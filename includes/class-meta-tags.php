<?php
/**
 * Meta_Tags class.
 *
 * Outputs meta tags (description, Open Graph, Twitter Card, robots, canonical) in the document head.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Meta_Tags
 *
 * Handles building, caching, and rendering meta tags for posts, term archives, and post type archives.
 */
class Meta_Tags {
	/**
	 * Cache group for rendered meta tags (shares group with schema output for unified invalidation).
	 */
	const CACHE_GROUP = 'prc_schema_seo_output';

	/**
	 * Cache TTL (1 hour).
	 */
	const CACHE_TTL = 3600;

	/**
	 * Loader instance.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Metadata service.
	 *
	 * @var Metadata
	 */
	protected $seo_metadata;

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader       = $loader;
		$this->seo_metadata = new Metadata( $loader );
		$this->init();
	}

	/**
	 * Initializes the hooks for this class.
	 *
	 * @return void
	 */
	public function init() {
		// Priority 2 so it runs after potential title filters and before schema (priority 1 currently used by Output).
		$this->loader->add_action( 'wp_head', $this, 'output_meta_tags', 2 );
		// Term archive meta tags.
		$this->loader->add_action( 'wp_head', $this, 'output_term_meta_tags', 2 );
		// Post type archive meta tags.
		$this->loader->add_action( 'wp_head', $this, 'output_post_type_archive_meta_tags', 2 );

		// Allow theme to adjust document title parts using our resolved <title/> tag.
		$this->loader->add_filter( 'pre_get_document_title', $this, 'filter_document_title', 10 );

		// Filter WordPress core canonical URL for singular posts (WordPress handles output via rel_canonical()).
		$this->loader->add_filter( 'get_canonical_url', $this, 'filter_canonical_url', 10, 2 );

		// Filter WordPress core robots meta tag (WordPress handles output via wp_robots()).
		$this->loader->add_filter( 'wp_robots', $this, 'filter_robots', 10 );
	}

	/**
	 * Filter WordPress core canonical URL for singular posts.
	 *
	 * Uses custom canonical URL from SEO metadata if set, otherwise returns the default.
	 * This integrates with WordPress's native rel_canonical() output.
	 *
	 * @param string   $canonical_url The post's canonical URL.
	 * @param \WP_Post $post          The post object.
	 * @return string Filtered canonical URL.
	 */
	public function filter_canonical_url( $canonical_url, $post ) {
		$post_type = get_post_type( $post->ID );

		// Handle attachments: point canonical to parent post.
		if ( 'attachment' === $post_type ) {
			$parent_id = wp_get_post_parent_id( $post->ID );
			if ( $parent_id ) {
				return get_permalink( $parent_id );
			}
		}

		if ( ! post_type_supports( $post_type, 'prc-schema-seo' ) ) {
			return $canonical_url;
		}

		$data = $this->seo_metadata->get_seo_data( $post->ID );
		$data = $this->seo_metadata->resolve_for_display( $data, $post->ID );

		if ( ! empty( $data['canonical_url'] ) ) {
			$canonical_url = $data['canonical_url'];
		}

		return apply_filters( 'prc_schema_seo_canonical_url', $canonical_url, $post->ID, $data );
	}

	/**
	 * Filter WordPress core robots meta tag.
	 *
	 * Adds noindex directive based on SEO metadata for singular posts and term archives.
	 * This integrates with WordPress's native wp_robots() output.
	 *
	 * @hook wp_robots
	 *
	 * @param array $robots Associative array of robots directives.
	 * @return array Filtered robots directives.
	 */
	public function filter_robots( $robots ) {
		$noindex = false;
		$data    = array();

		// Force noindex in non-production environments.
		if ( function_exists( 'wp_get_environment_type' ) && 'production' !== wp_get_environment_type() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			return $robots;
		}

		// Noindex paginated archive pages.
		if ( is_paged() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			return $robots;
		}

		// Noindex faceted search pages (PRC-specific query vars).
		$faceted_search_vars = array( 'formats', 'research-areas', 'topic', 'subtopic' );
		foreach ( $faceted_search_vars as $var ) {
			if ( ! empty( get_query_var( $var ) ) ) {
				$robots['noindex'] = true;
				$robots['follow']  = true;
				return $robots;
			}
		}

		// Handle singular posts.
		if ( is_singular() ) {
			$post_id   = get_the_ID();
			$post_type = get_post_type( $post_id );

			if ( $post_id && post_type_supports( $post_type, 'prc-schema-seo' ) ) {
				$data    = $this->seo_metadata->get_seo_data( $post_id );
				$data    = $this->seo_metadata->resolve_for_display( $data, $post_id );
				$noindex = ! empty( $data['noindex'] );
				$noindex = apply_filters( 'prc_schema_seo_noindex', $noindex, $post_id, $data );
			}
		}

		// Handle term archives.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && ! empty( $term->term_id ) ) {
				$meta_data = get_term_meta( $term->term_id, '_prc_seo_term_data', true );
				$meta_data = is_array( $meta_data ) ? $meta_data : array();
				$noindex   = ! empty( $meta_data['noindex'] );
				$noindex   = apply_filters( 'prc_schema_seo_noindex', $noindex, 0, $meta_data );
			}
		}

		// Handle post type archives.
		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			if ( ! empty( $post_type ) ) {
				$context           = array(
					'type'      => Template_Context::CONTEXT_POST_TYPE_ARCHIVE,
					'key'       => 'post_type_archive_' . $post_type,
					'post_type' => $post_type,
					'taxonomy'  => '',
					'label'     => '',
				);
				$template_defaults = new Template_Defaults( $this->loader );
				$defaults          = $template_defaults->get_template_defaults( $context );
				$noindex           = ! empty( $defaults['noindex'] );
				$noindex           = apply_filters( 'prc_schema_seo_noindex', $noindex, 0, $defaults );
			}
		}

		// Add noindex directive if needed.
		if ( $noindex ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}

		return $robots;
	}

	/**
	 * Filter document title to use resolved SEO title when present.
	 *
	 * Handles all template contexts:
	 * - Singular posts/pages/CPTs
	 * - Taxonomy archives (category, tag, custom taxonomies)
	 * - Post type archives
	 * - Search results
	 * - 404 pages
	 * - Front page
	 * - Blog page
	 * - Author archives
	 * - Date archives
	 *
	 * @param string $title Original title.
	 * @return string Filtered title.
	 */
	public function filter_document_title( $title ) {
		// Get the current template context.
		$context = Template_Context::get_current_context();

		// Handle singular posts.
		if ( is_singular() ) {
			$post_id = get_the_ID();
			if ( $post_id ) {
				$data = $this->seo_metadata->get_seo_data( $post_id );
				$data = $this->seo_metadata->resolve_for_display( $data, $post_id );
				if ( ! empty( $data['title'] ) ) {
					return $data['title'];
				}
			}
			return $title;
		}

		// Handle taxonomy archives (category, tag, custom taxonomies).
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && ! empty( $term->term_id ) ) {
				$meta_data = get_term_meta( $term->term_id, '_prc_seo_term_data', true );
				if ( is_array( $meta_data ) && ! empty( $meta_data['title'] ) ) {
					return $meta_data['title'];
				}
				// Fallback to template defaults.
				$template_defaults = new Template_Defaults( $this->loader );
				$defaults          = $template_defaults->get_template_defaults( $context );
				if ( ! empty( $defaults['title_pattern'] ) ) {
					$resolved = $this->resolve_archive_title_pattern( $defaults['title_pattern'], $term );
					if ( ! empty( $resolved ) ) {
						return $resolved;
					}
				}
				// Default to term name with site name.
				return $term->name . ' | ' . get_bloginfo( 'name' );
			}
			return $title;
		}

		// Handle post type archives.
		if ( is_post_type_archive() ) {
			$template_defaults = new Template_Defaults( $this->loader );
			$defaults          = $template_defaults->get_template_defaults( $context );
			if ( ! empty( $defaults['title_pattern'] ) ) {
				$resolved = $this->resolve_archive_title_pattern( $defaults['title_pattern'] );
				if ( ! empty( $resolved ) ) {
					return $resolved;
				}
			}
			// Default fallback.
			$post_type     = get_query_var( 'post_type' );
			$post_type     = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj ) {
				return $post_type_obj->labels->name . ' | ' . get_bloginfo( 'name' );
			}
			return $title;
		}

		// Handle search results.
		if ( is_search() ) {
			$template_defaults = new Template_Defaults( $this->loader );
			$defaults          = $template_defaults->get_template_defaults( $context );
			if ( ! empty( $defaults['title_pattern'] ) ) {
				$search_query = get_search_query();
				$resolved     = str_replace(
					array( '%search_query%', '%site_name%' ),
					array( $search_query, get_bloginfo( 'name' ) ),
					$defaults['title_pattern']
				);
				return $resolved;
			}
			return sprintf(
				/* translators: %s: search query */
				__( 'Search Results for "%s"', 'prc-schema-seo' ),
				get_search_query()
			) . ' | ' . get_bloginfo( 'name' );
		}

		$separator = apply_filters( 'prc_schema_seo_title_separator', ' | ' );

		// Handle 404 pages.
		if ( is_404() ) {
			$template_defaults = new Template_Defaults( $this->loader );
			$defaults          = $template_defaults->get_template_defaults( $context );
			if ( ! empty( $defaults['title_pattern'] ) ) {
				$resolved = str_replace(
					array( '%sep%', '%site_name%' ),
					array( $separator, get_bloginfo( 'name' ) ),
					$defaults['title_pattern']
				);
				return $resolved;
			}
			return __( 'Page Not Found', 'prc-schema-seo' ) . ' | ' . get_bloginfo( 'name' );
		}

		// Handle front page.
		if ( is_front_page() && ! is_home() ) {
			$template_defaults = new Template_Defaults( $this->loader );
			$defaults          = $template_defaults->get_template_defaults( $context );
			if ( ! empty( $defaults['title_pattern'] ) ) {
				$resolved = str_replace(
					array( '%sep%', '%site_name%', '%site_tagline%' ),
					array( $separator, get_bloginfo( 'name' ), get_bloginfo( 'description' ) ),
					$defaults['title_pattern']
				);
				return $resolved;
			}
			return $title;
		}

		// Handle blog page.
		if ( is_home() ) {
			$template_defaults = new Template_Defaults( $this->loader );
			$defaults          = $template_defaults->get_template_defaults( $context );
			if ( ! empty( $defaults['title_pattern'] ) ) {
				$resolved = str_replace(
					array( '%sep%', '%site_name%', '%site_tagline%' ),
					array( $separator, get_bloginfo( 'name' ), get_bloginfo( 'description' ) ),
					$defaults['title_pattern']
				);
				return $resolved;
			}
			return $title;
		}

		// Handle author archives.
		if ( is_author() ) {
			$author            = get_queried_object();
			$template_defaults = new Template_Defaults( $this->loader );
			$defaults          = $template_defaults->get_template_defaults( $context );
			if ( ! empty( $defaults['title_pattern'] ) ) {
				$resolved = str_replace(
					array( '%sep%', '%author%', '%site_name%' ),
					array( $separator, $author ? $author->display_name : '', get_bloginfo( 'name' ) ),
					$defaults['title_pattern']
				);
				return $resolved;
			}
			if ( $author ) {
				return $author->display_name . ' | ' . get_bloginfo( 'name' );
			}
			return $title;
		}

		// Handle date archives.
		if ( is_date() ) {
			$template_defaults = new Template_Defaults( $this->loader );
			$defaults          = $template_defaults->get_template_defaults( $context );
			if ( ! empty( $defaults['title_pattern'] ) ) {
				$date_title = '';
				if ( is_year() ) {
					$date_title = get_the_date( 'Y' );
				} elseif ( is_month() ) {
					$date_title = get_the_date( 'F Y' );
				} elseif ( is_day() ) {
					$date_title = get_the_date();
				}
				$resolved = str_replace(
					array( '%sep%', '%date%', '%site_name%' ),
					array( $separator, $date_title, get_bloginfo( 'name' ) ),
					$defaults['title_pattern']
				);
				return $resolved;
			}
			return $title;
		}

		return $title;
	}

	/**
	 * Resolve title pattern tokens for archive pages.
	 *
	 * @param string       $pattern The title pattern with tokens.
	 * @param WP_Term|null $term    Optional term object for taxonomy archives.
	 * @return string Resolved title.
	 */
	private function resolve_archive_title_pattern( $pattern, $term = null ) {
		$separator = apply_filters( 'prc_schema_seo_title_separator', ' | ' );
		$tokens    = array(
			'%sep%'          => $separator,
			'%site_name%'    => get_bloginfo( 'name' ),
			'%site_tagline%' => get_bloginfo( 'description' ),
		);

		do_action( 'qm/debug', 'resolve_archive_title_pattern' );
		do_action( 'qm/debug', 'pattern: ' . print_r( $pattern, true ) );
		do_action( 'qm/debug', 'term: ' . print_r( $term, true ) );

		// Add term-specific tokens.
		if ( $term ) {
			$tokens['%term_name%']        = $term->name;
			$tokens['%term_description%'] = wp_strip_all_tags( term_description( $term->term_id ) );
			$tokens['%taxonomy%']         = $term->taxonomy;

			$taxonomy_obj               = get_taxonomy( $term->taxonomy );
			$tokens['%taxonomy_label%'] = $taxonomy_obj ? $taxonomy_obj->labels->singular_name : $term->taxonomy;

			// Support %object_title% as an alias for %term_name%.
			$tokens['%object_title%'] = $term->name;
		}

		// Add post type archive tokens.
		if ( is_post_type_archive() ) {
			do_action( 'qm/debug', 'is_post_type_archive()' );
			$post_type     = get_query_var( 'post_type' );
			$post_type     = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_obj = get_post_type_object( $post_type );

			$tokens['%post_type%']    = $post_type;
			$tokens['%object_title%'] = $post_type_obj ? $post_type_obj->labels->name : $post_type;
		}

		return strtr( $pattern, $tokens );
	}

	/**
	 * Warm meta tags cache for a post.
	 *
	 * Builds and caches meta tags for a given post ID. Used by wp_head output and
	 * by CLI migration to pre-cache after Yoast migration.
	 *
	 * @param int $post_id Post ID.
	 * @return string Meta tags HTML. Empty string if post type does not support prc-schema-seo.
	 */
	public function warm_post_cache( int $post_id ): string {
		$post_type = get_post_type( $post_id );
		if ( ! post_type_supports( $post_type, 'prc-schema-seo' ) ) {
			return '';
		}

		$cache_key = 'meta_tags_' . $post_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->seo_metadata->get_seo_data( $post_id );
		$data = $this->seo_metadata->resolve_for_display( $data, $post_id );

		$default_canonical = get_permalink( $post_id );
		$canonical         = ! empty( $data['canonical_url'] ) ? $data['canonical_url'] : $default_canonical;
		$canonical         = apply_filters( 'prc_schema_seo_canonical_url', $canonical, $post_id, $data );

		$og_image_url    = '';
		$og_image_width  = '';
		$og_image_height = '';
		$og_image_type   = '';
		if ( function_exists( '\\PRC\\Platform\\Art_Direction\\get' ) ) {
			$social_art = \PRC\Platform\Art_Direction\get( $post_id, 'social' );
			if ( false !== $social_art && ! empty( $social_art['url'] ) ) {
				$og_image_url    = $social_art['url'];
				$og_image_width  = $social_art['width'] ?? '';
				$og_image_height = $social_art['height'] ?? '';
				if ( ! empty( $social_art['id'] ) ) {
					$og_image_type = get_post_mime_type( $social_art['id'] );
				}
			}
		}

		$og_image_url = apply_filters( 'prc_schema_seo_og_image_url', $og_image_url, $post_id, $data );

		$twitter_site    = apply_filters( 'prc_schema_seo_twitter_site', '@pewresearch' );
		$primary_category = '';
		$is_article       = strtolower( $data['schema_type'] ) === 'article';
		do_action( 'qm/debug', 'Is article: ' . print_r( $is_article, true ) );
		if ( $is_article ) {
			$primary_category = Primary_Term::get_name( $post_id, 'category', false );
		}

		$author_names = array();
		if ( $is_article ) {
			if ( class_exists( '\PRC\Platform\Staff_Bylines\Bylines' ) ) {
				$bylines_instance = new \PRC\Platform\Staff_Bylines\Bylines( $post_id );
				$bylines          = $bylines_instance->get();
				if ( ! empty( $bylines ) && ! is_wp_error( $bylines ) ) {
					foreach ( $bylines as $byline ) {
						if ( ! empty( $byline['name'] ) ) {
							$author_names[] = $byline['name'];
						}
					}
				}
			}
			if ( empty( $author_names ) ) {
				$author_name = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
				if ( $author_name ) {
					$author_names[] = $author_name;
				}
			}
		}

		$meta = array(
			'description'     => $data['description'],
			'og:locale'       => get_locale(),
			'og:type'         => $is_article ? 'article' : 'website',
			'og:site_name'    => get_bloginfo( 'name' ),
			'og:title'        => $data['og_title'],
			'og:description'  => $data['og_description'],
			'og:url'          => $canonical,
			'og:image'        => $og_image_url,
			'og:image:width'  => $og_image_width,
			'og:image:height' => $og_image_height,
			'og:image:type'   => $og_image_type,
			'twitter:card'    => $og_image_url ? 'summary_large_image' : 'summary',
			'twitter:site'    => $twitter_site,
			'twitter:creator' => $twitter_site,
		);

		if ( $is_article ) {
			$publisher_url                  = apply_filters( 'prc_schema_seo_article_publisher_url', 'https://www.facebook.com/pewresearch' );
			$meta['article:publisher']      = $publisher_url;
			$meta['article:published_time'] = get_the_date( 'c', $post_id );
			$meta['article:modified_time']  = get_the_modified_date( 'c', $post_id );

			if ( ! empty( $author_names ) ) {
				$meta['article:author'] = $author_names;
			}

			if ( ! empty( $primary_category ) ) {
				$meta['article:section'] = $primary_category;
			}
		}

		$meta = apply_filters( 'prc_schema_seo_meta_tags', $meta, $post_id, $data );

		$html = $this->build_meta_html( $meta );

		wp_cache_set( $cache_key, $html, self::CACHE_GROUP, self::CACHE_TTL );

		return $html;
	}

	/**
	 * Output meta tags for singular posts.
	 *
	 * @return void
	 */
	public function output_meta_tags() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$html = $this->warm_post_cache( $post_id );
		if ( '' === $html ) {
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when building string.
		do_action( 'prc_schema_seo_after_meta_tags', $post_id, $html );
	}

	/**
	 * Warm meta tags cache for a term archive.
	 *
	 * Builds and caches meta tags for a given term. Used by wp_head output and
	 * by CLI migration to pre-cache after Yoast migration.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string Meta tags HTML. Empty string if term not found.
	 */
	public function warm_term_cache( int $term_id, string $taxonomy ): string {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		$cache_key = 'meta_tags_term_' . $term_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$meta_data = get_term_meta( $term_id, '_prc_seo_term_data', true );
		$meta_data = is_array( $meta_data ) ? $meta_data : array();

		$title             = ! empty( $meta_data['title'] ) ? $meta_data['title'] : $term->name;
		$description       = ! empty( $meta_data['description'] ) ? $meta_data['description'] : term_description( $term_id );
		$description       = wp_strip_all_tags( $description );
		$og_image_id       = ! empty( $meta_data['og_image'] ) ? absint( $meta_data['og_image'] ) : 0;
		$default_canonical = get_term_link( $term );
		$canonical         = ! empty( $meta_data['canonical_url'] ) ? $meta_data['canonical_url'] : $default_canonical;

		if ( is_wp_error( $canonical ) ) {
			$canonical = $default_canonical;
		}

		$og_image_url = $og_image_id ? wp_get_attachment_image_url( $og_image_id, 'full' ) : '';
		$og_image_url = apply_filters( 'prc_schema_seo_og_image_url', $og_image_url, 0, $meta_data );

		$meta = array(
			'description'    => $description,
			'og:locale'      => get_locale(),
			'og:type'        => 'website',
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => $canonical,
			'og:image'       => $og_image_url,
			'canonical'      => $canonical,
		);

		$meta = apply_filters( 'prc_schema_seo_meta_tags', $meta, 0, $meta_data );
		$html = $this->build_meta_html( $meta );
		wp_cache_set( $cache_key, $html, self::CACHE_GROUP, self::CACHE_TTL );

		return $html;
	}

	/**
	 * Output meta tags for term archive pages.
	 *
	 * @return void
	 */
	public function output_term_meta_tags() {
		if ( is_singular() ) {
			return;
		}
		if ( ! ( is_category() || is_tag() || is_tax() ) ) {
			return;
		}
		$term = get_queried_object();
		if ( ! $term || empty( $term->term_id ) ) {
			return;
		}

		$html = $this->warm_term_cache( $term->term_id, $term->taxonomy );
		if ( '' === $html ) {
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		do_action( 'prc_schema_seo_after_meta_tags', $term->term_id, $html );
	}

	/**
	 * Output meta tags for post type archive pages.
	 *
	 * @return void
	 */
	public function output_post_type_archive_meta_tags() {
		if ( ! is_post_type_archive() ) {
			return;
		}

		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;

		if ( empty( $post_type ) ) {
			return;
		}

		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj ) {
			return;
		}

		$cache_key = 'meta_tags_post_type_archive_' . $post_type;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML string.
			do_action( 'prc_schema_seo_after_meta_tags', 0, $cached );
			return;
		}

		// Build template context for this post type archive.
		$context = array(
			'type'      => Template_Context::CONTEXT_POST_TYPE_ARCHIVE,
			'key'       => 'post_type_archive_' . $post_type,
			'post_type' => $post_type,
			'taxonomy'  => '',
			'label'     => sprintf(
				/* translators: %s: post type label */
				__( '%s Archive', 'prc-schema-seo' ),
				$post_type_obj->labels->name
			),
		);

		// Get template defaults (includes fallback chain: template-specific → archive default → site default).
		$template_defaults = new Template_Defaults( $this->loader );
		$defaults          = $template_defaults->get_template_defaults( $context );

		// Resolve title - use template pattern or fallback to post type label.
		$title = $post_type_obj->labels->name . ' | ' . get_bloginfo( 'name' );
		if ( ! empty( $defaults['title_pattern'] ) ) {
			$resolved_title = $this->resolve_archive_title_pattern( $defaults['title_pattern'] );
			if ( ! empty( $resolved_title ) ) {
				$title = $resolved_title;
			}
		}

		// Resolve description - use template pattern or fallback to post type description.
		$description = ! empty( $post_type_obj->description ) ? wp_strip_all_tags( $post_type_obj->description ) : '';
		if ( ! empty( $defaults['description_pattern'] ) ) {
			$resolved_description = $this->resolve_archive_description_pattern( $defaults['description_pattern'], $post_type_obj );
			if ( ! empty( $resolved_description ) ) {
				$description = $resolved_description;
			}
		}

		// Get canonical URL.
		$canonical = get_post_type_archive_link( $post_type );

		// Get OG image from template defaults.
		$og_image_id  = ! empty( $defaults['og_image'] ) ? absint( $defaults['og_image'] ) : 0;
		$og_image_url = $og_image_id ? wp_get_attachment_image_url( $og_image_id, 'full' ) : '';
		$og_image_url = apply_filters( 'prc_schema_seo_og_image_url', $og_image_url, 0, $defaults );

		$meta = array(
			'description'    => $description,
			'og:locale'      => get_locale(),
			'og:type'        => 'website',
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => $canonical,
			'og:image'       => $og_image_url,
			'twitter:card'   => $og_image_url ? 'summary_large_image' : 'summary',
			'canonical'      => $canonical,
			// Note: Robots meta tag is handled by WordPress core via the wp_robots filter.
		);

		$meta = apply_filters( 'prc_schema_seo_meta_tags', $meta, 0, $defaults );
		$html = $this->build_meta_html( $meta );
		wp_cache_set( $cache_key, $html, self::CACHE_GROUP, self::CACHE_TTL );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when building string.
		do_action( 'prc_schema_seo_after_meta_tags', 0, $html );
	}

	/**
	 * Resolve description pattern tokens for post type archive pages.
	 *
	 * @param string             $pattern       The description pattern with tokens.
	 * @param \WP_Post_Type|null $post_type_obj Optional post type object.
	 * @return string Resolved description.
	 */
	private function resolve_archive_description_pattern( $pattern, $post_type_obj = null ) {
		$separator = apply_filters( 'prc_schema_seo_title_separator', ' | ' );
		$tokens    = array(
			'%sep%'          => $separator,
			'%site_name%'    => get_bloginfo( 'name' ),
			'%site_tagline%' => get_bloginfo( 'description' ),
		);

		// Add post type archive tokens.
		if ( $post_type_obj ) {
			$tokens['%post_type%']          = $post_type_obj->name;
			$tokens['%object_title%']       = $post_type_obj->labels->name;
			$tokens['%object_description%'] = ! empty( $post_type_obj->description ) ? wp_strip_all_tags( $post_type_obj->description ) : '';
		}

		return strtr( $pattern, $tokens );
	}

	/**
	 * Build meta tag HTML string.
	 *
	 * @param array $meta Meta array.
	 * @return string HTML.
	 */
	/**
	 * Convert meta array to escaped HTML string.
	 *
	 * @param array $meta Key => value meta definitions.
	 * @return string HTML string.
	 */
	private function build_meta_html( $meta ) {
		$lines = array();

		foreach ( $meta as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			// Handle arrays (for multiple article:author tags).
			if ( is_array( $value ) ) {
				foreach ( $value as $single_value ) {
					if ( empty( $single_value ) ) {
						continue;
					}
					if ( 0 === strpos( $key, 'og:' ) || 0 === strpos( $key, 'article:' ) ) {
						$lines[] = sprintf( '<meta property="%s" content="%s" />', esc_attr( $key ), esc_attr( $single_value ) );
					}
				}
				continue;
			}

			if ( 'canonical' === $key ) {
				$lines[] = sprintf( '<link rel="canonical" href="%s" />', esc_url( $value ) );
				continue;
			}
			if ( 0 === strpos( $key, 'og:' ) || 0 === strpos( $key, 'article:' ) ) {
				$lines[] = sprintf( '<meta property="%s" content="%s" />', esc_attr( $key ), esc_attr( $value ) );
				continue;
			}
			if ( 0 === strpos( $key, 'twitter:' ) ) {
				$lines[] = sprintf( '<meta name="%s" content="%s" />', esc_attr( $key ), esc_attr( $value ) );
				continue;
			}
			// Generic name meta.
			$lines[] = sprintf( '<meta name="%s" content="%s" />', esc_attr( $key ), esc_attr( $value ) );
		}

		return PHP_EOL . implode( PHP_EOL, $lines ) . PHP_EOL;
	}
}
