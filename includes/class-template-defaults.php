<?php
/**
 * Template_Defaults class.
 *
 * Manages per-template SEO defaults with context awareness for different template types.
 * Supports: front_page, blog, post_type_archive_{type}, single_{post_type}, taxonomy_{taxonomy}.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Template_Defaults
 * Context-aware template SEO defaults with image field support.
 * Each template stores: title_pattern, description_pattern, schema_type, noindex, og_image.
 */
class Template_Defaults {
	/**
	 * Loader instance.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Single option key for all template defaults.
	 * Stores nested object: {context_key: {title_pattern, description_pattern, ...}}
	 */
	const OPTION_KEY = 'prc_schema_seo_templates';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Loader instance for hook registration.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;
		// Use 'init' instead of 'admin_init' so the setting is registered
		// for REST API requests (Site Editor saves occur via wp-json and
		// do not trigger admin_init).
		$loader->add_action( 'init', $this, 'register_settings' );
		$loader->add_action( 'init', $this, 'maybe_migrate_legacy_default' );
		$loader->add_filter( 'prc_schema_seo_schema_type_default', $this, 'apply_schema_override', 10, 3 );
		$loader->add_filter( 'prc_schema_seo_noindex', $this, 'apply_noindex_override', 10, 3 );
		$loader->add_filter( 'prc_schema_seo_description_fallback', $this, 'apply_description_pattern', 10, 2 );
		$loader->add_filter( 'prc_schema_seo_title', $this, 'apply_title_pattern', 9, 2 );
		$loader->add_filter( 'prc_schema_seo_og_image_fallback', $this, 'apply_og_image_fallback', 10, 2 );
	}

	/**
	 * Migrate legacy 'default' key to 'default_site' if needed.
	 *
	 * This is a one-time migration that runs on init.
	 * If 'default' exists and 'default_site' doesn't, copies data to 'default_site'.
	 *
	 * @return void
	 */
	public function maybe_migrate_legacy_default() {
		$all_templates = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $all_templates ) ) {
			return;
		}

		// Check if legacy 'default' exists and 'default_site' doesn't.
		$has_legacy       = isset( $all_templates['default'] ) && is_array( $all_templates['default'] );
		$has_default_site = isset( $all_templates['default_site'] ) && is_array( $all_templates['default_site'] );

		if ( $has_legacy && ! $has_default_site ) {
			// Migrate legacy 'default' to 'default_site'.
			$all_templates['default_site'] = $all_templates['default'];

			// Optionally remove the legacy key (or keep for backwards compatibility).
			// unset( $all_templates['default'] );

			update_option( self::OPTION_KEY, $all_templates );
		}
	}

	/**
	 * Register single setting for all template contexts.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'general',
			self::OPTION_KEY,
			array(
				'type'              => 'object',
				'label'             => 'Search and Social Settings',
				'description'       => 'Default SEO and social settings for various template contexts.',
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array(
							'type'       => 'object',
							'properties' => array(
								'title_pattern'       => array( 'type' => 'string' ),
								'description_pattern' => array( 'type' => 'string' ),
								'schema_type'         => array( 'type' => 'string' ),
								'noindex'             => array( 'type' => 'boolean' ),
								'og_image'            => array( 'type' => 'integer' ),
							),
						),
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_all_templates' ),
			)
		);
	}

	/**
	 * Sanitize all template data (nested structure).
	 *
	 * @param mixed $value Raw templates data (nested array).
	 * @return array Sanitized templates data.
	 */
	public function sanitize_all_templates( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();

		// Sanitize each template context.
		foreach ( $value as $context_key => $template_data ) {
			$context_key = sanitize_key( $context_key );

			if ( ! is_array( $template_data ) ) {
				continue;
			}

			$sanitized[ $context_key ] = $this->sanitize_single_template( $template_data );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single template's data.
	 *
	 * @param array $data Raw template data.
	 * @return array Sanitized template data.
	 */
	private function sanitize_single_template( $data ) {
		$sanitized = array();

		if ( isset( $data['title_pattern'] ) ) {
			$sanitized['title_pattern'] = sanitize_text_field( $data['title_pattern'] );
		}

		if ( isset( $data['description_pattern'] ) ) {
			$sanitized['description_pattern'] = sanitize_textarea_field( $data['description_pattern'] );
		}

		if ( isset( $data['schema_type'] ) ) {
			$sanitized['schema_type'] = sanitize_text_field( $data['schema_type'] );
		}

		if ( isset( $data['noindex'] ) ) {
			$sanitized['noindex'] = rest_sanitize_boolean( $data['noindex'] );
		}

		if ( isset( $data['og_image'] ) ) {
			$sanitized['og_image'] = absint( $data['og_image'] );
		}

		return $sanitized;
	}

	/**
	 * Get template defaults for a specific context.
	 *
	 * Uses three-tier fallback chain:
	 * 1. Template-specific settings (e.g., single_post, archive_category)
	 * 2. Context-tier default (default_singular or default_archive)
	 * 3. Site-wide default (default_site)
	 *
	 * @param array $context Template context from Template_Context.
	 * @return array Template defaults data.
	 */
	public function get_template_defaults( $context ) {
		if ( empty( $context['key'] ) ) {
			return $this->get_default_template_structure();
		}

		$all_templates = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $all_templates ) ) {
			$all_templates = array();
		}

		// Start with empty structure.
		$merged = $this->get_default_template_structure();

		// Build the fallback chain based on context type.
		$fallback_chain = $this->build_fallback_chain( $context );

		// Apply fallbacks in reverse order (least specific first, most specific last).
		$fallback_chain = array_reverse( $fallback_chain );
		foreach ( $fallback_chain as $key ) {
			if ( isset( $all_templates[ $key ] ) && is_array( $all_templates[ $key ] ) ) {
				$merged = wp_parse_args( $all_templates[ $key ], $merged );
			}
		}

		// Apply the template-specific data last (most specific).
		if ( isset( $all_templates[ $context['key'] ] ) && is_array( $all_templates[ $context['key'] ] ) ) {
			$data = $all_templates[ $context['key'] ];
			// Only override non-empty values from the specific template.
			foreach ( $data as $field => $value ) {
				if ( ! empty( $value ) || ( isset( $value ) && false === $value ) ) {
					$merged[ $field ] = $value;
				}
			}
		}

		return $merged;
	}

	/**
	 * Build the fallback chain for a given context.
	 *
	 * Returns an array of keys to check in order from most specific to least specific.
	 * Does not include the context's own key.
	 *
	 * @param array $context Template context from Template_Context.
	 * @return array Array of fallback keys.
	 */
	private function build_fallback_chain( $context ) {
		$chain = array();
		$key   = isset( $context['key'] ) ? $context['key'] : '';

		// If this is already a default context, determine its chain.
		if ( 'default_site' === $key ) {
			// Site-wide default has no further fallback.
			return $chain;
		}

		if ( 'default_singular' === $key || 'default_archive' === $key ) {
			// Context-tier defaults fall back to site-wide.
			$chain[] = 'default_site';
			return $chain;
		}

		// Legacy 'default' key - treat as alias for default_site.
		if ( 'default' === $key ) {
			return $chain;
		}

		// For regular template contexts, build the full chain.
		$default_key = $this->get_default_key_for_context( $context );

		if ( $default_key ) {
			// Add context-tier default (singular or archive).
			$chain[] = $default_key;

			// If it's singular or archive tier, also add site-wide.
			if ( 'default_singular' === $default_key || 'default_archive' === $default_key ) {
				$chain[] = 'default_site';
			}
		} elseif ( 'default_site' !== $key ) {
			// If no context-tier default, fall back directly to site-wide.
			$chain[] = 'default_site';
		}

		return $chain;
	}

	/**
	 * Get the appropriate default key for a given context.
	 *
	 * @param array $context Template context from Template_Context.
	 * @return string|null Default key or null if context doesn't need a default.
	 */
	private function get_default_key_for_context( $context ) {
		$type = isset( $context['type'] ) ? $context['type'] : '';

		// Singular contexts (single posts, pages, custom post types, attachments).
		$singular_contexts = array(
			Template_Context::CONTEXT_SINGLE_POST_TYPE,
			Template_Context::CONTEXT_ATTACHMENT,
		);
		if ( in_array( $type, $singular_contexts, true ) ) {
			return 'default_singular';
		}

		// Archive contexts (post type archives, taxonomy archives, author, date).
		$archive_contexts = array(
			Template_Context::CONTEXT_POST_TYPE_ARCHIVE,
			Template_Context::CONTEXT_TAXONOMY,
			Template_Context::CONTEXT_BLOG,
			Template_Context::CONTEXT_AUTHOR,
			Template_Context::CONTEXT_DATE,
			Template_Context::CONTEXT_SEARCH,
		);
		if ( in_array( $type, $archive_contexts, true ) ) {
			return 'default_archive';
		}

		// For front page, 404, and unknown, use site-wide default.
		$site_contexts = array(
			Template_Context::CONTEXT_FRONT_PAGE,
			Template_Context::CONTEXT_404,
			Template_Context::CONTEXT_UNKNOWN,
		);
		if ( in_array( $type, $site_contexts, true ) ) {
			return 'default_site';
		}

		// No default for this context type.
		return null;
	}

	/**
	 * Get default template structure.
	 *
	 * @return array Default template data structure.
	 */
	private function get_default_template_structure() {
		return array(
			'title_pattern'       => '',
			'description_pattern' => '',
			'schema_type'         => '',
			'noindex'             => false,
			'og_image'            => 0,
		);
	}

	/**
	 * Get template defaults for current context.
	 *
	 * @param int|null $post_id Optional post ID for context determination.
	 * @return array Template defaults.
	 */
	public function get_current_template_defaults( $post_id = null ) {
		$context = Template_Context::get_current_context();
		return $this->get_template_defaults( $context );
	}

	/**
	 * Apply schema type override from template defaults.
	 *
	 * @param string $type      Current schema type.
	 * @param string $post_type Post type slug.
	 * @param int    $post_id   Post ID (optional, for context-specific checks).
	 * @return string Schema type.
	 */
	public function apply_schema_override( $type, $post_type, $post_id = 0 ) {
		// Get the actual current template context - this properly detects front page, search, 404, etc.
		$context = Template_Context::get_current_context();

		// Fallback to post type-based context if we couldn't detect or are in an unknown context.
		if ( Template_Context::CONTEXT_UNKNOWN === $context['type'] && ! empty( $post_type ) ) {
			$context = array(
				'type'      => Template_Context::CONTEXT_SINGLE_POST_TYPE,
				'key'       => 'single_' . $post_type,
				'post_type' => $post_type,
				'taxonomy'  => '',
				'label'     => '',
			);
		}

		$defaults = $this->get_template_defaults( $context );

		if ( ! empty( $defaults['schema_type'] ) ) {
			return $defaults['schema_type'];
		}

		return $type;
	}

	/**
	 * Apply noindex override from template defaults.
	 *
	 * @param bool  $noindex  Current noindex flag.
	 * @param int   $post_id  Post ID.
	 * @param array $seo_data SEO metadata array.
	 * @return bool Noindex flag.
	 */
	public function apply_noindex_override( $noindex, $post_id, $seo_data ) {
		// Get the actual current template context - this properly detects front page, search, 404, etc.
		$context = Template_Context::get_current_context();

		// Fallback to post type-based context if we couldn't detect or are in an unknown context.
		if ( Template_Context::CONTEXT_UNKNOWN === $context['type'] && $post_id ) {
			$post_type = get_post_type( $post_id );
			if ( $post_type ) {
				$context = array(
					'type'      => Template_Context::CONTEXT_SINGLE_POST_TYPE,
					'key'       => 'single_' . $post_type,
					'post_type' => $post_type,
					'taxonomy'  => '',
					'label'     => '',
				);
			}
		}

		$defaults = $this->get_template_defaults( $context );

		if ( isset( $defaults['noindex'] ) && $defaults['noindex'] ) {
			return true;
		}

		return $noindex;
	}

	/**
	 * Apply title pattern from template defaults.
	 *
	 * Uses the three-tier fallback chain:
	 * template-specific → context-tier default → site-wide default.
	 *
	 * @param string $title   Current title.
	 * @param int    $post_id Post ID.
	 * @return string Title.
	 */
	public function apply_title_pattern( $title, $post_id ) {
		// Get the actual current template context - this properly detects front page, search, 404, etc.
		$context = Template_Context::get_current_context();

		// Fallback to post type-based context if we couldn't detect or are in an unknown context.
		if ( Template_Context::CONTEXT_UNKNOWN === $context['type'] && $post_id ) {
			// do_action( 'qm/debug', 'Fallback to post type-based context for title pattern: ' . print_r( $post_id, true ) );
			$post_type = get_post_type( $post_id );
			if ( $post_type ) {
				$context = array(
					'type'      => Template_Context::CONTEXT_SINGLE_POST_TYPE,
					'key'       => 'single_' . $post_type,
					'post_type' => $post_type,
					'taxonomy'  => '',
					'label'     => '',
				);
			}
		}

		// get_template_defaults() handles the entire three-tier fallback chain.
		$defaults = $this->get_template_defaults( $context );
		// do_action( 'qm/debug', 'Defaults for title pattern: ' . print_r( $defaults, true ) );

		if ( empty( $defaults['title_pattern'] ) ) {
			return $title;
		}

		$resolved = $this->resolve_pattern( $defaults['title_pattern'], $post_id );
		return $resolved ?: $title;
	}

	/**
	 * Apply description pattern from template defaults.
	 *
	 * Uses the three-tier fallback chain:
	 * template-specific → context-tier default → site-wide default.
	 *
	 * @param string $description Current description.
	 * @param int    $post_id     Post ID.
	 * @return string Description.
	 */
	public function apply_description_pattern( $description, $post_id ) {
		// Get the actual current template context - this properly detects front page, search, 404, etc.
		$context = Template_Context::get_current_context();

		// Fallback to post type-based context if we couldn't detect or are in an unknown context.
		if ( Template_Context::CONTEXT_UNKNOWN === $context['type'] && $post_id ) {
			$post_type = get_post_type( $post_id );
			if ( $post_type ) {
				$context = array(
					'type'      => Template_Context::CONTEXT_SINGLE_POST_TYPE,
					'key'       => 'single_' . $post_type,
					'post_type' => $post_type,
					'taxonomy'  => '',
					'label'     => '',
				);
			}
		}

		// get_template_defaults() handles the entire three-tier fallback chain.
		$defaults = $this->get_template_defaults( $context );

		if ( empty( $defaults['description_pattern'] ) ) {
			return $description;
		}

		$resolved = $this->resolve_pattern( $defaults['description_pattern'], $post_id );
		return $resolved ?: $description;
	}

	/**
	 * Apply OG image fallback from template defaults.
	 *
	 * @param int $og_image Current OG image ID.
	 * @param int $post_id  Post ID.
	 * @return int OG image ID.
	 */
	public function apply_og_image_fallback( $og_image, $post_id ) {
		if ( ! empty( $og_image ) ) {
			return $og_image;
		}

		// Get the actual current template context - this properly detects front page, search, 404, etc.
		$context = Template_Context::get_current_context();

		// Fallback to post type-based context if we couldn't detect or are in an unknown context.
		if ( Template_Context::CONTEXT_UNKNOWN === $context['type'] && $post_id ) {
			$post_type = get_post_type( $post_id );
			if ( $post_type ) {
				$context = array(
					'type'      => Template_Context::CONTEXT_SINGLE_POST_TYPE,
					'key'       => 'single_' . $post_type,
					'post_type' => $post_type,
					'taxonomy'  => '',
					'label'     => '',
				);
			}
		}

		$defaults = $this->get_template_defaults( $context );

		if ( ! empty( $defaults['og_image'] ) ) {
			return absint( $defaults['og_image'] );
		}

		return $og_image;
	}

	/**
	 * Resolve pattern tokens to actual values.
	 *
	 * @param string $pattern Pattern with tokens.
	 * @param int    $post_id Post ID for context.
	 * @return string Resolved pattern.
	 */
	private function resolve_pattern( $pattern, $post_id ) {
		if ( empty( $pattern ) ) {
			return '';
		}

		$post         = get_post( $post_id );
		$post_type    = $post ? $post->post_type : '';
		$site_name    = get_bloginfo( 'name' );
		$site_tagline = get_bloginfo( 'description' );
		$primary_cat  = $this->get_primary_category_name( $post_id );
		$year         = $post ? gmdate( 'Y', strtotime( $post->post_date_gmt ) ) : gmdate( 'Y' );
		$author_name  = $post ? get_the_author_meta( 'display_name', $post->post_author ) : '';
		$categories   = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'names' ) );
		$tags         = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );

		// Use existing SEO data like title and description if set, otherwise use the post title and excerpt.
		$existing_seo_data = get_post_meta( $post_id, '_prc_seo_data', true );
		$title             = isset( $existing_seo_data['title'] ) ? $existing_seo_data['title'] : false;
		if ( ! $title ) {
			$title = $post ? $post->post_title : '';
		}
		$description = isset( $existing_seo_data['description'] ) ? $existing_seo_data['description'] : false;
		if ( ! $description ) {
			$description = $post ? $post->post_excerpt : '';
		}

		// Resolve %object_title%, %object_type%, and %object_description% tokens based on current context.
		$object_title       = isset( $existing_seo_data['title'] ) ? $existing_seo_data['title'] : $this->resolve_object_title( $post_id );
		$object_type        = isset( $existing_seo_data['type'] ) ? $existing_seo_data['type'] : $this->resolve_object_type( $post_id );
		$object_description = isset( $existing_seo_data['description'] ) ? $existing_seo_data['description'] : $this->resolve_object_description( $post_id );

		/**
		 * Filter the separator character used in title patterns.
		 *
		 * @param string $separator Default separator.
		 */
		$separator = apply_filters( 'prc_schema_seo_title_separator', ' | ' );

		$core_tokens = array(
			'%sep%'                => $separator,
			'%post_title%'         => $title,
			'%post_excerpt%'       => $description,
			'%post_type%'          => $post_type,
			'%post_date%'          => $post ? gmdate( get_option( 'date_format' ), strtotime( $post->post_date_gmt ) ) : '',
			'%post_url%'           => $post ? get_permalink( $post_id ) : '',
			'%site_name%'          => $site_name,
			'%site_tagline%'       => $site_tagline,
			'%year%'               => $year,
			'%author%'             => $author_name,
			'%primary_category%'   => $primary_cat,
			'%categories%'         => ! empty( $categories ) ? implode( ', ', $categories ) : '',
			'%tags%'               => ! empty( $tags ) ? implode( ', ', $tags ) : '',
			'%object_title%'       => $object_title,
			'%object_type%'        => $object_type,
			'%object_description%' => $object_description,
		);

		$core_tokens = apply_filters( 'prc_schema_seo_pattern_tokens', $core_tokens, $post_id );

		// Replace simple tokens.
		$value = strtr( $pattern, $core_tokens );

		// Dynamic tokens: %primary_term:taxonomy% and %terms:taxonomy%.
		if ( preg_match_all( '/%((primary_term|terms):[a-z0-9_-]+)%/i', $value, $matches ) ) {
			foreach ( $matches[1] as $full ) {
				list( $type, $taxonomy ) = explode( ':', $full );
				$replacement             = '';
				if ( 'primary_term' === $type ) {
					$replacement = Primary_Term::get_name( $post_id, $taxonomy, true );
				} elseif ( 'terms' === $type ) {
					$names       = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
					$replacement = ! empty( $names ) ? implode( ', ', $names ) : '';
				}
				$value = str_replace( '%' . $full . '%', $replacement, $value );
			}
		}

		$value = trim( preg_replace( '/\s+/', ' ', $value ) );
		return $value;
	}

	/**
	 * Resolve object type based on current context.
	 *
	 * This method detects the current WordPress query context and returns
	 * the object type name/slug:
	 * - Single post: post type slug
	 * - Post type archive: post type slug
	 * - Taxonomy archive: taxonomy slug
	 *
	 * @param int $post_id Post ID (optional, for singular contexts).
	 * @return string Object type slug or empty string.
	 */
	private function resolve_object_type( $post_id = 0 ) {
		// Single post/page/CPT - return post type slug.
		if ( is_singular() ) {
			if ( $post_id ) {
				$post_type = get_post_type_object( get_post_type( $post_id ) );
				return $post_type ? $post_type->labels->singular_name : '';
			}
			// Fallback to current post if no ID provided.
			global $post;
			if ( $post ) {
				$post_type = get_post_type_object( get_post_type( $post->ID ) );
				return $post_type ? $post_type->labels->singular_name : '';
			}
			return '';
		}

		// Taxonomy archive (category, tag, or custom taxonomy) - return taxonomy slug.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->taxonomy ) ) {
				$taxonomy = get_taxonomy( $term->taxonomy );
				return $taxonomy ? $taxonomy->labels->singular_name : '';
			}
			return '';
		}

		// Post type archive - return post type slug.
		if ( is_post_type_archive() ) {
			$post_type     = get_query_var( 'post_type' );
			$post_type     = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_obj = get_post_type_object( $post_type );
			return $post_type_obj ? $post_type_obj->labels->name : '';
		}

		// Blog/home - return 'Publications' since it's the global cross post-type archive.
		if ( is_home() ) {
			return __( 'Publications', 'prc-schema-seo' );
		}

		return '';
	}

	/**
	 * Resolve object title based on current context.
	 *
	 * This method detects the current WordPress query context and returns
	 * the appropriate title:
	 * - Single post: post title
	 * - Single term: term name
	 * - Post type archive: post type label
	 * - Taxonomy archive: taxonomy label
	 *
	 * @param int $post_id Post ID (optional, for singular contexts).
	 * @return string Object title or empty string.
	 */
	private function resolve_object_title( $post_id = 0 ) {
		// Single post/page/CPT.
		if ( is_singular() ) {
			if ( $post_id ) {
				return get_the_title( $post_id );
			}
			// Fallback to current post if no ID provided.
			global $post;
			if ( $post ) {
				return get_the_title( $post->ID );
			}
			return '';
		}

		// Single term (category, tag, or custom taxonomy term).
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->name ) ) {
				return $term->name;
			}
			return '';
		}

		// Post type archive.
		if ( is_post_type_archive() ) {
			$post_type     = get_query_var( 'post_type' );
			$post_type     = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj && isset( $post_type_obj->labels->name ) ) {
				return $post_type_obj->labels->name;
			}
			return '';
		}

		// Taxonomy archive (general case - not a specific term).
		// This handles cases where we're viewing a taxonomy archive page.
		$queried_object = get_queried_object();
		if ( $queried_object && isset( $queried_object->taxonomy ) ) {
			$taxonomy_obj = get_taxonomy( $queried_object->taxonomy );
			if ( $taxonomy_obj && isset( $taxonomy_obj->labels->name ) ) {
				return $taxonomy_obj->labels->name;
			}
		}

		return '';
	}

	/**
	 * Resolve object description based on current context.
	 *
	 * This method detects the current WordPress query context and returns
	 * the appropriate description:
	 * - Single post: post excerpt
	 * - Single term: term description
	 * - Post type archive: empty (no default description)
	 *
	 * @param int $post_id Post ID (optional, for singular contexts).
	 * @return string Object description or empty string.
	 */
	private function resolve_object_description( $post_id = 0 ) {
		// Single post/page/CPT - return the post excerpt.
		if ( is_singular() ) {
			if ( $post_id ) {
				$post = get_post( $post_id );
				if ( $post && ! empty( $post->post_excerpt ) ) {
					return $post->post_excerpt;
				}
				// Fallback: generate excerpt from content if no manual excerpt.
				if ( $post && ! empty( $post->post_content ) ) {
					return wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '...' );
				}
			}
			// Fallback to current post if no ID provided.
			global $post;
			if ( $post ) {
				if ( ! empty( $post->post_excerpt ) ) {
					return $post->post_excerpt;
				}
				// Fallback: generate excerpt from content if no manual excerpt.
				if ( ! empty( $post->post_content ) ) {
					return wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '...' );
				}
			}
			return '';
		}

		// Single term (category, tag, or custom taxonomy term) - return term description.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->description ) && ! empty( $term->description ) ) {
				return $term->description;
			}
			return get_bloginfo( 'description' );
		}

		// Post type archive - return post type description.
		if ( is_post_type_archive() ) {
			$post_type     = get_query_var( 'post_type' );
			$post_type     = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj && isset( $post_type_obj->description ) && ! empty( $post_type_obj->description ) ) {
				return $post_type_obj->description;
			}
			return get_bloginfo( 'description' );
		}

		return get_bloginfo( 'description' );
	}


	/**
	 * Get primary category name.
	 *
	 * @param int $post_id Post ID.
	 * @return string Primary category name.
	 */
	private function get_primary_category_name( $post_id ) {
		return Primary_Term::get_name( $post_id, 'category', true );
	}

	/**
	 * Update template defaults for a specific context.
	 *
	 * @param array $context Template context from Template_Context.
	 * @param array $data    Template data to save.
	 * @return bool Success status.
	 */
	public function update_template_defaults( $context, $data ) {
		if ( empty( $context['key'] ) ) {
			return false;
		}

		$all_templates = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $all_templates ) ) {
			$all_templates = array();
		}

		// Sanitize the data for this template.
		$all_templates[ $context['key'] ] = $this->sanitize_single_template( $data );

		return update_option( self::OPTION_KEY, $all_templates );
	}

	/**
	 * Get all template defaults (for settings UI).
	 *
	 * @return array All template defaults grouped by context.
	 */
	public function get_all_template_defaults() {
		$all_templates = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $all_templates ) ) {
			$all_templates = array();
		}

		return apply_filters( 'prc_schema_seo_all_template_defaults', $all_templates );
	}
}
