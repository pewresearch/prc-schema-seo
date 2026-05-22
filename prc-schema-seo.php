<?php
/**
 * PRC Schema SEO Plugin
 *
 * @package           PRC\Schema_SEO
 * @author            Seth Rubenstein
 * @copyright         2024 Pew Research Center
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       PRC Schema - SEO
 * Plugin URI:        https://github.com/pewresearch/prc-schema-seo
 * Description:       A schema.org SEO plugin for PRC Platform.
 * Version:           1.1.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Seth Rubenstein
 * Author URI:        https://pewresearch.org
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       prc-schema-seo
 * Requires Plugins:  prc-scripts, prc-post-publish-pipeline
 */

namespace PRC\Platform\Schema_SEO;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DEFAULT_TECHNICAL_CONTACT' ) ) {
	define( 'DEFAULT_TECHNICAL_CONTACT', 'webdev@pewresearch.org' );
}

// When running inside the PRC Platform monorepo the root autoloader already
// provides every dependency; skip per-plugin Jetpack Autoloader initialization.
if ( ! defined( 'PRC_PLATFORM' ) ) {
	$prc_schema_seo_autoloader = __DIR__ . '/vendor/autoload_packages.php';
	if ( file_exists( $prc_schema_seo_autoloader ) ) {
		require_once $prc_schema_seo_autoloader;
	}
	unset( $prc_schema_seo_autoloader );
}

define( 'PRC_SCHEMA_SEO_FILE', __FILE__ );
define( 'PRC_SCHEMA_SEO_DIR', __DIR__ );
define( 'PRC_SCHEMA_SEO_VERSION', '1.0.0' );
define( 'PRC_SCHEMA_SEO_DISABLE_CACHE', false );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-plugin-activator.php
 */
function activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-activator.php';
	Plugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-plugin-deactivator.php
 */
function deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-deactivator.php';
	Plugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, '\PRC\Platform\Schema_SEO\activate' );
register_deactivation_hook( __FILE__, '\PRC\Platform\Schema_SEO\deactivate' );

/**
 * Helper utilities
 */
require plugin_dir_path( __FILE__ ) . 'includes/utils.php';

/**
 * The core bootstrap class that is used to define the hooks that initialize the various components.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-plugin.php';

/**
 * Optional CLI utilities (WP-CLI only). Loaded lazily to avoid overhead.
 */
if ( defined( 'WP_CLI' ) && class_exists( '\WP_CLI' ) ) {
	require plugin_dir_path( __FILE__ ) . 'includes/cli/class-cli-benchmark.php';
	require plugin_dir_path( __FILE__ ) . 'includes/cli/class-cli-migration.php';
	require plugin_dir_path( __FILE__ ) . 'includes/cli/class-cli-compare.php';
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_prc_schema_seo() {
	$plugin = new Plugin();
	$plugin->run();

	// Register all WP-CLI commands under the 'prc-seo' namespace.
	if ( defined( 'WP_CLI' ) && class_exists( '\WP_CLI' ) ) {
		// Benchmark command: wp prc-seo benchmark --post=ID
		if ( class_exists( '\PRC\Platform\Schema_SEO\CLI_Benchmark' ) ) {
			\WP_CLI::add_command( 'prc-seo benchmark', array( new CLI_Benchmark(), 'run' ) );
		}

		// Migration commands: wp prc-seo migrate-post, migrate-posts, migrate-term, migrate-terms, migration-status, preview-yoast
		if ( class_exists( '\PRC\Platform\Schema_SEO\CLI_Migration' ) ) {
			\WP_CLI::add_command( 'prc-seo', new CLI_Migration() );
		}

		// Compare commands: wp prc-seo compare, compare-batch
		if ( class_exists( '\PRC\Platform\Schema_SEO\CLI_Compare' ) ) {
			\WP_CLI::add_command( 'prc-seo compare', array( new CLI_Compare(), 'compare' ) );
			\WP_CLI::add_command( 'prc-seo compare-batch', array( new CLI_Compare(), 'compare_batch' ) );
		}
	}
}
run_prc_schema_seo();
