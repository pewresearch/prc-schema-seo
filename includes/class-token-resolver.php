<?php
/**
 * Token_Resolver class.
 *
 * Static utility for resolving SEO pattern tokens (%token%) to actual values.
 * Used by template-level resolution (Template_Defaults, Meta_Tags) and
 * post-level resolution (Metadata::resolve_for_display).
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Token_Resolver
 *
 * Consolidates token definitions from Template_Defaults and Meta_Tags
 * into a single source of truth. Supports singular, archive, and search contexts.
 */
class Token_Resolver {

	/**
	 * Build the token-to-value map for the given context.
	 *
	 * @param int   $post_id Post ID (0 for archive/non-singular contexts).
	 * @param array $context Optional context from Template_Context::get_current_context().
	 * @param array $overrides Optional overrides (e.g. %post_title% => resolved SEO title).
	 * @return array Token => value map.
	 */
	public static function build_token_map( int $post_id = 0, array $context = array(), array $overrides = array() ): array {
		$site_name    = get_bloginfo( 'name' );
		$site_tagline = get_bloginfo( 'description' );
		$separator    = apply_filters( 'prc_schema_seo_title_separator', ' | ' );

		$tokens = array(
			'%sep%'          => $separator,
			'%site_name%'    => $site_name,
			'%site_tagline%' => $site_tagline,
			'%year%'         => gmdate( 'Y' ),
			'%search_query%' => is_search() ? get_search_query() : '',
		);

		// Page tokens (archives and paginated singular).
		$page_tokens = self::get_page_tokens( $post_id );
		$tokens      = array_merge( $tokens, $page_tokens );

		// Singular post context.
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$existing_seo = get_post_meta( $post_id, '_prc_seo_data', true );
				$seo_title    = isset( $existing_seo['title'] ) ? $existing_seo['title'] : '';
				$seo_desc     = isset( $existing_seo['description'] ) ? $existing_seo['description'] : '';
				$title        = $seo_title ? $seo_title : $post->post_title;
				$description  = $seo_desc ? $seo_desc : $post->post_excerpt;

				$primary_cat = Primary_Term::get_name( $post_id, 'category', true );
				$categories  = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'names' ) );
				$tags        = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
				$author_name = get_the_author_meta( 'display_name', $post->post_author );
				$post_type   = $post->post_type;
				$post_date   = gmdate( get_option( 'date_format' ), strtotime( $post->post_date_gmt ) );
				$year        = gmdate( 'Y', strtotime( $post->post_date_gmt ) );
				$post_url    = get_permalink( $post_id );

				$object_title       = $seo_title ? $seo_title : self::resolve_object_title( $post_id );
				$object_type        = isset( $existing_seo['type'] ) ? $existing_seo['type'] : self::resolve_object_type( $post_id );
				$object_description = $seo_desc ? $seo_desc : self::resolve_object_description( $post_id );

				$singular_tokens = array(
					'%post_title%'         => $title,
					'%post_excerpt%'       => $description,
					'%post_type%'          => $post_type,
					'%post_date%'          => $post_date,
					'%post_url%'           => $post_url,
					'%author%'             => $author_name,
					'%primary_category%'   => $primary_cat,
					'%categories%'         => ! empty( $categories ) ? implode( ', ', $categories ) : '',
					'%tags%'               => ! empty( $tags ) ? implode( ', ', $tags ) : '',
					'%object_title%'       => $object_title,
					'%object_type%'        => $object_type,
					'%object_description%' => $object_description,
					'%year%'               => $year,
				);
				$tokens          = array_merge( $tokens, $singular_tokens );
			}
		}

		// Archive context tokens.
		$archive_tokens = self::get_archive_tokens( $context );
		$tokens         = array_merge( $tokens, $archive_tokens );

		// Apply overrides (e.g. template-level %post_title% with resolved SEO title).
		$tokens = array_merge( $tokens, $overrides );

		$tokens = apply_filters( 'prc_schema_seo_pattern_tokens', $tokens, $post_id );

		return $tokens;
	}

	/**
	 * Resolve a pattern string by replacing tokens with values.
	 *
	 * @param string $pattern   Pattern with %token% placeholders.
	 * @param int    $post_id   Post ID (0 for archive).
	 * @param array  $context   Optional context array.
	 * @param array  $overrides Optional token overrides.
	 * @param bool   $use_raw_post_title If true, %post_title% resolves to raw WP title (for post-level resolution).
	 * @return string Resolved string.
	 */
	public static function resolve( string $pattern, int $post_id = 0, array $context = array(), array $overrides = array(), bool $use_raw_post_title = false ): string {
		if ( empty( $pattern ) ) {
			return '';
		}

		if ( $use_raw_post_title && $post_id ) {
			$post                      = get_post( $post_id );
			$overrides['%post_title%'] = $post ? $post->post_title : '';
		}

		$tokens = self::build_token_map( $post_id, $context, $overrides );
		$value  = strtr( $pattern, $tokens );

		// Dynamic tokens: %primary_term:taxonomy% and %terms:taxonomy%.
		if ( preg_match_all( '/%((primary_term|terms):[a-z0-9_-]+)%/i', $value, $matches ) ) {
			foreach ( $matches[1] as $full ) {
				$parts       = explode( ':', $full, 2 );
				$type        = $parts[0] ?? '';
				$taxonomy    = $parts[1] ?? '';
				$replacement = '';
				if ( 'primary_term' === $type && $post_id ) {
					$replacement = Primary_Term::get_name( $post_id, $taxonomy, true );
				} elseif ( 'terms' === $type && $post_id ) {
					$names       = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
					$replacement = ! empty( $names ) ? implode( ', ', $names ) : '';
				}
				$value = str_replace( '%' . $full . '%', $replacement, $value );
			}
		}

		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}

	/**
	 * Get page-related tokens (%page%, %page_number%, %page_total%).
	 *
	 * Resolves to empty string on non-paginated pages (no "Page 1 of 1").
	 *
	 * @param int $post_id Post ID for singular multipage, 0 for archives.
	 * @return array Token => value.
	 */
	private static function get_page_tokens( int $post_id = 0 ): array {
		$page_num   = 1;
		$page_total = 1;

		// Archive pagination.
		if ( ! $post_id && is_main_query() ) {
			global $wp_query;
			$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
			if ( $paged > 1 && $wp_query && isset( $wp_query->max_num_pages ) ) {
				$page_num   = (int) $paged;
				$page_total = (int) $wp_query->max_num_pages;
			}
		}

		// Singular multipage (<!--nextpage-->).
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post && str_contains( $post->post_content, '<!--nextpage-->' ) ) {
				$page_total = substr_count( $post->post_content, '<!--nextpage-->' ) + 1;
				$page_var   = get_query_var( 'page' );
				$page_num   = $page_var ? (int) $page_var : 1;
			}
		}

		$page_str = '';
		if ( $page_num > 1 && $page_total > 1 ) {
			$page_str = sprintf(
				/* translators: 1: current page number, 2: total pages */
				__( 'Page %1$d of %2$d', 'prc-schema-seo' ),
				$page_num,
				$page_total
			);
		}

		return array(
			'%page%'        => $page_str,
			'%page_number%' => $page_num > 1 ? (string) $page_num : '',
			'%page_total%'  => $page_total > 1 ? (string) $page_total : '',
		);
	}

	/**
	 * Get archive-specific tokens (term, post type, etc.).
	 *
	 * Uses WordPress conditional tags; $context is available for future CLI/Site Editor use.
	 *
	 * @param array $context Context from Template_Context (reserved for non-request contexts).
	 * @return array Token => value.
	 */
	private static function get_archive_tokens( array $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Reserved for CLI/Site Editor.
		$tokens = array();

		if ( is_home() ) {
			$tokens['%object_title%'] = 'Publications';
		}

		if ( is_post_type_archive() ) {
			$post_type                      = get_query_var( 'post_type' );
			$post_type                      = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_obj                  = get_post_type_object( $post_type );
			$tokens['%post_type%']          = $post_type;
			$tokens['%object_title%']       = $post_type_obj ? $post_type_obj->labels->name : $post_type;
			$tokens['%object_type%']        = $post_type_obj ? $post_type_obj->labels->singular_name : $post_type;
			$tokens['%object_description%'] = $post_type_obj && ! empty( $post_type_obj->description )
				? wp_strip_all_tags( $post_type_obj->description )
				: get_bloginfo( 'description' );
		}

		$term = get_queried_object();
		if ( $term instanceof \WP_Term ) {
			$taxonomy_obj                 = get_taxonomy( $term->taxonomy );
			$tokens['%term_name%']        = html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$tokens['%term_description%'] = html_entity_decode( wp_strip_all_tags( term_description( $term->term_id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$tokens['%taxonomy%']         = $term->taxonomy;
			$tokens['%taxonomy_label%']   = $taxonomy_obj ? $taxonomy_obj->labels->singular_name : $term->taxonomy;
			$tokens['%object_title%']     = html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		if ( is_search() ) {
			$tokens['%object_title%'] = sprintf(
				/* translators: %s: search query */
				__( 'Search results for: %s', 'prc-schema-seo' ),
				get_search_query()
			);
		}

		if ( is_date() ) {
			$tokens['%date%']         = get_the_date();
			$tokens['%object_title%'] = get_the_archive_title();
		}

		return $tokens;
	}

	/**
	 * Resolve object title for the current context.
	 *
	 * Canonical implementation used by both build_token_map() and Template_Defaults.
	 *
	 * @param int $post_id Post ID (0 for archive/non-singular contexts).
	 * @return string
	 */
	public static function resolve_object_title( int $post_id = 0 ): string {
		if ( is_singular() ) {
			if ( $post_id ) {
				return get_the_title( $post_id );
			}
			global $post;
			if ( $post ) {
				return get_the_title( $post->ID );
			}
			return '';
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->name ) ) {
				return $term->name;
			}
			return '';
		}
		if ( is_post_type_archive() ) {
			$post_type     = get_query_var( 'post_type' );
			$post_type     = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj && isset( $post_type_obj->labels->name ) ) {
				return $post_type_obj->labels->name;
			}
			return '';
		}
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
	 * Resolve object type for the current context.
	 *
	 * Canonical implementation used by both build_token_map() and Template_Defaults.
	 *
	 * @param int $post_id Post ID (0 for archive/non-singular contexts).
	 * @return string
	 */
	public static function resolve_object_type( int $post_id = 0 ): string {
		if ( is_singular() ) {
			if ( $post_id ) {
				$post_type = get_post_type_object( get_post_type( $post_id ) );
				return $post_type ? $post_type->labels->singular_name : '';
			}
			global $post;
			if ( $post ) {
				$post_type = get_post_type_object( get_post_type( $post->ID ) );
				return $post_type ? $post_type->labels->singular_name : '';
			}
			return '';
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->taxonomy ) ) {
				$taxonomy = get_taxonomy( $term->taxonomy );
				return $taxonomy ? $taxonomy->labels->singular_name : '';
			}
			return '';
		}
		if ( is_post_type_archive() ) {
			$post_type     = get_query_var( 'post_type' );
			$post_type     = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_obj = get_post_type_object( $post_type );
			return $post_type_obj ? $post_type_obj->labels->name : '';
		}
		if ( is_home() ) {
			return __( 'Publications', 'prc-schema-seo' );
		}
		return '';
	}

	/**
	 * Resolve object description for the current context.
	 *
	 * Canonical implementation used by both build_token_map() and Template_Defaults.
	 *
	 * @param int $post_id Post ID (0 for archive/non-singular contexts).
	 * @return string
	 */
	public static function resolve_object_description( int $post_id = 0 ): string {
		if ( is_singular() ) {
			$post = $post_id ? get_post( $post_id ) : ( $GLOBALS['post'] ?? null );
			if ( $post ) {
				if ( ! empty( $post->post_excerpt ) ) {
					return $post->post_excerpt;
				}
				if ( ! empty( $post->post_content ) ) {
					return wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '...' );
				}
			}
			return '';
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->description ) && ! empty( $term->description ) ) {
				return $term->description;
			}
			return get_bloginfo( 'description' );
		}
		if ( is_post_type_archive() ) {
			$post_type     = get_query_var( 'post_type' );
			$post_type     = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj && ! empty( $post_type_obj->description ) ) {
				return wp_strip_all_tags( $post_type_obj->description );
			}
		}
		return get_bloginfo( 'description' );
	}

	/**
	 * Check if a string contains PRC-style tokens (%...%).
	 *
	 * @param string $str String to check.
	 * @return bool
	 */
	public static function has_tokens( string $str ): bool {
		return (bool) preg_match( '/%[a-z0-9_:]+%/i', $str );
	}
}
