<?php
/**
 * Fired during plugin activation.
 *
 * @package    PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * The plugin activator class.
 *
 * @package    PRC\Platform\Schema_SEO
 */
class Plugin_Activator {

	/**
	 * Activate the plugin.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		flush_rewrite_rules();
		self::seed_default_templates();

		wp_mail(
			DEFAULT_TECHNICAL_CONTACT,
			'🔎 PRC Schema SEO Activated',
			'The PRC Schema SEO plugin has been activated on ' . get_site_url()
		);
	}

	/**
	 * Seed default token patterns for the three fallback tiers.
	 *
	 * Only writes to the option when it doesn't already exist, so reactivation
	 * never overwrites user-configured templates.
	 *
	 * @since    1.0.0
	 */
	private static function seed_default_templates() {
		$option_key = 'prc_schema_seo_templates';

		if ( false !== get_option( $option_key ) ) {
			return;
		}

		$defaults = array(
			'default_site'     => array(
				'title_pattern'       => '%site_name%',
				'description_pattern' => '%site_tagline%',
			),
			'default_singular' => array(
				'title_pattern'       => '%object_title% %sep% %site_name%',
				'description_pattern' => '%object_description%',
			),
			'default_archive'  => array(
				'title_pattern'       => '%object_title% %sep% %site_name%',
				'description_pattern' => '%site_tagline%',
			),
		);

		add_option( $option_key, $defaults );
	}
}
