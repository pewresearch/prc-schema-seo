<?php
/**
 * Parsely_Meta class.
 *
 * Outputs Parsely meta tags (parsely-title, parsely-link, parsely-type, etc.) in the document head
 * for singular posts, term archives, and the front page. Integrates with VIP Parsely (load MU, disable JSON-LD).
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Parsely_Meta
 *
 * Handles building, caching, and rendering Parsely meta tags. Does not depend on Yoast SEO.
 */
class Parsely_Meta {

	/**
	 * Cache group for rendered Parsely meta tags (shares group with Meta_Tags for unified invalidation).
	 */
	const CACHE_GROUP = 'prc_schema_seo_parsely_03112026';

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
		// Priority 3 so it runs after Meta_Tags (priority 2).
		$this->loader->add_action( 'wp_head', $this, 'output_post_parsely_tags', 3 );
		$this->loader->add_action( 'wp_head', $this, 'output_term_parsely_tags', 3 );
		$this->loader->add_action( 'wp_head', $this, 'output_home_parsely_tags', 3 );

		// VIP Parsely integration: enable MU plugin and optionally disable Parsely's JSON-LD to avoid duplication.
		$this->loader->add_filter( 'wpvip_parsely_load_mu', $this, 'enable_parsely_mu_on_vip' );
		$this->loader->add_filter( 'wp_parsely_metadata', $this, 'disable_parsely_json_ld', 10, 3 );
	}

	/**
	 * Enable VIP's WP Parsely integration.
	 *
	 * @return true
	 */
	public function enable_parsely_mu_on_vip() {
		return true;
	}

	/**
	 * Disable Parsely's own JSON-LD output (we output meta tags only).
	 *
	 * @param array  $parsely_metadata The Parsely metadata.
	 * @param object $post             The post object. Unused but required by filter signature.
	 * @param array  $parsely_options  The Parsely options. Unused but required by filter signature.
	 * @return array The Parsely metadata (unchanged; filter allows other code to suppress JSON-LD).
	 */
	public function disable_parsely_json_ld( $parsely_metadata, $post, $parsely_options ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $parsely_metadata;
	}

	/**
	 * Warm Parsely meta tags cache for a post.
	 *
	 * Builds and caches Parsely tags for a given post ID. Used by wp_head output
	 * and by CLI migration to pre-cache after Yoast migration.
	 *
	 * @param int $post_id Post ID.
	 * @return string Parsely meta tags HTML. Empty string if post type unsupported or RLS template.
	 */
	public function warm_post_cache( int $post_id ): string {
		$post_type = get_post_type( $post_id );
		if ( ! post_type_supports( $post_type, 'prc-schema-seo' ) ) {
			return '';
		}
		if ( defined( 'PRC_RLS_TEMPLATE_POST_TYPE' ) && PRC_RLS_TEMPLATE_POST_TYPE === $post_type ) {
			return '';
		}

		$cache_key = 'parsely_tags_' . $post_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->seo_metadata->get_seo_data( $post_id );

		// Parsely wants the raw post title, not the template-wrapped SEO title
		// (e.g. "Post Title" not "Post Title | Pew Research Center").
		$parsely_title = $this->get_parsely_title_post( $post_id, $data );

		$data      = $this->seo_metadata->resolve_for_display( $data, $post_id );
		$canonical = ! empty( $data['canonical_url'] ) ? $data['canonical_url'] : get_permalink( $post_id );
		$canonical = apply_filters( 'prc_schema_seo_canonical_url', $canonical, $post_id, $data );

		$tags = array(
			'parsely-title'     => $parsely_title,
			'parsely-link'      => $canonical,
			'parsely-type'      => $this->get_parsely_type_post( $post_type ),
			'parsely-tags'      => $this->get_parsely_tags( $post_id ),
			'parsely-section'   => $this->get_parsely_section( $post_id ),
			'parsely-pub-date'  => get_the_date( 'c', $post_id ),
			'parsely-image-url' => $this->get_parsely_image_url( $post_id, $post_type ),
			'parsely-author'    => $this->get_parsely_authors( $post_id ),
		);

		$tags = apply_filters( 'prc_schema_seo_parsely_tags', $tags, $post_id, $data );
		$html = $this->build_parsely_html( $tags );
		wp_cache_set( $cache_key, $html, self::CACHE_GROUP, self::CACHE_TTL );

		return $html;
	}

	/**
	 * Output Parsely meta tags for singular posts.
	 *
	 * @return void
	 */
	public function output_post_parsely_tags() {
		if ( ! is_singular() || is_front_page() ) {
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

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in build_parsely_html.
	}

	/**
	 * Warm Parsely meta tags cache for a term archive.
	 *
	 * Builds and caches Parsely tags for a given term. Used by wp_head output
	 * and by CLI migration to pre-cache after Yoast migration.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string Parsely meta tags HTML. Empty string if term not found.
	 */
	public function warm_term_cache( int $term_id, string $taxonomy ): string {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		$cache_key = 'parsely_tags_term_' . $term_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$title = $term->name;
		$link  = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			$link = '';
		}

		$tags = array(
			'parsely-title' => $title,
			'parsely-link'  => $link,
			'parsely-type'  => 'page',
		);
		$tags = apply_filters(
			'prc_schema_seo_parsely_tags',
			$tags,
			0,
			array(
				'context' => 'term',
				'term'    => $term,
			)
		);
		$html = $this->build_parsely_html( $tags );
		wp_cache_set( $cache_key, $html, self::CACHE_GROUP, self::CACHE_TTL );

		return $html;
	}

	/**
	 * Output Parsely meta tags for term archive pages.
	 *
	 * @return void
	 */
	public function output_term_parsely_tags() {
		if ( is_singular() || is_front_page() ) {
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

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in build_parsely_html.
	}

	/**
	 * Output Parsely meta tags for the front page.
	 *
	 * @return void
	 */
	public function output_home_parsely_tags() {
		if ( ! is_front_page() ) {
			return;
		}

		$cache_key = 'parsely_tags_home';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML.
			return;
		}

		$tags = array(
			'parsely-title' => 'Pew Research Center',
			'parsely-link'  => get_bloginfo( 'url' ),
			'parsely-type'  => 'index',
		);
		$tags = apply_filters( 'prc_schema_seo_parsely_tags', $tags, 0, array( 'context' => 'home' ) );
		$html = $this->build_parsely_html( $tags );
		wp_cache_set( $cache_key, $html, self::CACHE_GROUP, self::CACHE_TTL );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in build_parsely_html.
	}

	/**
	 * Get parsely-title for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Resolved SEO data.
	 * @return string
	 */
	private function get_parsely_title_post( $post_id, $data ) {
		if ( ! empty( $data['title'] ) ) {
			return $data['title'];
		}
		return get_the_title( $post_id );
	}

	/**
	 * Get parsely-type for a post (post, page, or index).
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private function get_parsely_type_post( $post_type ) {
		return 'page' === $post_type ? 'page' : 'post';
	}

	/**
	 * Get parsely-tags: category, research-teams, formats slugs plus meta tags (post/parent/report).
	 *
	 * @param int $post_id Post ID.
	 * @return string Comma-separated list, or empty string.
	 */
	private function get_parsely_tags( $post_id ) {
		$is_child  = has_post_parent( $post_id );
		$parent_id = $is_child ? (int) get_post_field( 'post_parent', $post_id ) : $post_id;

		$category_tags      = wp_get_post_terms( $parent_id, 'category' );
		$research_team_tags = wp_get_post_terms( $parent_id, 'research-teams' );
		$format_tags        = wp_get_post_terms( $parent_id, 'formats' );

		$meta_tags = array( $post_id . '__post' );
		if ( $is_child ) {
			$meta_tags[] = $parent_id . '__parent';
		}

		$report_package_is_chapter = function_exists( '\\PRC\\Platform\\Report_Package\\is_chapter_part_of_report_package' )
			&& \PRC\Platform\Report_Package\is_chapter_part_of_report_package( $post_id );
		$report_package_is_report  = function_exists( '\\PRC\\Platform\\Report_Package\\is_report_package' )
			&& \PRC\Platform\Report_Package\is_report_package( $post_id );
		if ( $report_package_is_chapter || $report_package_is_report ) {
			$meta_tags[] = $parent_id . '__report';
		}

		$category_tags = ( is_array( $category_tags ) && ! empty( $category_tags ) && isset( $category_tags[0]->slug ) )
			? array_map(
				function ( $category ) {
					return isset( $category->slug ) ? 'category__' . $category->slug : null;
				},
				$category_tags
			)
			: array();

		$research_team_tags = ( is_array( $research_team_tags ) && ! empty( $research_team_tags ) && isset( $research_team_tags[0]->slug ) )
			? array_map(
				function ( $research_team ) {
					return isset( $research_team->slug ) ? 'team__' . $research_team->slug : null;
				},
				$research_team_tags
			)
			: array();

		$format_tags = ( is_array( $format_tags ) && ! empty( $format_tags ) && isset( $format_tags[0]->slug ) )
			? array_map(
				function ( $format ) {
					return isset( $format->slug ) ? 'format__' . $format->slug : null;
				},
				$format_tags
			)
			: array();

		$all = array_merge( $category_tags, $format_tags, $research_team_tags, $meta_tags );
		$all = array_filter( $all );
		return implode( ',', $all );
	}

	/**
	 * Get parsely-section (primary category name) for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_parsely_section( $post_id ) {
		$is_child  = has_post_parent( $post_id );
		$parent_id = $is_child ? (int) get_post_field( 'post_parent', $post_id ) : $post_id;
		$name      = Primary_Term::get_name( $parent_id, 'category', false );
		return is_string( $name ) ? $name : '';
	}

	/**
	 * Get parsely-image-url for a post (post thumbnail medium size).
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return string
	 */
	private function get_parsely_image_url( $post_id, $post_type ) {
		if ( ! post_type_supports( $post_type, 'thumbnail' ) ) {
			return '';
		}
		$url = get_the_post_thumbnail_url( $post_id, 'medium' );
		return $url ? $url : '';
	}

	/**
	 * Get parsely-author names (bylines or post author fallback).
	 *
	 * @param int $post_id Post ID.
	 * @return array List of author names, or empty array.
	 */
	private function get_parsely_authors( $post_id ) {
		$is_child  = has_post_parent( $post_id );
		$parent_id = $is_child ? (int) get_post_field( 'post_parent', $post_id ) : $post_id;

		if ( class_exists( '\PRC\Platform\Staff_Bylines\Bylines' ) ) {
			$bylines_instance = new \PRC\Platform\Staff_Bylines\Bylines( $parent_id );
			$bylines          = $bylines_instance->get();
			if ( ! empty( $bylines ) && ! is_wp_error( $bylines ) ) {
				$names = array();
				foreach ( $bylines as $byline ) {
					if ( ! empty( $byline['name'] ) ) {
						$names[] = $byline['name'];
					}
				}
				if ( ! empty( $names ) ) {
					return $names;
				}
			}
		}

		// Fallback: bylines post meta (raw term names) as in original implementation.
		$bylines_data = get_post_meta( $parent_id, 'bylines', true );
		if ( ! empty( $bylines_data ) && is_array( $bylines_data ) ) {
			$names = array();
			foreach ( $bylines_data as $item ) {
				if ( ! empty( $item['termId'] ) ) {
					$term = get_term( (int) $item['termId'], 'bylines' );
					if ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) {
						$names[] = $term->name;
					}
				}
			}
			if ( ! empty( $names ) ) {
				return $names;
			}
		}

		// Fallback to post author.
		$author_id = get_post_field( 'post_author', $parent_id );
		if ( $author_id ) {
			$name = get_the_author_meta( 'display_name', $author_id );
			if ( $name ) {
				return array( $name );
			}
		}
		return array();
	}

	/**
	 * Build HTML for Parsely meta tags.
	 *
	 * @param array $tags Associative array of parsely tag name => value(s). Value may be string or array (e.g. parsely-author).
	 * @return string HTML string.
	 */
	private function build_parsely_html( $tags ) {
		$lines = array();
		foreach ( $tags as $name => $value ) {
			if ( empty( $value ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				foreach ( $value as $single ) {
					if ( '' !== $single ) {
						$lines[] = sprintf( '<meta name="%s" content="%s" />', esc_attr( $name ), esc_attr( $single ) );
					}
				}
				continue;
			}
			$lines[] = sprintf( '<meta name="%s" content="%s" />', esc_attr( $name ), esc_attr( $value ) );
		}
		if ( empty( $lines ) ) {
			return '';
		}
		return PHP_EOL . implode( PHP_EOL, $lines ) . PHP_EOL;
	}
}
