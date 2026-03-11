<?php
/**
 * CLI Migration class.
 *
 * WP-CLI commands for Yoast SEO to PRC Schema SEO migration.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

use WP_CLI;
use WP_CLI\Utils;
use WPCOM_VIP_CLI_Command;

if ( ! class_exists( 'WPCOM_VIP_CLI_Command' ) ) {
	return;
}

/**
 * CLI_Migration class.
 *
 * Provides WP-CLI commands for migrating Yoast SEO data to PRC Schema SEO format.
 *
 * ## EXAMPLES
 *
 *     # Migrate a single post (preview)
 *     wp prc-seo migrate-post 123
 *
 *     # Migrate all posts for real
 *     wp prc-seo migrate-posts --dry-run=false
 *
 *     # Show migration status
 *     wp prc-seo migration-status
 */
class CLI_Migration extends WPCOM_VIP_CLI_Command {

	/**
	 * The Yoast migrator instance.
	 *
	 * @var Yoast_Migrator
	 */
	private $migrator;

	/**
	 * Cursor ID for --start-id pagination filter.
	 *
	 * @var int
	 */
	private $cursor_id = 0;

	/**
	 * Generator instance for schema cache warming. Lazy-initialized.
	 *
	 * @var Generator|null
	 */
	private $generator = null;

	/**
	 * Meta_Tags instance for meta tags cache warming. Lazy-initialized.
	 *
	 * @var Meta_Tags|null
	 */
	private $meta_tags = null;

	/**
	 * Parsely_Meta instance for Parsely cache warming. Lazy-initialized.
	 *
	 * @var Parsely_Meta|null
	 */
	private $parsely = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->migrator = new Yoast_Migrator();
	}

	/**
	 * Lazy-initialize cache services (Generator, Meta_Tags, Parsely_Meta).
	 *
	 * @return void
	 */
	private function init_cache_services(): void {
		if ( null !== $this->generator ) {
			return;
		}
		$loader          = new Loader();
		$this->generator = new Generator( $loader );
		$this->meta_tags = new Meta_Tags( $loader );
		$this->parsely   = new Parsely_Meta( $loader );
	}

	/**
	 * Warm SEO caches for a post after migration.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function warm_post_cache( int $post_id ): void {
		$this->init_cache_services();
		$this->generator->generate_schema( $post_id );
		$this->meta_tags->warm_post_cache( $post_id );
		$this->parsely->warm_post_cache( $post_id );
	}

	/**
	 * Warm SEO caches for a term after migration.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	private function warm_term_cache( int $term_id, string $taxonomy ): void {
		$this->init_cache_services();
		$this->generator->generate_term_schema( $term_id );
		$this->meta_tags->warm_term_cache( $term_id, $taxonomy );
		$this->parsely->warm_term_cache( $term_id, $taxonomy );
	}

	/**
	 * Migrate Yoast SEO data for a single post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID to migrate.
	 *
	 * [--dry-run=<bool>]
	 * : Preview changes without saving to database. Default: true.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview migration for post ID 123
	 *     wp prc-seo migrate-post 123
	 *
	 *     # Migrate post ID 123 for real
	 *     wp prc-seo migrate-post 123 --dry-run=false
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function migrate_post( $args, $assoc_args ) {
		if ( ! isset( $args[0] ) || ! absint( $args[0] ) ) {
			WP_CLI::error( 'Please provide a valid post ID.' );
			return;
		}

		$post_id = absint( $args[0] );
		$dry_run = $this->parse_dry_run( $assoc_args );

		if ( $dry_run ) {
			WP_CLI::line( 'Running in dry-run mode. No changes will be saved.' );
		}

		$result = $this->migrator->migrate_post( $post_id, $dry_run, false );

		if ( $result['success'] ) {
			if ( $result['skipped'] ) {
				WP_CLI::warning( $result['message'] );
			} else {
				WP_CLI::success( $result['message'] );
				if ( ! empty( $result['data'] ) ) {
					WP_CLI::line( 'Migrated data:' );
					WP_CLI::line( wp_json_encode( $result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
				}
				if ( ! $dry_run ) {
					$this->warm_post_cache( $post_id );
				}
			}
		} else {
			WP_CLI::warning( $result['message'] );
		}
	}

	/**
	 * Migrate Yoast SEO data for multiple posts in batches.
	 *
	 * Defaults to dry-run mode. Pass --dry-run=false to write.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<type>]
	 * : Post type to migrate. Default: 'post'.
	 *
	 * [--batch-size=<size>]
	 * : Number of posts to process per batch. Default: 100. Maximum: 100.
	 *
	 * [--dry-run=<bool>]
	 * : Preview changes without saving to database. Default: true.
	 *
	 * [--skip-existing]
	 * : Skip posts that already have PRC SEO data. Default: true.
	 *
	 * [--no-skip-existing]
	 * : Process all posts, including those with existing PRC SEO data.
	 *
	 * [--start-id=<id>]
	 * : Resume from this post ID (only process posts with ID greater than this value). Default: 0.
	 *
	 * [--no-warm-cache]
	 * : Skip pre-caching SEO output after migration (faster for large runs).
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview migration for all posts (dry-run by default)
	 *     wp prc-seo migrate-posts
	 *
	 *     # Migrate all posts for real
	 *     wp prc-seo migrate-posts --dry-run=false
	 *
	 *     # Migrate only pages
	 *     wp prc-seo migrate-posts --post-type=page --dry-run=false
	 *
	 *     # Resume after interruption at post ID 5000
	 *     wp prc-seo migrate-posts --dry-run=false --start-id=5000
	 *
	 *     # Force re-migration of all posts
	 *     wp prc-seo migrate-posts --no-skip-existing --dry-run=false
	 *
	 * @subcommand migrate-posts
	 * @synopsis [--post-type=<type>] [--batch-size=<size>] [--dry-run=<bool>] [--skip-existing] [--no-skip-existing] [--start-id=<id>] [--no-warm-cache]
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function migrate_posts( $args, $assoc_args ) {
		$post_type     = Utils\get_flag_value( $assoc_args, 'post-type', 'post' );
		$batch_size    = min( absint( Utils\get_flag_value( $assoc_args, 'batch-size', 100 ) ), 100 );
		$dry_run       = $this->parse_dry_run( $assoc_args );
		$skip_existing = ! Utils\get_flag_value( $assoc_args, 'no-skip-existing', false );
		$start_id      = absint( Utils\get_flag_value( $assoc_args, 'start-id', 0 ) );
		$no_warm_cache = Utils\get_flag_value( $assoc_args, 'no-warm-cache', false );

		if ( $batch_size < 1 ) {
			$batch_size = 100;
		}

		if ( defined( 'PRC_SCHEMA_SEO_DISABLE_CACHE' ) && PRC_SCHEMA_SEO_DISABLE_CACHE ) {
			WP_CLI::warning( 'PRC_SCHEMA_SEO_DISABLE_CACHE is enabled. Cache warming will have no effect.' );
		}

		WP_CLI::line( sprintf( 'Migrating %s posts%s…', $post_type, $dry_run ? ' (dry-run)' : '' ) );

		if ( $skip_existing ) {
			WP_CLI::line( 'Skipping posts with existing PRC SEO data.' );
		}

		if ( $start_id > 0 ) {
			WP_CLI::line( sprintf( 'Resuming from post ID > %d.', $start_id ) );
			$this->cursor_id = $start_id;
			add_filter( 'posts_where', array( $this, 'filter_posts_after_cursor' ) );
		}

		$stats = array(
			'processed'    => 0,
			'migrated'     => 0,
			'skipped'      => 0,
			'failed'       => 0,
			'cache_warmed' => 0,
		);

		$this->start_bulk_operation();

		$paged   = 1;
		$last_id = $start_id;

		do {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => $batch_size,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					'paged'          => $paged,
				)
			);

			foreach ( $posts as $post ) {
				$result = $this->migrator->migrate_post( $post->ID, $dry_run, $skip_existing );

				if ( ! $result['success'] ) {
					++$stats['failed'];
					WP_CLI::warning( sprintf( 'Post ID %d: %s', $post->ID, $result['message'] ) );
				} elseif ( $result['skipped'] ) {
					++$stats['skipped'];
				} else {
					++$stats['migrated'];
					if ( ! $dry_run && ! $no_warm_cache ) {
						$this->warm_post_cache( $post->ID );
						++$stats['cache_warmed'];
					}
				}

				++$stats['processed'];
				$last_id = $post->ID;
			}

			WP_CLI::line(
				sprintf(
					'Batch %d done. Last ID: %d | Processed: %d | Migrated: %d | Skipped: %d | Failed: %d | Caches warmed: %d',
					$paged,
					$last_id,
					$stats['processed'],
					$stats['migrated'],
					$stats['skipped'],
					$stats['failed'],
					$stats['cache_warmed']
				)
			);

			sleep( 2 );
			$this->vip_inmemory_cleanup();

			++$paged;

		} while ( count( $posts ) === $batch_size );

		$this->end_bulk_operation();

		if ( $start_id > 0 ) {
			remove_filter( 'posts_where', array( $this, 'filter_posts_after_cursor' ) );
		}

		WP_CLI::line( '' );
		WP_CLI::line( '=== Migration Results ===' );
		WP_CLI::line( sprintf( 'Posts processed:    %d', $stats['processed'] ) );
		WP_CLI::line( sprintf( 'Posts migrated:     %d', $stats['migrated'] ) );
		WP_CLI::line( sprintf( 'Posts skipped:      %d', $stats['skipped'] ) );
		WP_CLI::line( sprintf( 'Posts failed:       %d', $stats['failed'] ) );
		WP_CLI::line( sprintf( 'Caches warmed:      %d', $stats['cache_warmed'] ) );

		if ( $stats['failed'] > 0 ) {
			WP_CLI::warning( sprintf( 'Migration completed with %d failures.', $stats['failed'] ) );
		} else {
			WP_CLI::success(
				$dry_run
					? sprintf( '%d posts would be migrated.', $stats['migrated'] )
					: sprintf( 'Migration completed successfully. %d posts migrated.', $stats['migrated'] )
			);
		}
	}

	/**
	 * WP_Query posts_where filter for --start-id cursor pagination.
	 *
	 * @param string $where Existing WHERE clause.
	 * @return string Modified WHERE clause.
	 */
	public function filter_posts_after_cursor( $where ) {
		global $wpdb;
		return $where . $wpdb->prepare( ' AND ' . $wpdb->posts . '.ID > %d', $this->cursor_id );
	}

	/**
	 * Migrate Yoast SEO data for a single term.
	 *
	 * ## OPTIONS
	 *
	 * <term_id>
	 * : The term ID to migrate.
	 *
	 * --taxonomy=<taxonomy>
	 * : The taxonomy of the term.
	 *
	 * [--dry-run=<bool>]
	 * : Preview changes without saving to database. Default: true.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview migration for category term ID 55
	 *     wp prc-seo migrate-term 55 --taxonomy=category
	 *
	 *     # Migrate term 55 for real
	 *     wp prc-seo migrate-term 55 --taxonomy=category --dry-run=false
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function migrate_term( $args, $assoc_args ) {
		$term_id  = absint( $args[0] );
		$taxonomy = Utils\get_flag_value( $assoc_args, 'taxonomy', '' );
		$dry_run  = $this->parse_dry_run( $assoc_args );

		if ( ! $term_id ) {
			WP_CLI::error( 'Please provide a valid term ID.' );
			return;
		}

		if ( empty( $taxonomy ) ) {
			WP_CLI::error( 'Please provide a taxonomy with --taxonomy=<taxonomy>.' );
			return;
		}

		if ( $dry_run ) {
			WP_CLI::line( 'Running in dry-run mode. No changes will be saved.' );
		}

		$result = $this->migrator->migrate_term( $term_id, $taxonomy, $dry_run, false );

		if ( $result['success'] ) {
			if ( $result['skipped'] ) {
				WP_CLI::warning( $result['message'] );
			} else {
				WP_CLI::success( $result['message'] );
				if ( ! empty( $result['data'] ) ) {
					WP_CLI::line( 'Migrated data:' );
					WP_CLI::line( wp_json_encode( $result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
				}
				if ( ! $dry_run ) {
					$this->warm_term_cache( $term_id, $taxonomy );
				}
			}
		} else {
			WP_CLI::warning( $result['message'] );
		}
	}

	/**
	 * Migrate Yoast SEO data for all terms.
	 *
	 * Defaults to dry-run mode. Pass --dry-run=false to write.
	 *
	 * ## OPTIONS
	 *
	 * [--taxonomy=<taxonomy>]
	 * : Specific taxonomy to migrate. Default: all taxonomies.
	 *
	 * [--dry-run=<bool>]
	 * : Preview changes without saving to database. Default: true.
	 *
	 * [--skip-existing]
	 * : Skip terms that already have PRC SEO data. Default: true.
	 *
	 * [--no-skip-existing]
	 * : Process all terms, including those with existing PRC SEO data.
	 *
	 * [--no-warm-cache]
	 * : Skip pre-caching SEO output after migration (faster for large runs).
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview migration for all terms (dry-run by default)
	 *     wp prc-seo migrate-terms
	 *
	 *     # Migrate all terms for real
	 *     wp prc-seo migrate-terms --dry-run=false
	 *
	 *     # Migrate only category terms
	 *     wp prc-seo migrate-terms --taxonomy=category --dry-run=false
	 *
	 *     # Force re-migration of all terms
	 *     wp prc-seo migrate-terms --no-skip-existing --dry-run=false
	 *
	 * @subcommand migrate-terms
	 * @synopsis [--taxonomy=<taxonomy>] [--dry-run=<bool>] [--skip-existing] [--no-skip-existing] [--no-warm-cache]
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function migrate_terms( $args, $assoc_args ) {
		$taxonomy      = Utils\get_flag_value( $assoc_args, 'taxonomy', null );
		$dry_run       = $this->parse_dry_run( $assoc_args );
		$skip_existing = ! Utils\get_flag_value( $assoc_args, 'no-skip-existing', false );
		$no_warm_cache = Utils\get_flag_value( $assoc_args, 'no-warm-cache', false );

		if ( defined( 'PRC_SCHEMA_SEO_DISABLE_CACHE' ) && PRC_SCHEMA_SEO_DISABLE_CACHE ) {
			WP_CLI::warning( 'PRC_SCHEMA_SEO_DISABLE_CACHE is enabled. Cache warming will have no effect.' );
		}

		if ( $taxonomy ) {
			WP_CLI::line( sprintf( 'Migrating terms in "%s" taxonomy%s…', $taxonomy, $dry_run ? ' (dry-run)' : '' ) );
		} else {
			WP_CLI::line( sprintf( 'Migrating terms in all taxonomies%s…', $dry_run ? ' (dry-run)' : '' ) );
		}

		if ( $skip_existing ) {
			WP_CLI::line( 'Skipping terms with existing PRC SEO data.' );
		}

		$term_args = array(
			'hide_empty' => false,
		);

		if ( $taxonomy ) {
			$term_args['taxonomy'] = $taxonomy;
		}

		$terms = get_terms( $term_args );

		if ( is_wp_error( $terms ) ) {
			WP_CLI::warning( 'Could not retrieve terms: ' . $terms->get_error_message() );
			return;
		}

		if ( empty( $terms ) ) {
			WP_CLI::line( 'No terms found.' );
			return;
		}

		WP_CLI::line( sprintf( 'Found %d terms to process.', count( $terms ) ) );

		$stats = array(
			'processed'    => 0,
			'migrated'     => 0,
			'skipped'      => 0,
			'failed'       => 0,
			'cache_warmed' => 0,
		);

		$this->start_bulk_operation();

		foreach ( $terms as $term ) {
			$result = $this->migrator->migrate_term( $term->term_id, $term->taxonomy, $dry_run, $skip_existing );

			if ( ! $result['success'] ) {
				++$stats['failed'];
				WP_CLI::warning( sprintf( 'Term ID %d (%s): %s', $term->term_id, $term->taxonomy, $result['message'] ) );
			} elseif ( $result['skipped'] ) {
				++$stats['skipped'];
			} else {
				++$stats['migrated'];
				if ( ! $dry_run && ! $no_warm_cache ) {
					$this->warm_term_cache( $term->term_id, $term->taxonomy );
					++$stats['cache_warmed'];
				}
			}

			++$stats['processed'];

			if ( 0 === $stats['processed'] % 50 ) {
				WP_CLI::line( sprintf( 'Processed %d / %d terms…', $stats['processed'], count( $terms ) ) );
				sleep( 1 );
				$this->vip_inmemory_cleanup();
			}
		}

		$this->end_bulk_operation();

		WP_CLI::line( '' );
		WP_CLI::line( '=== Migration Results ===' );
		WP_CLI::line( sprintf( 'Terms processed:    %d', $stats['processed'] ) );
		WP_CLI::line( sprintf( 'Terms migrated:     %d', $stats['migrated'] ) );
		WP_CLI::line( sprintf( 'Terms skipped:      %d', $stats['skipped'] ) );
		WP_CLI::line( sprintf( 'Terms failed:       %d', $stats['failed'] ) );
		WP_CLI::line( sprintf( 'Caches warmed:      %d', $stats['cache_warmed'] ) );

		if ( $stats['failed'] > 0 ) {
			WP_CLI::warning( sprintf( 'Migration completed with %d failures.', $stats['failed'] ) );
		} else {
			WP_CLI::success(
				$dry_run
					? sprintf( '%d terms would be migrated.', $stats['migrated'] )
					: sprintf( 'Migration completed successfully. %d terms migrated.', $stats['migrated'] )
			);
		}
	}

	/**
	 * Show migration status and statistics.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show migration status
	 *     wp prc-seo migration-status
	 *
	 *     # Show status as JSON
	 *     wp prc-seo migration-status --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function migration_status( $args, $assoc_args ) {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$status = $this->migrator->get_migration_status();

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		WP_CLI::line( '' );
		WP_CLI::line( '=== Yoast SEO to PRC Schema SEO Migration Status ===' );
		WP_CLI::line( '' );

		WP_CLI::line( 'POSTS:' );
		WP_CLI::line( sprintf( '  Posts with Yoast meta:     %d', $status['posts']['with_yoast_meta'] ) );
		WP_CLI::line( sprintf( '  Posts with PRC meta:       %d', $status['posts']['with_prc_meta'] ) );
		WP_CLI::line( sprintf( '  Posts pending migration:   %d', $status['posts']['pending_migration'] ) );

		WP_CLI::line( '' );
		WP_CLI::line( 'TERMS:' );
		WP_CLI::line( sprintf( '  Terms with Yoast meta:     %d', $status['terms']['with_yoast_meta'] ) );
		WP_CLI::line( sprintf( '  Terms with PRC meta:       %d', $status['terms']['with_prc_meta'] ) );
		WP_CLI::line( sprintf( '  Terms pending migration:   %d', $status['terms']['pending_migration'] ) );

		WP_CLI::line( '' );

		$total_pending = $status['posts']['pending_migration'] + $status['terms']['pending_migration'];

		if ( $total_pending > 0 ) {
			WP_CLI::warning( sprintf( 'There are %d items pending migration.', $total_pending ) );
			WP_CLI::line( '' );
			WP_CLI::line( 'To migrate all posts:' );
			WP_CLI::line( '  wp prc-seo migrate-posts              (preview — dry-run by default)' );
			WP_CLI::line( '  wp prc-seo migrate-posts --dry-run=false  (execute)' );
			WP_CLI::line( '' );
			WP_CLI::line( 'To migrate all terms:' );
			WP_CLI::line( '  wp prc-seo migrate-terms              (preview — dry-run by default)' );
			WP_CLI::line( '  wp prc-seo migrate-terms --dry-run=false  (execute)' );
		} else {
			WP_CLI::success( 'All Yoast SEO data has been migrated to PRC Schema SEO format.' );
		}
	}

	/**
	 * Preview Yoast SEO data for a post without migrating.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID to preview.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview Yoast data for post ID 123
	 *     wp prc-seo preview-yoast 123
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function preview_yoast( $args, $assoc_args ) {
		$post_id = absint( $args[0] );

		if ( ! $post_id ) {
			WP_CLI::error( 'Please provide a valid post ID.' );
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			WP_CLI::error( sprintf( 'Post ID %d does not exist.', $post_id ) );
			return;
		}

		WP_CLI::line( sprintf( 'Yoast SEO data for post ID %d ("%s"):', $post_id, $post->post_title ) );
		WP_CLI::line( '' );

		$yoast_data = $this->migrator->get_yoast_post_data( $post_id );

		if ( empty( $yoast_data ) ) {
			WP_CLI::warning( 'No Yoast SEO data found for this post.' );
			return;
		}

		WP_CLI::line( wp_json_encode( $yoast_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Clean leftover Yoast %%...%% placeholders from already-migrated PRC SEO data.
	 *
	 * Scans _prc_seo_data post meta for title, og_title, and twitter_title fields
	 * containing Yoast replacement variables (e.g. %%sitename%%, %%title%%) and
	 * strips them. Optionally processes _prc_seo_term_data as well.
	 *
	 * Defaults to dry-run mode. Pass --dry-run=false to write.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<type>]
	 * : Post type to process. Default: all post types.
	 *
	 * [--batch-size=<size>]
	 * : Number of posts to process per batch. Default: 100. Maximum: 100.
	 *
	 * [--dry-run=<bool>]
	 * : Preview changes without saving to database. Default: true.
	 *
	 * [--include-terms]
	 * : Also clean term SEO data (_prc_seo_term_data).
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview which posts have Yoast placeholders (dry-run)
	 *     wp prc-seo clean-yoast-placeholders
	 *
	 *     # Clean all posts for real
	 *     wp prc-seo clean-yoast-placeholders --dry-run=false
	 *
	 *     # Clean posts and terms
	 *     wp prc-seo clean-yoast-placeholders --dry-run=false --include-terms
	 *
	 * @subcommand clean-yoast-placeholders
	 * @synopsis [--post-type=<type>] [--batch-size=<size>] [--dry-run=<bool>] [--include-terms]
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function clean_yoast_placeholders( $args, $assoc_args ) {
		$post_type     = Utils\get_flag_value( $assoc_args, 'post-type', '' );
		$batch_size    = min( absint( Utils\get_flag_value( $assoc_args, 'batch-size', 100 ) ), 100 );
		$dry_run       = $this->parse_dry_run( $assoc_args );
		$include_terms = Utils\get_flag_value( $assoc_args, 'include-terms', false );

		if ( $batch_size < 1 ) {
			$batch_size = 100;
		}

		$text_fields = array( 'title', 'og_title', 'twitter_title', 'description', 'og_description', 'twitter_description' );

		WP_CLI::line( sprintf( 'Converting Yoast placeholders to PRC tokens in SEO data%s…', $dry_run ? ' (dry-run)' : '' ) );

		$stats = array(
			'posts_scanned' => 0,
			'posts_cleaned' => 0,
			'terms_scanned' => 0,
			'terms_cleaned' => 0,
		);

		$this->start_bulk_operation();

		$paged = 1;
		do {
			$query_args = array(
				'post_status'    => 'any',
				'posts_per_page' => $batch_size,
				'paged'          => $paged,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => Yoast_Migrator::PRC_POST_META_KEY,
						'compare' => 'EXISTS',
					),
				),
			);

			if ( ! empty( $post_type ) ) {
				$query_args['post_type'] = $post_type;
			} else {
				$query_args['post_type'] = 'any';
			}

			$posts = get_posts( $query_args );

			foreach ( $posts as $post ) {
				++$stats['posts_scanned'];
				$data    = get_post_meta( $post->ID, Yoast_Migrator::PRC_POST_META_KEY, true );
				$changed = false;

				if ( ! is_array( $data ) ) {
					continue;
				}

				foreach ( $text_fields as $field ) {
					if ( empty( $data[ $field ] ) || ! is_string( $data[ $field ] ) ) {
						continue;
					}
					if ( preg_match( '/%%[a-z0-9_]+%%/i', $data[ $field ] ) ) {
						$original       = $data[ $field ];
						$converted      = Yoast_Migrator::convert_yoast_tokens( $data[ $field ] );
						$data[ $field ] = $converted;
						$changed        = true;

						WP_CLI::line(
							sprintf(
								'Post %d [%s]: "%s" → "%s"',
								$post->ID,
								$field,
								$original,
								$converted
							)
						);
					}
				}

				if ( $changed ) {
					++$stats['posts_cleaned'];
					if ( ! $dry_run ) {
						update_post_meta( $post->ID, Yoast_Migrator::PRC_POST_META_KEY, $data );
						wp_cache_delete( 'seo_data_' . $post->ID, Metadata::CACHE_GROUP );
					}
				}
			}

			$posts_count = count( $posts );

			if ( 0 === $paged % 5 ) {
				sleep( 1 );
				$this->vip_inmemory_cleanup();
			}
			++$paged;

		} while ( $posts_count === $batch_size );

		// Process terms if requested.
		if ( $include_terms ) {
			WP_CLI::line( '' );
			WP_CLI::line( 'Scanning term SEO data…' );

			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$term_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = %s",
					Yoast_Migrator::PRC_TERM_META_KEY
				)
			);

			foreach ( $term_rows as $row ) {
				++$stats['terms_scanned'];
				$data    = maybe_unserialize( $row->meta_value );
				$changed = false;

				if ( ! is_array( $data ) ) {
					continue;
				}

				$term_text_fields = array( 'title', 'description' );
				foreach ( $term_text_fields as $field ) {
					if ( empty( $data[ $field ] ) || ! is_string( $data[ $field ] ) ) {
						continue;
					}
					if ( preg_match( '/%%[a-z0-9_]+%%/i', $data[ $field ] ) ) {
						$original      = $data[ $field ];
						$converted     = Yoast_Migrator::convert_yoast_tokens( $data[ $field ] );
						$data[ $field ] = $converted;
						$changed       = true;

						WP_CLI::line(
							sprintf(
								'Term %d [%s]: "%s" → "%s"',
								$row->term_id,
								$field,
								$original,
								$converted
							)
						);
					}
				}

				if ( $changed ) {
					++$stats['terms_cleaned'];
					if ( ! $dry_run ) {
						update_term_meta( $row->term_id, Yoast_Migrator::PRC_TERM_META_KEY, $data );
					}
				}
			}
		}

		$this->end_bulk_operation();

		WP_CLI::line( '' );
		WP_CLI::line( '=== Cleanup Results ===' );
		WP_CLI::line( sprintf( 'Posts scanned:  %d', $stats['posts_scanned'] ) );
		WP_CLI::line( sprintf( 'Posts cleaned:  %d', $stats['posts_cleaned'] ) );

		if ( $include_terms ) {
			WP_CLI::line( sprintf( 'Terms scanned:  %d', $stats['terms_scanned'] ) );
			WP_CLI::line( sprintf( 'Terms cleaned:  %d', $stats['terms_cleaned'] ) );
		}

		$total_cleaned = $stats['posts_cleaned'] + $stats['terms_cleaned'];
		if ( 0 === $total_cleaned ) {
			WP_CLI::success( 'No Yoast placeholders found. Data is clean.' );
		} else {
			WP_CLI::success(
				$dry_run
					? sprintf( '%d items would be cleaned.', $total_cleaned )
					: sprintf( '%d items cleaned successfully.', $total_cleaned )
			);
		}
	}

	/**
	 * Parse --dry-run from $assoc_args safely.
	 *
	 * WP-CLI passes flag values as strings. Casting (bool) 'false' === true,
	 * so we must compare the string value explicitly.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return bool
	 */
	private function parse_dry_run( array $assoc_args ): bool {
		if ( ! isset( $assoc_args['dry-run'] ) ) {
			return true;
		}
		if ( 'false' === $assoc_args['dry-run'] ) {
			return false;
		}
		return (bool) $assoc_args['dry-run'];
	}
}
