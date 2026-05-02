<?php
/**
 * Template_Context class.
 *
 * Detects and identifies WordPress template context for Site Editor and frontend rendering.
 * Determines which template is being viewed/edited: front page, blog, post type archives, singles, taxonomies.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Template_Context
 * Utility class for identifying template contexts and generating storage keys.
 */
class Template_Context {
	/**
	 * Template context types.
	 */
	const CONTEXT_FRONT_PAGE        = 'front_page';
	const CONTEXT_BLOG              = 'blog';
	const CONTEXT_POST_TYPE_ARCHIVE = 'post_type_archive';
	const CONTEXT_SINGLE_POST_TYPE  = 'single_post_type';
	const CONTEXT_TAXONOMY          = 'taxonomy';
	const CONTEXT_SEARCH            = 'search';
	const CONTEXT_404               = '404';
	const CONTEXT_ATTACHMENT        = 'attachment';
	const CONTEXT_AUTHOR            = 'author';
	const CONTEXT_DATE              = 'date';
	const CONTEXT_UNKNOWN           = 'unknown';
	const CONTEXT_DEFAULT           = 'default';
	const CONTEXT_DEFAULT_SITE      = 'default_site';
	const CONTEXT_DEFAULT_SINGULAR  = 'default_singular';
	const CONTEXT_DEFAULT_ARCHIVE   = 'default_archive';

	/**
	 * Get the current template context.
	 *
	 * @return array {
	 *     Template context information.
	 *
	 *     @type string $type       Context type constant.
	 *     @type string $key        Storage key for this template.
	 *     @type string $post_type  Post type slug (for archive/single contexts).
	 *     @type string $taxonomy   Taxonomy slug (for taxonomy contexts).
	 *     @type string $label      Human-readable label for UI.
	 * }
	 */
	public static function get_current_context() {
		// do_action('qm/debug', 'get_current_context');
		// Site Editor context - check if we're editing a template.
		if ( self::is_site_editor() ) {
			$context = self::get_site_editor_context();
			// do_action('qm/debug', 'Site Editor context: ' . print_r( $context, true ) );
			return $context;
		}

		// Frontend context - check the current query.
		$context = self::get_frontend_context();
		// do_action('qm/debug', 'Template context: ' . print_r( $context, true ) );
		return $context;
	}

	/**
	 * Check if we're in the Site Editor.
	 *
	 * @return bool
	 */
	private static function is_site_editor() {
		global $pagenow;
		return 'site-editor.php' === $pagenow || ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_GET['postType'] ) && 'wp_template' === $_GET['postType'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get template context in Site Editor.
	 *
	 * @return array Template context array.
	 */
	private static function get_site_editor_context() {
		// Try to get the template being edited from REST request or query params.
		$template_id = null;

		// Check REST request body for template ID.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$request_body = file_get_contents( 'php://input' );
			if ( $request_body ) {
				$data = json_decode( $request_body, true );
				if ( isset( $data['id'] ) ) {
					$template_id = $data['id'];
				}
			}
		}

		// Check query parameters.
		if ( ! $template_id && isset( $_GET['postId'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$template_id = sanitize_text_field( wp_unslash( $_GET['postId'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// If we have a template ID, parse it to determine context.
		if ( $template_id ) {
			return self::parse_template_id( $template_id );
		}

		// Default unknown context.
		return array(
			'type'      => self::CONTEXT_UNKNOWN,
			'key'       => 'unknown',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => __( 'Unknown Template', 'prc-schema-seo' ),
		);
	}

	/**
	 * Get template context on frontend.
	 *
	 * Detection order follows WordPress template hierarchy precedence.
	 * More specific contexts are checked before less specific ones.
	 *
	 * @return array Template context array.
	 */
	private static function get_frontend_context() {
		// do_action('qm/debug', 'get_frontend_context');
		// 404 error page - check first as it's a distinct error state.
		if ( is_404() ) {
			// do_action('qm/debug', 'is_404()');
			return array(
				'type'      => self::CONTEXT_404,
				'key'       => '404',
				'post_type' => '',
				'taxonomy'  => '',
				'label'     => __( '404 Error Page', 'prc-schema-seo' ),
			);
		}

		// Search results page.
		if ( is_search() ) {
			// do_action('qm/debug', 'is_search()');
			return array(
				'type'      => self::CONTEXT_SEARCH,
				'key'       => 'search',
				'post_type' => '',
				'taxonomy'  => '',
				'label'     => __( 'Search Results', 'prc-schema-seo' ),
			);
		}

		// Front page - check BEFORE is_home() to properly handle static front page.
		// When a static page is set as front page, is_front_page() returns true.
		// When "Your latest posts" is set, both is_front_page() AND is_home() return true.
		if ( is_front_page() && ! is_home() ) {
			// Static page set as front page.
			// do_action('qm/debug', 'is_front_page() && ! is_home()');
			return array(
				'type'      => self::CONTEXT_FRONT_PAGE,
				'key'       => 'front_page',
				'post_type' => '',
				'taxonomy'  => '',
				'label'     => __( 'Front Page', 'prc-schema-seo' ),
			);
		}

		// Blog page (posts page) - handles both:
		// 1. "Your latest posts" as front page (is_front_page() && is_home())
		// 2. Separate blog page when static front page is set (is_home() && !is_front_page())
		if ( \PRC\BlockUtils\is_publications() ) {
			// do_action('qm/debug', 'is_publications()');
			return array(
				'type'      => self::CONTEXT_BLOG,
				'key'       => 'blog',
				'post_type' => 'post',
				'taxonomy'  => '',
				'label'     => __( 'Blog', 'prc-schema-seo' ),
			);
		}

		// Attachment pages - check before is_singular() since attachments are also singular.
		if ( is_attachment() ) {
			// do_action('qm/debug', 'is_attachment()');
			return array(
				'type'      => self::CONTEXT_ATTACHMENT,
				'key'       => 'attachment',
				'post_type' => 'attachment',
				'taxonomy'  => '',
				'label'     => __( 'Attachment', 'prc-schema-seo' ),
			);
		}

		// Author archive.
		if ( is_author() ) {
			// do_action('qm/debug', 'is_author()');
			return array(
				'type'      => self::CONTEXT_AUTHOR,
				'key'       => 'author',
				'post_type' => '',
				'taxonomy'  => '',
				'label'     => __( 'Author Archive', 'prc-schema-seo' ),
			);
		}

		// Date archive.
		if ( is_date() ) {
			// do_action('qm/debug', 'is_date()');
			return array(
				'type'      => self::CONTEXT_DATE,
				'key'       => 'date',
				'post_type' => '',
				'taxonomy'  => '',
				'label'     => __( 'Date Archive', 'prc-schema-seo' ),
			);
		}

		// Post type archive.
		if ( is_post_type_archive() ) {
			// do_action('qm/debug', 'is_post_type_archive()');
			$post_type     = get_query_var( 'post_type' );
			$post_type     = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_obj = get_post_type_object( $post_type );
			return array(
				'type'      => self::CONTEXT_POST_TYPE_ARCHIVE,
				'key'       => 'post_type_archive_' . $post_type,
				'post_type' => $post_type,
				'taxonomy'  => '',
				'label'     => sprintf(
					/* translators: %s: post type label */
					__( '%s Archive', 'prc-schema-seo' ),
					$post_type_obj ? $post_type_obj->labels->name : $post_type
				),
			);
		}

		// Taxonomy archive.
		if ( is_category() || is_tag() || is_tax() ) {
			// do_action('qm/debug', 'is_category() || is_tag() || is_tax()');
			$term = get_queried_object();
			if ( $term && isset( $term->taxonomy ) ) {
				$taxonomy_obj = get_taxonomy( $term->taxonomy );
				return array(
					'type'      => self::CONTEXT_TAXONOMY,
					'key'       => 'taxonomy_' . $term->taxonomy,
					'post_type' => '',
					'taxonomy'  => $term->taxonomy,
					'label'     => sprintf(
						/* translators: %s: taxonomy label */
						__( '%s Archive', 'prc-schema-seo' ),
						$taxonomy_obj ? $taxonomy_obj->labels->name : $term->taxonomy
					),
				);
			}
		}

		// Single post/page/CPT - check after all special singular types.
		if ( is_singular() ) {
			// do_action('qm/debug', 'is_singular()');
			$post_type     = get_post_type();
			$post_type_obj = get_post_type_object( $post_type );
			return array(
				'type'      => self::CONTEXT_SINGLE_POST_TYPE,
				'key'       => 'single_' . $post_type,
				'post_type' => $post_type,
				'taxonomy'  => '',
				'label'     => sprintf(
					/* translators: %s: post type label */
					__( 'Single %s', 'prc-schema-seo' ),
					$post_type_obj ? $post_type_obj->labels->singular_name : $post_type
				),
			);
		}

		// Unknown fallback.
		// do_action('qm/debug', 'Unknown fallback');
		return array(
			'type'      => self::CONTEXT_UNKNOWN,
			'key'       => 'unknown',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => __( 'Unknown Context', 'prc-schema-seo' ),
		);
	}

	/**
	 * Parse a wp_template ID to determine context.
	 *
	 * WordPress template IDs follow patterns like:
	 * - theme//front-page
	 * - theme//home
	 * - theme//archive-{post_type}
	 * - theme//single-{post_type}
	 * - theme//taxonomy-{taxonomy}
	 * - theme//404
	 * - theme//search
	 * - theme//attachment
	 * - theme//author
	 * - theme//date
	 *
	 * @param string $template_id Template ID string.
	 * @return array Template context array.
	 */
	private static function parse_template_id( $template_id ) {
		// Extract slug from template ID (format: theme//slug).
		$parts = explode( '//', $template_id );
		$slug  = isset( $parts[1] ) ? $parts[1] : $template_id;

		// 404 error template.
		if ( '404' === $slug ) {
			return array(
				'type'      => self::CONTEXT_404,
				'key'       => '404',
				'post_type' => '',
				'taxonomy'  => '',
				'label'     => __( '404 Error Page', 'prc-schema-seo' ),
			);
		}

		// Search results template.
		if ( 'search' === $slug ) {
			return array(
				'type'      => self::CONTEXT_SEARCH,
				'key'       => 'search',
				'post_type' => '',
				'taxonomy'  => '',
				'label'     => __( 'Search Results', 'prc-schema-seo' ),
			);
		}

		// Front page template.
		if ( 'front-page' === $slug || 'frontpage' === $slug ) {
			return array(
				'type'      => self::CONTEXT_FRONT_PAGE,
				'key'       => 'front_page',
				'post_type' => '',
				'taxonomy'  => '',
				'label'     => __( 'Front Page', 'prc-schema-seo' ),
			);
		}

		// Blog/home template.
		if ( 'home' === $slug || 'index' === $slug ) {
			return array(
				'type'      => self::CONTEXT_BLOG,
				'key'       => 'blog',
				'post_type' => 'post',
				'taxonomy'  => '',
				'label'     => __( 'Blog', 'prc-schema-seo' ),
			);
		}

		// Attachment template.
		if ( 'attachment' === $slug ) {
			return array(
				'type'      => self::CONTEXT_ATTACHMENT,
				'key'       => 'attachment',
				'post_type' => 'attachment',
				'taxonomy'  => '',
				'label'     => __( 'Attachment', 'prc-schema-seo' ),
			);
		}

		// Author archive template.
		if ( 'author' === $slug ) {
			return array(
				'type'      => self::CONTEXT_AUTHOR,
				'key'       => 'author',
				'post_type' => '',
				'taxonomy'  => '',
				'label'     => __( 'Author Archive', 'prc-schema-seo' ),
			);
		}

		// Date archive template.
		if ( 'date' === $slug ) {
			return array(
				'type'      => self::CONTEXT_DATE,
				'key'       => 'date',
				'post_type' => '',
				'taxonomy'  => '',
				'label'     => __( 'Date Archive', 'prc-schema-seo' ),
			);
		}

		// Generic archive template.
		if ( 'archive' === $slug ) {
			return array(
				'type'      => self::CONTEXT_POST_TYPE_ARCHIVE,
				'key'       => 'post_type_archive_post',
				'post_type' => 'post',
				'taxonomy'  => '',
				'label'     => __( 'Archive', 'prc-schema-seo' ),
			);
		}

		// Post type archive template.
		if ( preg_match( '/^archive-(.+)$/', $slug, $matches ) ) {
			$post_type     = $matches[1];
			$post_type_obj = get_post_type_object( $post_type );
			return array(
				'type'      => self::CONTEXT_POST_TYPE_ARCHIVE,
				'key'       => 'post_type_archive_' . $post_type,
				'post_type' => $post_type,
				'taxonomy'  => '',
				'label'     => sprintf(
					/* translators: %s: post type label */
					__( '%s Archive', 'prc-schema-seo' ),
					$post_type_obj ? $post_type_obj->labels->name : $post_type
				),
			);
		}

		// Single post type template.
		if ( preg_match( '/^single-(.+)$/', $slug, $matches ) ) {
			$post_type     = $matches[1];
			$post_type_obj = get_post_type_object( $post_type );
			return array(
				'type'      => self::CONTEXT_SINGLE_POST_TYPE,
				'key'       => 'single_' . $post_type,
				'post_type' => $post_type,
				'taxonomy'  => '',
				'label'     => sprintf(
					/* translators: %s: post type label */
					__( 'Single %s', 'prc-schema-seo' ),
					$post_type_obj ? $post_type_obj->labels->singular_name : $post_type
				),
			);
		}

		// Generic single template.
		if ( 'single' === $slug ) {
			return array(
				'type'      => self::CONTEXT_SINGLE_POST_TYPE,
				'key'       => 'single_post',
				'post_type' => 'post',
				'taxonomy'  => '',
				'label'     => __( 'Single Post', 'prc-schema-seo' ),
			);
		}

		// Page template.
		if ( 'page' === $slug ) {
			return array(
				'type'      => self::CONTEXT_SINGLE_POST_TYPE,
				'key'       => 'single_page',
				'post_type' => 'page',
				'taxonomy'  => '',
				'label'     => __( 'Single Page', 'prc-schema-seo' ),
			);
		}

		// Taxonomy archive template.
		if ( preg_match( '/^taxonomy-(.+)$/', $slug, $matches ) ) {
			$taxonomy     = $matches[1];
			$taxonomy_obj = get_taxonomy( $taxonomy );
			return array(
				'type'      => self::CONTEXT_TAXONOMY,
				'key'       => 'taxonomy_' . $taxonomy,
				'post_type' => '',
				'taxonomy'  => $taxonomy,
				'label'     => sprintf(
					/* translators: %s: taxonomy label */
					__( '%s Archive', 'prc-schema-seo' ),
					$taxonomy_obj ? $taxonomy_obj->labels->name : $taxonomy
				),
			);
		}

		// Category template.
		if ( 'category' === $slug ) {
			return array(
				'type'      => self::CONTEXT_TAXONOMY,
				'key'       => 'taxonomy_category',
				'post_type' => '',
				'taxonomy'  => 'category',
				'label'     => __( 'Category Archive', 'prc-schema-seo' ),
			);
		}

		// Tag template.
		if ( 'tag' === $slug ) {
			return array(
				'type'      => self::CONTEXT_TAXONOMY,
				'key'       => 'taxonomy_post_tag',
				'post_type' => '',
				'taxonomy'  => 'post_tag',
				'label'     => __( 'Tag Archive', 'prc-schema-seo' ),
			);
		}

		// Unknown template.
		return array(
			'type'      => self::CONTEXT_UNKNOWN,
			'key'       => 'unknown',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => sprintf(
				/* translators: %s: template slug */
				__( 'Template: %s', 'prc-schema-seo' ),
				$slug
			),
		);
	}

	/**
	 * Get storage key for a template context.
	 *
	 * @deprecated No longer used - templates stored in single prc_schema_seo_templates option.
	 * @param array $context Context array from get_current_context().
	 * @return string Storage key for wp_options.
	 */
	public static function get_storage_key( $context ) {
		_deprecated_function( __METHOD__, '1.0.0', 'Use context[\'key\'] directly with prc_schema_seo_templates option' );
		if ( ! isset( $context['key'] ) ) {
			return '';
		}
		return 'prc_schema_seo_template_' . $context['key'];
	}

	/**
	 * Get default fallback context.
	 *
	 * @deprecated 1.0.0 Use get_default_site_context() for site-wide defaults,
	 *                   get_default_singular_context() for single post/page defaults,
	 *                   or get_default_archive_context() for archive defaults.
	 * @return array Default context array (aliases get_default_site_context()).
	 */
	public static function get_default_context() {
		_deprecated_function( __METHOD__, '1.0.0', 'Template_Context::get_default_site_context()' );
		return self::get_default_site_context();
	}

	/**
	 * Get site-wide default fallback context.
	 *
	 * @return array Site-wide default context array.
	 */
	public static function get_default_site_context() {
		return array(
			'type'      => self::CONTEXT_DEFAULT_SITE,
			'key'       => 'default_site',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => __( 'Site-wide Default', 'prc-schema-seo' ),
		);
	}

	/**
	 * Get singular default fallback context.
	 *
	 * @return array Singular default context array.
	 */
	public static function get_default_singular_context() {
		return array(
			'type'      => self::CONTEXT_DEFAULT_SINGULAR,
			'key'       => 'default_singular',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => __( 'Singular Default', 'prc-schema-seo' ),
		);
	}

	/**
	 * Get archive default fallback context.
	 *
	 * @return array Archive default context array.
	 */
	public static function get_default_archive_context() {
		return array(
			'type'      => self::CONTEXT_DEFAULT_ARCHIVE,
			'key'       => 'default_archive',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => __( 'Archive Default', 'prc-schema-seo' ),
		);
	}

	/**
	 * Get all available template contexts for settings UI.
	 *
	 * @return array Array of context arrays.
	 */
	public static function get_all_template_contexts() {
		$contexts = array();

		// Three-tier default fallback contexts (appear first for UI grouping).
		$contexts[] = self::get_default_site_context();
		$contexts[] = self::get_default_singular_context();
		$contexts[] = self::get_default_archive_context();

		// Special templates - order matters for UI presentation.
		$contexts[] = array(
			'type'      => self::CONTEXT_FRONT_PAGE,
			'key'       => 'front_page',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => __( 'Front Page', 'prc-schema-seo' ),
		);

		$contexts[] = array(
			'type'      => self::CONTEXT_BLOG,
			'key'       => 'blog',
			'post_type' => 'post',
			'taxonomy'  => '',
			'label'     => __( 'Blog', 'prc-schema-seo' ),
		);

		$contexts[] = array(
			'type'      => self::CONTEXT_SEARCH,
			'key'       => 'search',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => __( 'Search Results', 'prc-schema-seo' ),
		);

		$contexts[] = array(
			'type'      => self::CONTEXT_404,
			'key'       => '404',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => __( '404 Error Page', 'prc-schema-seo' ),
		);

		$contexts[] = array(
			'type'      => self::CONTEXT_ATTACHMENT,
			'key'       => 'attachment',
			'post_type' => 'attachment',
			'taxonomy'  => '',
			'label'     => __( 'Attachment', 'prc-schema-seo' ),
		);

		$contexts[] = array(
			'type'      => self::CONTEXT_AUTHOR,
			'key'       => 'author',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => __( 'Author Archive', 'prc-schema-seo' ),
		);

		$contexts[] = array(
			'type'      => self::CONTEXT_DATE,
			'key'       => 'date',
			'post_type' => '',
			'taxonomy'  => '',
			'label'     => __( 'Date Archive', 'prc-schema-seo' ),
		);

		// All public post types (archives and singles).
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $post_type ) {
			// Skip attachment since it's handled above as a special template.
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			if ( $post_type->has_archive ) {
				$contexts[] = array(
					'type'      => self::CONTEXT_POST_TYPE_ARCHIVE,
					'key'       => 'post_type_archive_' . $post_type->name,
					'post_type' => $post_type->name,
					'taxonomy'  => '',
					'label'     => sprintf(
						/* translators: %s: post type label */
						__( '%s Archive', 'prc-schema-seo' ),
						$post_type->labels->name
					),
				);
			}

			$contexts[] = array(
				'type'      => self::CONTEXT_SINGLE_POST_TYPE,
				'key'       => 'single_' . $post_type->name,
				'post_type' => $post_type->name,
				'taxonomy'  => '',
				'label'     => sprintf(
					/* translators: %s: post type label */
					__( 'Single %s', 'prc-schema-seo' ),
					$post_type->labels->singular_name
				),
			);
		}

		// All public taxonomies.
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			$contexts[] = array(
				'type'      => self::CONTEXT_TAXONOMY,
				'key'       => 'taxonomy_' . $taxonomy->name,
				'post_type' => '',
				'taxonomy'  => $taxonomy->name,
				'label'     => sprintf(
					/* translators: %s: taxonomy label */
					__( '%s Archive', 'prc-schema-seo' ),
					$taxonomy->labels->name
				),
			);
		}

		return apply_filters( 'prc_schema_seo_template_contexts', $contexts );
	}
}
