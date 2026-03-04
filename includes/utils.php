<?php
/**
 * Utility functions.
 *
 * @package    PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO\Utils;

/**
 * This is a place for "utility" functions.
 * These are functions meant to be consumed both by this plugin and others, easily.
 */

/**
 * Migrate Yoast SEO data for a single post to PRC Schema SEO format.
 *
 * This function allows programmatic migration of Yoast SEO metadata
 * to the PRC Schema SEO _prc_seo_data format.
 *
 * @param int  $post_id  Post ID to migrate.
 * @param bool $dry_run  If true, returns data without saving. Default false.
 * @return array {
 *     Migration result.
 *
 *     @type bool   $success Whether the migration was successful.
 *     @type array  $data    The migrated SEO data.
 *     @type string $message Description of the migration result.
 *     @type bool   $skipped Whether the post was skipped (no data to migrate or already exists).
 * }
 *
 * @example
 * // Migrate a single post
 * $result = \PRC\Platform\Schema_SEO\Utils\migrate_yoast_post( 123 );
 * if ( $result['success'] && ! $result['skipped'] ) {
 *     echo 'Post migrated successfully!';
 * }
 *
 * // Preview migration without saving
 * $result = \PRC\Platform\Schema_SEO\Utils\migrate_yoast_post( 123, true );
 * print_r( $result['data'] );
 */
function migrate_yoast_post( int $post_id, bool $dry_run = false ): array {
	$migrator = new \PRC\Platform\Schema_SEO\Yoast_Migrator();
	return $migrator->migrate_post( $post_id, $dry_run, false );
}

/**
 * Migrate Yoast SEO data for a single term to PRC Schema SEO format.
 *
 * This function allows programmatic migration of Yoast SEO term metadata
 * to the PRC Schema SEO _prc_seo_term_data format.
 *
 * @param int    $term_id  Term ID to migrate.
 * @param string $taxonomy Taxonomy slug.
 * @param bool   $dry_run  If true, returns data without saving. Default false.
 * @return array {
 *     Migration result.
 *
 *     @type bool   $success Whether the migration was successful.
 *     @type array  $data    The migrated SEO data.
 *     @type string $message Description of the migration result.
 *     @type bool   $skipped Whether the term was skipped (no data to migrate or already exists).
 * }
 *
 * @example
 * // Migrate a single term
 * $result = \PRC\Platform\Schema_SEO\Utils\migrate_yoast_term( 55, 'category' );
 * if ( $result['success'] && ! $result['skipped'] ) {
 *     echo 'Term migrated successfully!';
 * }
 */
function migrate_yoast_term( int $term_id, string $taxonomy, bool $dry_run = false ): array {
	$migrator = new \PRC\Platform\Schema_SEO\Yoast_Migrator();
	return $migrator->migrate_term( $term_id, $taxonomy, $dry_run, false );
}

/**
 * Get Yoast SEO data for a post without migrating.
 *
 * Useful for inspecting what Yoast data exists before migration.
 *
 * @param int $post_id Post ID.
 * @return array Yoast SEO data found for the post.
 *
 * @example
 * // Preview Yoast data
 * $yoast_data = \PRC\Platform\Schema_SEO\Utils\get_yoast_post_data( 123 );
 * if ( ! empty( $yoast_data ) ) {
 *     print_r( $yoast_data );
 * }
 */
function get_yoast_post_data( int $post_id ): array {
	$migrator = new \PRC\Platform\Schema_SEO\Yoast_Migrator();
	return $migrator->get_yoast_post_data( $post_id );
}

/**
 * Get migration status statistics.
 *
 * Returns counts of posts and terms with Yoast data, PRC data,
 * and pending migration.
 *
 * @return array {
 *     Migration status.
 *
 *     @type array $posts {
 *         @type int $with_yoast_meta   Posts with Yoast SEO meta.
 *         @type int $with_prc_meta     Posts with PRC SEO meta.
 *         @type int $pending_migration Posts needing migration.
 *     }
 *     @type array $terms {
 *         @type int $with_yoast_meta   Terms with Yoast SEO meta.
 *         @type int $with_prc_meta     Terms with PRC SEO meta.
 *         @type int $pending_migration Terms needing migration.
 *     }
 * }
 */
function get_migration_status(): array {
	$migrator = new \PRC\Platform\Schema_SEO\Yoast_Migrator();
	return $migrator->get_migration_status();
}

/**
 * Get the expertise area ID linked to a category term.
 *
 * Retrieves the expertise_id from the category's _prc_seo_term_data meta.
 *
 * @param int $category_term_id Category term ID.
 * @return int The expertise area term ID, or 0 if not set.
 *
 * @example
 * // Get expertise area for a category
 * $expertise_id = \PRC\Platform\Schema_SEO\Utils\get_category_expertise_id( 42 );
 * if ( $expertise_id ) {
 *     $expertise_term = get_term( $expertise_id, 'areas-of-expertise' );
 * }
 */
function get_category_expertise_id( int $category_term_id ): int {
	$meta = get_term_meta( $category_term_id, '_prc_seo_term_data', true );

	// Apply filter to allow overriding the expertise binding.
	$expertise_id = isset( $meta['expertise_id'] ) ? absint( $meta['expertise_id'] ) : 0;

	/**
	 * Filter the expertise area ID for a category.
	 *
	 * @param int $expertise_id      The expertise area term ID.
	 * @param int $category_term_id  The category term ID.
	 */
	return apply_filters( 'prc_schema_seo_category_expertise_id', $expertise_id, $category_term_id );
}

/**
 * Get the primary term ID for a post and taxonomy.
 *
 * Retrieves the primary term ID from the post's _prc_seo_data meta.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy slug (e.g., 'category', 'post_tag').
 * @return int|null Primary term ID or null if not set.
 *
 * @example
 * // Get primary category for a post
 * $primary_cat_id = \PRC\Platform\Schema_SEO\Utils\get_primary_term_id( 123, 'category' );
 * if ( $primary_cat_id ) {
 *     $category = get_term( $primary_cat_id, 'category' );
 * }
 */
function get_primary_term_id( int $post_id, string $taxonomy ): ?int {
	return \PRC\Platform\Schema_SEO\Primary_Term::get_id( $post_id, $taxonomy );
}
