<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * The plugin deactivator class.
 *
 * @package    PRC\Platform\Schema_SEO
 */
class Plugin_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		flush_rewrite_rules();

		wp_mail(
			DEFAULT_TECHNICAL_CONTACT,
			'🔎 PRC Schema SEO Deactivated',
			'The PRC Schema SEO plugin has been deactivated on ' . get_site_url()
		);
	}
}
