<?php
/**
 * Editor_UI class.
 *
 * @package    PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Register and enqueue block and site editor UI assets.
 *
 * @package    PRC\Platform\Schema_SEO
 */
class Editor_UI {

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( $loader = null ) {
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_block_editor_ui', 1 );
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_site_editor_ui', 1 );
	}

	/**
	 * Enqueue the block editor UI script.
	 *
	 * @hook enqueue_block_editor_assets
	 */
	public function enqueue_block_editor_ui() {
		// Only enqueue on enabled post types.
		$editor_asset_path = plugin_dir_path( PRC_SCHEMA_SEO_FILE ) . 'build/block-editor/index.asset.php';
		$screen            = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && isset( $screen->post_type ) ) {
			if ( post_type_supports( $screen->post_type, 'prc-schema-seo' ) ) {

				if ( file_exists( $editor_asset_path ) ) {
					$editor_asset = include $editor_asset_path;
					wp_enqueue_script(
						'prc-schema-seo-block-editor',
						plugins_url( 'build/block-editor/index.js', PRC_SCHEMA_SEO_FILE ),
						$editor_asset['dependencies'],
						$editor_asset['version'],
						true
					);

					$style_path = plugin_dir_path( PRC_SCHEMA_SEO_FILE ) . 'build/block-editor/index.css';
					if ( file_exists( $style_path ) ) {
						wp_enqueue_style(
							'prc-schema-seo-block-editor',
							plugins_url( 'build/block-editor/index.css', PRC_SCHEMA_SEO_FILE ),
							array( 'wp-components' ),
							$editor_asset['version']
						);
					}
				}
			}
		}

		if ( wp_script_is( 'prc-schema-seo-block-editor', 'enqueued' ) ) {
			wp_localize_script(
				'prc-schema-seo-block-editor',
				'PRCSchemaSEO',
				array(
					'enabledPostTypes'      => $this->get_enabled_post_types_for_js(),
					'allowedSchemaTypes'    => $this->get_allowed_schema_types(),
					'primaryTermTaxonomies' => $this->get_primary_term_taxonomies(),
					'indexnowEnabled'       => defined( 'PRC_PLATFORM_INDEXNOW_KEY' ) && ! empty( PRC_PLATFORM_INDEXNOW_KEY ),
					'gscEnabled'            => Search_Console::is_configured(),
					'homeUrl'               => home_url(),
					'symbolSvgUrl'          => $this->get_qr_logo_url(),
					'canManageSeoAdvanced'  => current_user_can( 'prc_surfaces__seo_advanced' ),
					'branding'              => apply_filters(
						'prc_schema_seo_branding',
						array(
							'siteName'        => get_bloginfo( 'name' ),
							'displayName'     => 'Pew Research Center',
							'twitterUsername' => 'pewresearch',
							'blueskyHandle'   => 'pewresearch.org', // pragma: allowlist secret — public Bluesky handle default for previews, not an API key.
						)
					),
				)
			);
		}
	}

	/**
	 * Enqueue the site editor UI script.
	 *
	 * @hook enqueue_block_editor_assets
	 */
	public function enqueue_site_editor_ui() {
		global $pagenow;
		$is_site_editor         = 'site-editor.php' === $pagenow;
		$site_editor_asset_path = plugin_dir_path( PRC_SCHEMA_SEO_FILE ) . 'build/site-editor/index.asset.php';

		if ( $is_site_editor && file_exists( $site_editor_asset_path ) ) {
			$site_editor_asset = include $site_editor_asset_path;
			wp_enqueue_script(
				'prc-schema-seo-site-editor',
				plugins_url( 'build/site-editor/index.js', PRC_SCHEMA_SEO_FILE ),
				$site_editor_asset['dependencies'],
				$site_editor_asset['version'],
				true
			);

			wp_localize_script(
				'prc-schema-seo-site-editor',
				'PRCSchemaSEO',
				array(
					'allowedSchemaTypes' => $this->get_allowed_schema_types(),
				)
			);
		}
	}

	/**
	 * Get primary term taxonomies.
	 *
	 * @return array The primary term taxonomies.
	 */
	private function get_primary_term_taxonomies() {
		return Primary_Term::get_supported_taxonomies();
	}

	/**
	 * Get enabled post types for JavaScript.
	 *
	 * @return array Array of post type slugs that support prc-schema-seo.
	 */
	private function get_enabled_post_types_for_js() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		return array_values(
			array_filter(
				$post_types,
				function ( $pt ) {
					return post_type_supports( $pt, 'prc-schema-seo' );
				}
			)
		);
	}

	/**
	 * Resolve the logo URL for the QR code center overlay.
	 *
	 * Priority: site icon > custom logo. Empty string when neither is set.
	 * Filterable via `prc_schema_seo_qr_logo_url`.
	 *
	 * @return string Absolute URL to a logo image, or empty string for QR without center logo.
	 */
	private function get_qr_logo_url(): string {
		$url = '';

		$site_icon = get_site_icon_url( 512 );
		if ( $site_icon ) {
			$url = $site_icon;
		}

		if ( ! $url ) {
			$custom_logo_id = get_theme_mod( 'custom_logo' );
			if ( $custom_logo_id ) {
				$logo_url = wp_get_attachment_image_url( (int) $custom_logo_id, 'medium' );
				if ( $logo_url ) {
					$url = $logo_url;
				}
			}
		}

		return apply_filters( 'prc_schema_seo_qr_logo_url', $url );
	}

	/**
	 * Safely retrieve allowed schema types via filter, validating structure.
	 *
	 * @return array
	 */
	private function get_allowed_schema_types() {
		$default  = array( 'Article', 'NewsArticle', 'BlogPosting', 'Report', 'WebPage', 'Person', 'Event', 'Course', 'Dataset', 'Quiz', 'CollectionPage' );
		$filtered = apply_filters( 'prc_schema_seo_allowed_schema_types', $default, '' );
		if ( ! is_array( $filtered ) ) {
			// Log filtered type validation failure.
			return $default;
		}
		// Ensure scalar string values only.
		$clean = array();
		foreach ( $filtered as $type ) {
			if ( is_string( $type ) && preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $type ) ) {
				$clean[] = $type;
			}
			// Invalid types silently filtered out (validated via regex).
		}
		return ! empty( $clean ) ? array_values( array_unique( $clean ) ) : $default;
	}
}
