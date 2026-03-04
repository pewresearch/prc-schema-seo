<?php
/**
 * Sitemap Integration class.
 *
 * Integrates prc-schema-seo with prc-schema-sitemap to exclude noindex posts from sitemaps.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Sitemap Integration class.
 *
 * Handles integration with prc-schema-sitemap plugin to exclude
 * posts marked as noindex from sitemap generation.
 */
class Sitemap_Integration {
	/**
	 * The loader instance.
	 *
	 * @var Loader
	 */
	private $loader;

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;
		$this->loader->add_action( 'init', $this, 'hook_into_sitemap_filter' );
	}

	/**
	 * Hook into the sitemap filter.
	 *
	 * Only hooks if the PRC_Sitemap class exists (sitemap plugin is active).
	 */
	public function hook_into_sitemap_filter() {
		// Only hook if sitemap plugin is active
		if ( ! class_exists( 'PRC_Sitemap' ) ) {
			return;
		}

		// Hook with priority 10 (default), accept 2 parameters
		add_filter( 'prc_sitemap_skip_post', array( $this, 'should_skip_post' ), 10, 2 );
	}

	/**
	 * Determine if a post should be skipped from the sitemap.
	 * If a post is set to be noindex, we should respect that and skip it from the sitemap.
	 *
	 * @param bool     $skip    Current skip status.
	 * @param int|null $post_id Post ID (optional, for backward compatibility).
	 * @return bool True if post should be skipped, false otherwise.
	 */
	public function should_skip_post( $skip, $post_id = null ) {
		// If already skipped, respect that
		if ( $skip ) {
			return $skip;
		}

		// Fallback to global if post_id not provided (backward compatibility)
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return $skip;
		}

		// Get SEO data
		$seo_data = new Metadata( $this->loader );
		$seo_data = $seo_data->get_seo_data( $post_id );

		// Apply noindex filter (allows other plugins to override)
		$noindex = apply_filters(
			'prc_schema_seo_noindex',
			! empty( $seo_data['noindex'] ),
			$post_id,
			$seo_data
		);

		// Skip if noindex is true
		return $noindex;
	}
}
