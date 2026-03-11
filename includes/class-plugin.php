<?php
/**
 * Plugin class.
 *
 * Main plugin controller and dependency injection container.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Plugin class.
 *
 * Coordinates all plugin functionality and manages dependencies.
 */
class Plugin {
	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Initialize the plugin and set its properties.
	 */
	public function __construct() {
		$this->version     = PRC_SCHEMA_SEO_VERSION;
		$this->plugin_name = 'prc-schema-seo';

		require_once __DIR__ . '/class-loader.php';

		$this->load_dependencies();
		$this->init_dependencies();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		$composer_autoload = plugin_dir_path( __DIR__ ) . '/vendor/autoload.php';
		// If the composer autoload file exists and if this plugin is being used off platform or in a test case of the platform, load the autoload file.
		if ( file_exists( $composer_autoload ) && ( ! defined( 'PRC_PLATFORM' ) || ( defined( 'PRC_PLATFORM' ) && true !== PRC_PLATFORM ) ) ) {
			require_once $composer_autoload;
		}

		$this->loader = new Loader();

		// Load required class files
		require_once __DIR__ . '/class-primary-term.php';
		require_once __DIR__ . '/class-token-resolver.php';
		require_once __DIR__ . '/class-metadata.php';
		require_once __DIR__ . '/class-rest-api.php';

		// Schema generator and output
		require_once __DIR__ . '/schema/class-generator.php';
		require_once __DIR__ . '/schema/class-json-output.php';

		require_once __DIR__ . '/class-meta-tags.php';
		require_once __DIR__ . '/class-parsely-meta.php';
		require_once __DIR__ . '/class-template-context.php';
		require_once __DIR__ . '/class-template-defaults.php';
		require_once __DIR__ . '/class-cache-invalidator.php';

		// Contact resolution service
		require_once __DIR__ . '/class-contact-resolver.php';

		// Redirect on slug change (integration with Safe Redirect Manager)
		require_once __DIR__ . '/class-redirect-on-slug-change.php';

		// Sitemap integration (conditional on sitemap plugin)
		require_once __DIR__ . '/class-sitemap-integration.php';

		// IndexNow search engine notification
		require_once __DIR__ . '/class-indexnow.php';

		// Google Search Console URL Inspection
		require_once __DIR__ . '/class-search-console.php';

		// User Interface for various contexts
		require_once __DIR__ . '/admin/class-taxonomy-ui.php';
		require_once __DIR__ . '/admin/class-editor-ui.php';
		require_once __DIR__ . '/admin/class-redirect-csv-import.php';

		// Migration utilities (loaded lazily for CLI usage).
		require_once __DIR__ . '/class-yoast-migrator.php';

		// Admin Columns Pro integration (hooks only fire if Admin Columns is active).
		require_once __DIR__ . '/admin-columns/class-admin-columns.php';

		// Reading score calculator and WP Ability (no AI dependency).
		require_once __DIR__ . '/class-reading-score.php';

		// Load the AI experiment and ability classes if the WP AI plugin is available.
		if ( class_exists( '\WordPress\AI\Abstracts\Abstract_Experiment' ) ) {
			require_once __DIR__ . '/ai-experiment/class-seo-ai-ability.php';
			require_once __DIR__ . '/ai-experiment/class-seo-ai-experiment.php';
		}
	}

	/**
	 * Initialize the dependencies.
	 */
	private function init_dependencies() {
		// Register default post type support for built-in types
		$this->loader->add_action( 'init', $this, 'register_default_post_type_support', 5 );

		// SEO Metadata management
		new Metadata( $this->get_loader() );
		// REST API extensions
		new REST_API( $this->get_loader() );

		// Schema generator (instantiated separately for potential future direct use)
		new Generator( $this->get_loader() );
		// Output handler (registers wp_head schema output)
		new JSON_Output( $this->get_loader() );

		// Meta tags output (Open Graph, Twitter, robots, canonical)
		new Meta_Tags( $this->get_loader() );
		// Parsely meta tags (parsely-title, parsely-link, etc.)
		new Parsely_Meta( $this->get_loader() );
		// Template defaults (site level patterns & overrides)
		new Template_Defaults( $this->get_loader() );
		// Cache invalidation events
		new Cache_Invalidator( $this->get_loader() );

		// Editor SEO UI components
		new Editor_UI( $this->get_loader() );
		// Taxonomy Term SEO UI components
		new Taxonomy_UI( $this->get_loader() );

		// Automatic redirects on slug change (Safe Redirect Manager integration)
		new Redirect_On_Slug_Change( $this->get_loader() );
		// CSV import UI for Safe Redirect Manager
		new Redirect_CSV_Import( $this->get_loader() );

		// Sitemap integration (only hooks if sitemap plugin is active)
		new Sitemap_Integration( $this->get_loader() );

		// IndexNow: notify search engines when content changes
		new IndexNow( $this->get_loader() );

		// Google Search Console: surface index status in the editor
		new Search_Console( $this->get_loader() );

		// Admin Columns Pro integration (hooks only fire if Admin Columns is active)
		new Admin_Columns( $this->get_loader() );

		// Reading score ability (registered unconditionally — no AI plugin required).
		new Reading_Score( $this->get_loader() );

		// Register the AI experiment with the WP AI Experiments plugin.
		if ( class_exists( '\WordPress\AI\Abstracts\Abstract_Experiment' ) ) {
			add_action(
				'ai_experiments_register_experiments',
				function ( $registry ) {
					$registry->register_experiment( new SEO_AI_Experiment() );
				}
			);
		}

		// Use a longer timeout for Google Generative Language API requests.
		// WordPress default is 5s; AI generation often needs 15–60+ seconds.
		$this->loader->add_filter( 'http_request_args', $this, 'filter_ai_http_request_timeout', 10, 2 );
	}

	/**
	 * Increase HTTP timeout for Google Generative Language API requests.
	 *
	 * The php-ai-client does not set RequestOptions when the ability runs, so
	 * wp_remote_request() uses WordPress's default 5s timeout and requests
	 * to generativelanguage.googleapis.com time out before the model responds.
	 *
	 * @param array<string, mixed> $args  Request arguments.
	 * @param string               $url  Request URL.
	 * @return array<string, mixed> Filtered arguments.
	 */
	public function filter_ai_http_request_timeout( $args, $url ) {
		if ( str_contains( $url, 'generativelanguage.googleapis.com' ) ) {
			$args['timeout'] = (int) apply_filters( 'prc_schema_seo_ai_request_timeout', 60 );
		}
		return $args;
	}

	/**
	 * Register default post type support for built-in post types.
	 *
	 * @hook init
	 */
	public function register_default_post_type_support() {
		add_post_type_support( 'post', 'prc-schema-seo' );
		add_post_type_support( 'page', 'prc-schema-seo' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin.
	 *
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks.
	 *
	 * @return Loader The loader.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return string The version number.
	 */
	public function get_version() {
		return $this->version;
	}
}
