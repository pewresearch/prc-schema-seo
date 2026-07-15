<?php
/**
 * Yoast SEO Migrator class.
 *
 * Handles migration of Yoast SEO metadata to PRC Schema SEO format.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Yoast_Migrator class.
 *
 * Provides methods to migrate Yoast SEO post meta and term meta to the
 * PRC Schema SEO _prc_seo_data and _prc_seo_term_data formats.
 */
class Yoast_Migrator {

	/**
	 * Yoast post meta keys to migrate.
	 *
	 * @var array
	 */
	const YOAST_POST_META_KEYS = array(
		'title'               => '_yoast_wpseo_title',
		'description'         => '_yoast_wpseo_metadesc',
		'og_title'            => '_yoast_wpseo_opengraph-title',
		'og_description'      => '_yoast_wpseo_opengraph-description',
		'og_image'            => '_yoast_wpseo_opengraph-image-id',
		'twitter_title'       => '_yoast_wpseo_twitter-title',
		'twitter_description' => '_yoast_wpseo_twitter-description',
		'twitter_image'       => '_yoast_wpseo_twitter-image-id',
		'canonical_url'       => '_yoast_wpseo_canonical',
		'noindex'             => '_yoast_wpseo_meta-robots-noindex',
	);

	/**
	 * Yoast term meta keys to migrate.
	 *
	 * @var array
	 */
	const YOAST_TERM_META_KEYS = array(
		'title'       => 'wpseo_title',
		'description' => 'wpseo_desc',
		'noindex'     => 'wpseo_noindex',
		'og_image'    => 'wpseo_opengraph-image-id',
	);

	/**
	 * Additional taxonomies to check during Yoast migration.
	 * These supplement Primary_Term::get_supported_taxonomies() for migration purposes.
	 *
	 * @var array
	 */
	const MIGRATION_ADDITIONAL_TAXONOMIES = array(
		'collection',
		'regions-countries',
		'research-teams',
	);

	/**
	 * PRC SEO post meta key.
	 *
	 * @var string
	 */
	const PRC_POST_META_KEY = '_prc_seo_data';

	/**
	 * PRC SEO term meta key.
	 *
	 * @var string
	 */
	const PRC_TERM_META_KEY = '_prc_seo_term_data';

	/**
	 * Yoast options stored in wp_options (not postmeta).
	 *
	 * Safe to delete after post/term SEO migration to PRC Schema SEO is complete.
	 * Redirect options (e.g. wpseo-premium-redirects-*) are intentionally omitted —
	 * Schema SEO does not migrate redirects (Safe Redirect Manager owns that).
	 *
	 * @var array<int, string>
	 */
	const YOAST_OPTION_KEYS = array(
		'wpseo',
		'wpseo_titles',
		'wpseo_taxonomy_meta',
	);

	/**
	 * Yoast %%token%% to PRC %token% mapping.
	 *
	 * Tokens mapped to '' are stripped (%%sitename%%, %%sep%% - template adds these).
	 * Used by migrate and clean-yoast-placeholders CLI.
	 *
	 * @var array<string, string>
	 */
	const YOAST_TO_PRC_TOKEN_MAP = array(
		'%%title%%'             => '%post_title%',
		'%%sitename%%'          => '',
		'%%sep%%'               => '',
		'%%primary_category%%'  => '%primary_category%',
		'%%excerpt%%'           => '%post_excerpt%',
		'%%excerpt_only%%'      => '%post_excerpt%',
		'%%date%%'              => '%post_date%',
		'%%name%%'              => '%author%',
		'%%category%%'          => '%categories%',
		'%%tag%%'               => '%tags%',
		'%%currentyear%%'       => '%year%',
		'%%page%%'              => '%page%',
		'%%pagenumber%%'        => '%page_number%',
		'%%pagetotal%%'         => '%page_total%',
		'%%pt_single%%'         => '%post_type%',
		'%%term_title%%'        => '%term_name%',
		'%%term_description%%'  => '%term_description%',
		'%%searchphrase%%'      => '%search_query%',
	);

	/**
	 * Map of SEO text fields to the bare PRC token that is redundant for that field.
	 *
	 * A field whose entire value is its redundant token (e.g. a description of just
	 * %post_excerpt%, produced by migrating Yoast's %%excerpt%%) is equivalent to PRC's
	 * empty -> default fallback. The cleanup CLI uses this map to normalize already-migrated data.
	 *
	 * @var array<string, string>
	 */
	const REDUNDANT_FIELD_TOKENS = array(
		'title'               => '%post_title%',
		'og_title'            => '%post_title%',
		'twitter_title'       => '%post_title%',
		'description'         => '%post_excerpt%',
		'og_description'      => '%post_excerpt%',
		'twitter_description' => '%post_excerpt%',
	);

	/**
	 * Determine whether a converted field value is a bare, redundant default token.
	 *
	 * @param string $field Field key (e.g. 'description').
	 * @param string $value Converted field value.
	 * @return bool True when the value is only the redundant token for that field.
	 */
	public static function is_redundant_default_token( string $field, string $value ): bool {
		if ( ! isset( self::REDUNDANT_FIELD_TOKENS[ $field ] ) ) {
			return false;
		}
		return trim( $value ) === self::REDUNDANT_FIELD_TOKENS[ $field ];
	}

	/**
	 * Convert Yoast %%token%% placeholders to PRC %token% equivalents.
	 *
	 * Tokens in YOAST_TO_PRC_TOKEN_MAP are converted; %%sitename%% and %%sep%% are stripped.
	 * Unknown %%...%% tokens are stripped. First removes "%%sep%% Pew Research Center" pattern.
	 *
	 * @param string $text Text potentially containing Yoast placeholders.
	 * @return string Converted text.
	 */
	public static function convert_yoast_tokens( string $text ): string {
		if ( empty( $text ) ) {
			return $text;
		}

		// Remove "%%sep%% <site name>" (with optional spaces).
		$org_name = apply_filters( 'prc_schema_seo_organization_name', 'Pew Research Center' );
		$text     = preg_replace( '/\s*%%sep%%\s*' . preg_quote( $org_name, '/' ) . '/i', '', $text );

		// Apply token mapping (case-insensitive for Yoast token names).
		foreach ( self::YOAST_TO_PRC_TOKEN_MAP as $yoast => $prc ) {
			$text = preg_replace( '/\s*' . preg_quote( $yoast, '/' ) . '\s*/i', $prc ? ( ' ' . $prc . ' ' ) : ' ', $text );
		}

		// Strip any remaining %%...%% placeholders.
		$text = preg_replace( '/\s*%%[a-z0-9_]+%%\s*/i', ' ', $text );

		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Get the list of taxonomies that support primary terms for migration.
	 *
	 * Combines Primary_Term::get_supported_taxonomies() with additional
	 * migration-specific taxonomies that Yoast may have tracked.
	 *
	 * @return array Array of taxonomy slugs.
	 */
	public function get_supported_primary_term_taxonomies(): array {
		// Start with the standard supported taxonomies.
		$taxonomies = Primary_Term::get_supported_taxonomies();

		// Add migration-specific taxonomies that Yoast may have tracked.
		$taxonomies = array_unique( array_merge( $taxonomies, self::MIGRATION_ADDITIONAL_TAXONOMIES ) );

		/**
		 * Filter the supported primary term taxonomies for migration.
		 *
		 * @param array $taxonomies List of taxonomy slugs.
		 */
		return apply_filters( 'prc_schema_seo_yoast_primary_term_taxonomies', $taxonomies );
	}

	/**
	 * Get Yoast SEO data for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Yoast SEO data.
	 */
	public function get_yoast_post_data( int $post_id ): array {
		$data = array();

		// Get standard Yoast meta fields.
		foreach ( self::YOAST_POST_META_KEYS as $prc_key => $yoast_key ) {
			$value = get_post_meta( $post_id, $yoast_key, true );
			if ( '' !== $value && null !== $value ) {
				$data[ $prc_key ] = $value;
			}
		}

		// Get primary terms for supported taxonomies.
		$primary_terms = array();
		foreach ( $this->get_supported_primary_term_taxonomies() as $taxonomy ) {
			$yoast_primary_key = '_yoast_wpseo_primary_' . $taxonomy;
			$primary_term_id   = get_post_meta( $post_id, $yoast_primary_key, true );
			if ( $primary_term_id ) {
				$primary_terms[ $taxonomy ] = absint( $primary_term_id );
			}
		}
		if ( ! empty( $primary_terms ) ) {
			$data['primary_terms'] = $primary_terms;
		}

		return $data;
	}

	/**
	 * Get Yoast SEO data for a term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return array Yoast SEO term data.
	 */
	public function get_yoast_term_data( int $term_id, string $taxonomy ): array {
		$data = array();

		// Yoast stores taxonomy meta in the 'wpseo_taxonomy_meta' option.
		$yoast_taxonomy_meta = get_option( 'wpseo_taxonomy_meta', array() );

		if ( ! isset( $yoast_taxonomy_meta[ $taxonomy ][ $term_id ] ) ) {
			return $data;
		}

		$term_meta = $yoast_taxonomy_meta[ $taxonomy ][ $term_id ];

		foreach ( self::YOAST_TERM_META_KEYS as $prc_key => $yoast_key ) {
			if ( isset( $term_meta[ $yoast_key ] ) && '' !== $term_meta[ $yoast_key ] ) {
				$data[ $prc_key ] = $term_meta[ $yoast_key ];
			}
		}

		return $data;
	}

	/**
	 * Transform Yoast post data to PRC Schema SEO format.
	 *
	 * @param array $yoast_data Yoast SEO data.
	 * @return array PRC Schema SEO formatted data.
	 */
	private function transform_post_data( array $yoast_data ): array {
		$prc_data = array();

		// Title and description fields: convert Yoast tokens to PRC equivalents.
		$text_fields = array( 'title', 'og_title', 'twitter_title', 'description', 'og_description', 'twitter_description' );
		foreach ( $text_fields as $field ) {
			if ( isset( $yoast_data[ $field ] ) && '' !== $yoast_data[ $field ] ) {
				$prc_data[ $field ] = self::convert_yoast_tokens( $yoast_data[ $field ] );
			}
		}

		// Direct mappings for non-text fields.
		$direct_fields = array(
			'canonical_url',
			'primary_terms',
		);

		foreach ( $direct_fields as $field ) {
			if ( isset( $yoast_data[ $field ] ) && '' !== $yoast_data[ $field ] ) {
				$prc_data[ $field ] = $yoast_data[ $field ];
			}
		}

		// Image IDs need to be integers.
		if ( isset( $yoast_data['og_image'] ) && '' !== $yoast_data['og_image'] ) {
			$prc_data['og_image'] = absint( $yoast_data['og_image'] );
		}
		if ( isset( $yoast_data['twitter_image'] ) && '' !== $yoast_data['twitter_image'] ) {
			$prc_data['twitter_image'] = absint( $yoast_data['twitter_image'] );
		}

		// Noindex: Yoast uses '1' for noindex, '2' for index, empty for default.
		if ( isset( $yoast_data['noindex'] ) ) {
			$prc_data['noindex'] = '1' === $yoast_data['noindex'];
		}

		return $prc_data;
	}

	/**
	 * Transform Yoast term data to PRC Schema SEO format.
	 *
	 * @param array $yoast_data Yoast SEO term data.
	 * @return array PRC Schema SEO formatted term data.
	 */
	private function transform_term_data( array $yoast_data ): array {
		$prc_data = array();

		// Title and description: convert Yoast tokens to PRC equivalents.
		if ( isset( $yoast_data['title'] ) && '' !== $yoast_data['title'] ) {
			$prc_data['title'] = self::convert_yoast_tokens( $yoast_data['title'] );
		}
		if ( isset( $yoast_data['description'] ) && '' !== $yoast_data['description'] ) {
			$prc_data['description'] = self::convert_yoast_tokens( $yoast_data['description'] );
		}

		// Image ID.
		if ( isset( $yoast_data['og_image'] ) && '' !== $yoast_data['og_image'] ) {
			$prc_data['og_image'] = absint( $yoast_data['og_image'] );
		}

		// Noindex: Yoast uses 'noindex' string value.
		if ( isset( $yoast_data['noindex'] ) && 'noindex' === $yoast_data['noindex'] ) {
			$prc_data['noindex'] = true;
		}

		return $prc_data;
	}

	/**
	 * Migrate Yoast SEO data for a single post.
	 *
	 * @param int  $post_id       Post ID to migrate.
	 * @param bool $dry_run       If true, returns data without saving.
	 * @param bool $skip_existing If true, skip posts that already have PRC SEO data.
	 * @return array Migration result with 'success', 'data', 'message', and 'skipped' keys.
	 */
	public function migrate_post( int $post_id, bool $dry_run = false, bool $skip_existing = false ): array {
		$result = array(
			'success' => false,
			'data'    => array(),
			'message' => '',
			'skipped' => false,
		);

		// Check if post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			$result['message'] = sprintf( 'Post ID %d does not exist.', $post_id );
			return $result;
		}

		// Check for existing PRC SEO data if skip_existing is true.
		if ( $skip_existing ) {
			$existing = get_post_meta( $post_id, self::PRC_POST_META_KEY, true );
			if ( ! empty( $existing ) && is_array( $existing ) ) {
				$result['success'] = true;
				$result['skipped'] = true;
				$result['message'] = sprintf( 'Post ID %d already has PRC SEO data. Skipped.', $post_id );
				$result['data']    = $existing;
				return $result;
			}
		}

		// Get Yoast data.
		$yoast_data = $this->get_yoast_post_data( $post_id );

		if ( empty( $yoast_data ) ) {
			$result['success'] = true;
			$result['skipped'] = true;
			$result['message'] = sprintf( 'Post ID %d has no Yoast SEO data to migrate.', $post_id );
			return $result;
		}

		// Transform to PRC format.
		$prc_data = $this->transform_post_data( $yoast_data );

		if ( empty( $prc_data ) ) {
			$result['success'] = true;
			$result['skipped'] = true;
			$result['message'] = sprintf( 'Post ID %d Yoast data resulted in empty PRC data.', $post_id );
			return $result;
		}

		$result['data'] = $prc_data;

		if ( $dry_run ) {
			$result['success'] = true;
			$result['message'] = sprintf( 'Post ID %d would be migrated (dry run).', $post_id );
			return $result;
		}

		// Merge with existing PRC data (if any).
		$existing = get_post_meta( $post_id, self::PRC_POST_META_KEY, true );
		if ( is_array( $existing ) ) {
			$prc_data = array_merge( $existing, $prc_data );
		}

		// Save the data.
		$updated = update_post_meta( $post_id, self::PRC_POST_META_KEY, $prc_data );

		if ( false === $updated ) {
			$result['message'] = sprintf( 'Failed to update post meta for post ID %d.', $post_id );
			return $result;
		}

		// Clear caches.
		wp_cache_delete( 'seo_data_' . $post_id, Metadata::CACHE_GROUP );

		$result['success'] = true;
		$result['message'] = sprintf( 'Post ID %d migrated successfully.', $post_id );
		$result['data']    = $prc_data;

		return $result;
	}

	/**
	 * Migrate Yoast SEO data for a single term.
	 *
	 * @param int    $term_id       Term ID to migrate.
	 * @param string $taxonomy      Taxonomy slug.
	 * @param bool   $dry_run       If true, returns data without saving.
	 * @param bool   $skip_existing If true, skip terms that already have PRC SEO data.
	 * @return array Migration result with 'success', 'data', 'message', and 'skipped' keys.
	 */
	public function migrate_term( int $term_id, string $taxonomy, bool $dry_run = false, bool $skip_existing = false ): array {
		$result = array(
			'success' => false,
			'data'    => array(),
			'message' => '',
			'skipped' => false,
		);

		// Check if term exists.
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			$result['message'] = sprintf( 'Term ID %d in taxonomy "%s" does not exist.', $term_id, $taxonomy );
			return $result;
		}

		// Check for existing PRC SEO data if skip_existing is true.
		if ( $skip_existing ) {
			$existing = get_term_meta( $term_id, self::PRC_TERM_META_KEY, true );
			if ( ! empty( $existing ) && is_array( $existing ) ) {
				$result['success'] = true;
				$result['skipped'] = true;
				$result['message'] = sprintf( 'Term ID %d already has PRC SEO data. Skipped.', $term_id );
				$result['data']    = $existing;
				return $result;
			}
		}

		// Get Yoast data.
		$yoast_data = $this->get_yoast_term_data( $term_id, $taxonomy );

		if ( empty( $yoast_data ) ) {
			$result['success'] = true;
			$result['skipped'] = true;
			$result['message'] = sprintf( 'Term ID %d has no Yoast SEO data to migrate.', $term_id );
			return $result;
		}

		// Transform to PRC format.
		$prc_data = $this->transform_term_data( $yoast_data );

		if ( empty( $prc_data ) ) {
			$result['success'] = true;
			$result['skipped'] = true;
			$result['message'] = sprintf( 'Term ID %d Yoast data resulted in empty PRC data.', $term_id );
			return $result;
		}

		$result['data'] = $prc_data;

		if ( $dry_run ) {
			$result['success'] = true;
			$result['message'] = sprintf( 'Term ID %d would be migrated (dry run).', $term_id );
			return $result;
		}

		// Merge with existing PRC data (if any).
		$existing = get_term_meta( $term_id, self::PRC_TERM_META_KEY, true );
		if ( is_array( $existing ) ) {
			$prc_data = array_merge( $existing, $prc_data );
		}

		// Save the data.
		$updated = update_term_meta( $term_id, self::PRC_TERM_META_KEY, $prc_data );

		if ( false === $updated ) {
			$result['message'] = sprintf( 'Failed to update term meta for term ID %d.', $term_id );
			return $result;
		}

		// Clear caches.
		wp_cache_delete( 'term_schema_' . $term_id, Generator::CACHE_GROUP );
		Meta_Tags::clear_term_meta_tags_cache( $term_id );

		$result['success'] = true;
		$result['message'] = sprintf( 'Term ID %d migrated successfully.', $term_id );
		$result['data']    = $prc_data;

		return $result;
	}

	/**
	 * Migrate Yoast SEO data for multiple posts in batches.
	 *
	 * @param array $args {
	 *     Optional. Arguments for batch migration.
	 *
	 *     @type string $post_type     Post type to migrate. Default 'post'.
	 *     @type int    $batch_size    Number of posts per batch. Default 100.
	 *     @type int    $offset        Starting offset. Default 0.
	 *     @type int    $limit         Maximum posts to process. Default 0 (no limit).
	 *     @type string $post_status   Post status to query. Default 'any'.
	 * }
	 * @param bool  $dry_run       If true, returns data without saving.
	 * @param bool  $skip_existing If true, skip posts that already have PRC SEO data.
	 * @return array Migration statistics.
	 */
	public function migrate_posts_batch( array $args = array(), bool $dry_run = false, bool $skip_existing = true ): array {
		$defaults = array(
			'post_type'   => 'post',
			'batch_size'  => 100,
			'offset'      => 0,
			'limit'       => 0,
			'post_status' => 'any',
		);

		$args = wp_parse_args( $args, $defaults );

		$stats = array(
			'total'     => 0,
			'migrated'  => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'processed' => 0,
			'details'   => array(),
		);

		$query_args = array(
			'post_type'      => $args['post_type'],
			'post_status'    => $args['post_status'],
			'posts_per_page' => $args['batch_size'],
			'offset'         => $args['offset'],
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'relation' => 'OR',
					array(
						'key'     => '_yoast_wpseo_title',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_yoast_wpseo_metadesc',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_yoast_wpseo_canonical',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_yoast_wpseo_meta-robots-noindex',
						'compare' => 'EXISTS',
					),
				),
			),
		);

		$query          = new \WP_Query( $query_args );
		$stats['total'] = $query->found_posts;

		$posts_to_process = $args['limit'] > 0 ? min( $args['limit'], $stats['total'] ) : $stats['total'];

		while ( $query->have_posts() && $stats['processed'] < $posts_to_process ) {
			foreach ( $query->posts as $post_id ) {
				if ( $stats['processed'] >= $posts_to_process ) {
					break;
				}

				$result = $this->migrate_post( $post_id, $dry_run, $skip_existing );
				++$stats['processed'];

				if ( ! $result['success'] ) {
					++$stats['failed'];
					$stats['details'][] = array(
						'post_id' => $post_id,
						'status'  => 'failed',
						'message' => $result['message'],
					);
				} elseif ( $result['skipped'] ) {
					++$stats['skipped'];
				} else {
					++$stats['migrated'];
				}
			}

			// Get next batch.
			$query_args['offset'] += $args['batch_size'];
			$query                 = new \WP_Query( $query_args );
		}

		return $stats;
	}

	/**
	 * Migrate Yoast SEO data for all terms.
	 *
	 * @param string|null $taxonomy      Taxonomy to migrate. Null for all taxonomies.
	 * @param bool        $dry_run       If true, returns data without saving.
	 * @param bool        $skip_existing If true, skip terms that already have PRC SEO data.
	 * @return array Migration statistics.
	 */
	public function migrate_terms_batch( ?string $taxonomy = null, bool $dry_run = false, bool $skip_existing = true ): array {
		$stats = array(
			'total'     => 0,
			'migrated'  => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'processed' => 0,
			'details'   => array(),
		);

		// Get Yoast taxonomy meta.
		$yoast_taxonomy_meta = get_option( 'wpseo_taxonomy_meta', array() );

		if ( empty( $yoast_taxonomy_meta ) ) {
			return $stats;
		}

		// If specific taxonomy is provided, filter to just that one.
		if ( null !== $taxonomy ) {
			if ( isset( $yoast_taxonomy_meta[ $taxonomy ] ) ) {
				$yoast_taxonomy_meta = array( $taxonomy => $yoast_taxonomy_meta[ $taxonomy ] );
			} else {
				return $stats;
			}
		}

		// Count total terms.
		foreach ( $yoast_taxonomy_meta as $tax_slug => $terms ) {
			$stats['total'] += count( $terms );
		}

		// Process each taxonomy and term.
		foreach ( $yoast_taxonomy_meta as $tax_slug => $terms ) {
			foreach ( $terms as $term_id => $term_data ) {
				$result = $this->migrate_term( $term_id, $tax_slug, $dry_run, $skip_existing );
				++$stats['processed'];

				if ( ! $result['success'] ) {
					++$stats['failed'];
					$stats['details'][] = array(
						'term_id'  => $term_id,
						'taxonomy' => $tax_slug,
						'status'   => 'failed',
						'message'  => $result['message'],
					);
				} elseif ( $result['skipped'] ) {
					++$stats['skipped'];
				} else {
					++$stats['migrated'];
				}
			}
		}

		return $stats;
	}

	/**
	 * Get migration status/statistics.
	 *
	 * @return array Migration status with counts.
	 */
	public function get_migration_status(): array {
		global $wpdb;

		$status = array(
			'posts' => array(
				'with_yoast_meta'   => 0,
				'with_prc_meta'     => 0,
				'pending_migration' => 0,
			),
			'terms' => array(
				'with_yoast_meta'   => 0,
				'with_prc_meta'     => 0,
				'pending_migration' => 0,
			),
		);

		// Count posts with Yoast meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$yoast_post_count                   = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			WHERE meta_key LIKE '_yoast_wpseo_%'"
		);
		$status['posts']['with_yoast_meta'] = (int) $yoast_post_count;

		// Count posts with PRC meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$prc_post_count                   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::PRC_POST_META_KEY
			)
		);
		$status['posts']['with_prc_meta'] = (int) $prc_post_count;

		// Pending = Yoast posts without PRC data.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending_posts                        = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm1.post_id)
				FROM {$wpdb->postmeta} pm1
				LEFT JOIN {$wpdb->postmeta} pm2
					ON pm1.post_id = pm2.post_id
					AND pm2.meta_key = %s
				WHERE pm1.meta_key LIKE '_yoast_wpseo_%%'
				AND pm2.post_id IS NULL",
				self::PRC_POST_META_KEY
			)
		);
		$status['posts']['pending_migration'] = (int) $pending_posts;

		// Count terms with Yoast meta (from options). Deduplicate term IDs across taxonomies.
		$yoast_taxonomy_meta = get_option( 'wpseo_taxonomy_meta', array() );
		$yoast_term_ids      = array();
		if ( is_array( $yoast_taxonomy_meta ) ) {
			foreach ( $yoast_taxonomy_meta as $terms ) {
				if ( ! is_array( $terms ) ) {
					continue;
				}
				foreach ( array_keys( $terms ) as $term_id ) {
					$term_id = (int) $term_id;
					if ( $term_id > 0 ) {
						$yoast_term_ids[ $term_id ] = $term_id;
					}
				}
			}
		}
		$yoast_term_count                   = count( $yoast_term_ids );
		$status['terms']['with_yoast_meta'] = $yoast_term_count;

		// Count terms with PRC meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$prc_term_count                   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT term_id) FROM {$wpdb->termmeta} WHERE meta_key = %s",
				self::PRC_TERM_META_KEY
			)
		);
		$status['terms']['with_prc_meta'] = (int) $prc_term_count;

		// Pending terms = Yoast terms missing PRC term meta (true left-join, not count subtraction).
		$pending_terms = 0;
		if ( $yoast_term_count > 0 ) {
			$term_ids     = array_values( $yoast_term_ids );
			$placeholders = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$migrated_term_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT term_id FROM {$wpdb->termmeta}
					WHERE meta_key = %s AND term_id IN ({$placeholders})",
					self::PRC_TERM_META_KEY,
					...$term_ids
				)
			);
			$pending_terms = $yoast_term_count - count( $migrated_term_ids );
		}
		$status['terms']['pending_migration'] = $pending_terms;

		return $status;
	}
}
