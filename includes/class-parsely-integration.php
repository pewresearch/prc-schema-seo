<?php
/**
 * Parsely_Integration class.
 *
 * Wires PRC SEO data into the official wp-parsely metadata pipeline via filters.
 * JSON-LD vs repeated meta tags follows the Parse.ly plugin settings in WP Admin.
 *
 * On the front page, term archives, and RLS template singular requests, PRC
 * emits its own parsely-* meta tags from wp_head priority 3 and suppresses
 * wp-parsely's renderer by returning an empty array from the per-request
 * `wp_parsely_metadata` filter. The renderer bails at its
 * `! isset( $metadata['headline'] )` guard, avoiding duplicate
 * <meta name="parsely-*"> output (notably parsely-title) when wp-parsely is
 * configured for `repeated_metas`.
 *
 * @package PRC\Platform\Schema_SEO
 */

declare(strict_types=1);

namespace PRC\Platform\Schema_SEO;

/**
 * Parsely_Integration
 */
class Parsely_Integration {

	/**
	 * Cache group for home/term Parsely output (matches former Parsely_Meta group for invalidation consistency).
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
	 * Initializes hooks for this class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->loader->add_filter( 'wpvip_parsely_load_mu', $this, 'enable_parsely_mu_on_local' );
		$this->loader->add_filter( 'wp_parsely_metadata', $this, 'filter_wp_parsely_metadata', 10, 3 );
		$this->loader->add_filter( 'wp_parsely_permalink', $this, 'filter_wp_parsely_permalink', 10, 3 );

		// PRC owns parsely-* output on the front page and term archives.
		// wp-parsely's own renderer is suppressed for these contexts via
		// filter_wp_parsely_metadata() returning an empty array (see notes there).
		$this->loader->add_action( 'wp_head', $this, 'output_term_parsely_tags', 3 );
		$this->loader->add_action( 'wp_head', $this, 'output_home_parsely_tags', 3 );
	}

	/**
	 * Enable VIP's WP Parsely integration on local environments.
	 *
	 * @return bool
	 */
	public function enable_parsely_mu_on_local( $load_mu ) {
		if ( 'local' !== wp_get_environment_type() ) {
			return $load_mu;
		}
		return true;
	}

	/**
	 * Map PRC SEO and platform data onto wp-parsely's metadata array.
	 *
	 * On contexts where PRC emits its own parsely-* tags (front page, term
	 * archives, RLS template post type), this returns an empty array. That
	 * causes wp-parsely's Metadata_Renderer::render_metadata_on_head() to
	 * bail at its `! isset( $metadata['headline'] )` guard, suppressing
	 * duplicate <meta name="parsely-*"> output regardless of whether the
	 * site is configured for `json_ld` or `repeated_metas`.
	 *
	 * Note: wp-parsely evaluates `wp_parsely_should_insert_metadata` exactly
	 * once at plugin init (in Metadata_Renderer::run()) — before `wp` has
	 * fired and before conditional tags work. Gating per-request via
	 * `wp_parsely_metadata` is the only reliable suppression path.
	 *
	 * @param array    $metadata        Parsely metadata.
	 * @param \WP_Post $post            Post object.
	 * @param array    $parsely_options Plugin options.
	 * @return array
	 */
	public function filter_wp_parsely_metadata( $metadata, $post, $parsely_options ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_array( $metadata ) ) {
			return $metadata;
		}

		// Suppress wp-parsely's render on contexts where PRC emits its own tags.
		if ( did_action( 'wp' ) ) {
			if ( is_front_page() || is_category() || is_tag() || is_tax() ) {
				return array();
			}
			if ( is_singular() && defined( 'PRC_RLS_TEMPLATE_POST_TYPE' ) ) {
				$queried = get_queried_object();
				if ( $queried instanceof \WP_Post && PRC_RLS_TEMPLATE_POST_TYPE === $queried->post_type ) {
					return array();
				}
			}
		}

		if ( ! $post instanceof \WP_Post ) {
			return $metadata;
		}

		$post_id   = (int) $post->ID;
		$post_type = $post->post_type;

		if ( ! post_type_supports( $post_type, 'prc-schema-seo' ) ) {
			return $metadata;
		}
		if ( defined( 'PRC_RLS_TEMPLATE_POST_TYPE' ) && PRC_RLS_TEMPLATE_POST_TYPE === $post_type ) {
			return array();
		}

		$data = $this->seo_metadata->get_seo_data( $post_id );
		if ( ! empty( $data['title'] ) ) {
			$metadata['headline'] = $data['title'];
		}

		$data_resolved = $this->seo_metadata->resolve_for_display( $data, $post_id );
		$canonical     = ! empty( $data_resolved['canonical_url'] ) ? $data_resolved['canonical_url'] : get_permalink( $post_id );
		$canonical     = apply_filters( 'prc_schema_seo_canonical_url', $canonical, $post_id, $data_resolved );
		if ( is_string( $canonical ) && '' !== $canonical ) {
			$metadata['url'] = $canonical;
		}

		if ( post_type_supports( $post_type, 'thumbnail' ) ) {
			$url = get_the_post_thumbnail_url( $post_id, 'medium' );
			if ( is_string( $url ) && '' !== $url ) {
				$metadata['thumbnailUrl'] = $url;
			}
		}

		$keywords = $this->get_parsely_tag_tokens( $post_id );
		if ( ! empty( $keywords ) ) {
			$metadata['keywords'] = $keywords;
		}

		$section = $this->get_parsely_section( $post_id );
		if ( '' !== $section ) {
			$metadata['articleSection'] = $section;
		}

		$author_names = $this->get_parsely_author_names( $post_id );
		if ( ! empty( $author_names ) ) {
			$author_objects = array();
			foreach ( $author_names as $name ) {
				if ( is_string( $name ) && '' !== $name ) {
					$author_objects[] = array(
						'@type' => 'Person',
						'name'  => $name,
					);
				}
			}
			if ( ! empty( $author_objects ) ) {
				$metadata['author']  = $author_objects;
				$metadata['creator'] = array_map(
					static function ( $a ) {
						return $a['name'];
					},
					$author_objects
				);
			}
		}

		$pub_date = get_the_date( 'c', $post_id );
		if ( is_string( $pub_date ) && '' !== $pub_date ) {
			$metadata['datePublished'] = $pub_date;
			$metadata['dateCreated']   = $pub_date;
		}
		$mod_date = get_the_modified_date( 'c', $post_id );
		if ( is_string( $mod_date ) && '' !== $mod_date ) {
			$metadata['dateModified'] = $mod_date;
		}

		return $metadata;
	}

	/**
	 * PRC canonical URL for Parse.ly link meta.
	 *
	 * @param string $permalink     Permalink.
	 * @param string $parsely_type  Parsely type.
	 * @param int    $post_id       Post ID.
	 * @return string
	 */
	public function filter_wp_parsely_permalink( $permalink, $parsely_type, $post_id ) {
		if ( 'post' !== $parsely_type || $post_id <= 0 ) {
			return $permalink;
		}
		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! post_type_supports( $post_type, 'prc-schema-seo' ) ) {
			return $permalink;
		}
		if ( defined( 'PRC_RLS_TEMPLATE_POST_TYPE' ) && PRC_RLS_TEMPLATE_POST_TYPE === $post_type ) {
			return $permalink;
		}

		$data      = $this->seo_metadata->get_seo_data( $post_id );
		$data      = $this->seo_metadata->resolve_for_display( $data, $post_id );
		$canonical = ! empty( $data['canonical_url'] ) ? $data['canonical_url'] : get_permalink( $post_id );
		$canonical = apply_filters( 'prc_schema_seo_canonical_url', $canonical, $post_id, $data );

		return is_string( $canonical ) && '' !== $canonical ? $canonical : $permalink;
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

		$html = $this->warm_term_cache( (int) $term->term_id, $term->taxonomy );
		if ( '' === $html ) {
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in build_parsely_html.
	}

	/**
	 * Output Parsely meta tags for the front page (including static front page).
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
			'parsely-title' => get_bloginfo( 'name' ),
			'parsely-link'  => get_bloginfo( 'url' ),
			'parsely-type'  => 'index',
		);
		$html = $this->build_parsely_html( $tags );
		wp_cache_set( $cache_key, $html, self::CACHE_GROUP, self::CACHE_TTL );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in build_parsely_html.
	}

	/**
	 * Warm Parsely meta tags cache for a term archive (CLI migration).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string Parsely meta tags HTML.
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
		$html = $this->build_parsely_html( $tags );
		wp_cache_set( $cache_key, $html, self::CACHE_GROUP, self::CACHE_TTL );

		return $html;
	}

	/**
	 * Get parsely-section (primary category name) for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_parsely_section( int $post_id ): string {
		$is_child  = has_post_parent( $post_id );
		$parent_id = $is_child ? (int) get_post_field( 'post_parent', $post_id ) : $post_id;
		$name      = Primary_Term::get_name( $parent_id, 'category', false );
		return is_string( $name ) ? $name : '';
	}

	/**
	 * Parsely tag tokens (category/format/team slugs and meta markers) for metadata keywords.
	 *
	 * @param int $post_id Post ID.
	 * @return string[] Non-empty, 0-indexed list.
	 */
	private function get_parsely_tag_tokens( int $post_id ): array {
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
		return array_values( $all );
	}

	/**
	 * Author names for Parsely (bylines, meta, or post author).
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private function get_parsely_author_names( int $post_id ): array {
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
	 * Build HTML for Parsely meta tags (home and term archives only).
	 *
	 * @param array<string, string|array<int, string>> $tags Tag name => value or list of values.
	 * @return string HTML string.
	 */
	private function build_parsely_html( array $tags ): string {
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
