<?php
/**
 * Primary_Term class.
 *
 * Consolidated service for all primary term operations.
 * Provides a single source of truth for primary term configuration, retrieval,
 * validation, and sanitization across the plugin.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Primary_Term class.
 *
 * Static utility class for primary term operations. All methods are stateless
 * and can be called without instantiation.
 */
class Primary_Term {

	/**
	 * Default taxonomies that support primary term selection.
	 */
	const DEFAULT_TAXONOMIES = array( 'category' );

	/**
	 * Meta key for SEO data.
	 */
	const META_KEY = '_prc_seo_data';

	/**
	 * Get the list of taxonomies that support primary term selection.
	 *
	 * @return array Array of taxonomy slugs.
	 */
	public static function get_supported_taxonomies(): array {
		/**
		 * Filter the supported primary term taxonomies.
		 *
		 * @param array $taxonomies Array of taxonomy slugs that support primary term selection.
		 */
		return apply_filters( 'prc_schema_seo_primary_term_taxonomies', self::DEFAULT_TAXONOMIES );
	}

	/**
	 * Get the primary term ID for a post and taxonomy.
	 *
	 * Retrieves the primary term ID from the post's SEO data meta and applies
	 * the `prc_schema_seo_primary_term_id` filter for consistency.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug (e.g., 'category', 'post_tag').
	 * @param bool   $fallback Whether to fall back to first assigned term if no primary set. Default true.
	 * @return int|null Primary term ID or null if not set.
	 */
	public static function get_id( int $post_id, string $taxonomy, bool $fallback = true ): ?int {
		$map     = self::get_map( $post_id );
		$term_id = ! empty( $map[ $taxonomy ] ) ? absint( $map[ $taxonomy ] ) : 0;

		/**
		 * Filter the primary term ID for a taxonomy.
		 *
		 * @param int    $term_id  The primary term ID (0 if not set).
		 * @param string $taxonomy The taxonomy slug.
		 * @param int    $post_id  The post ID.
		 */
		$filtered_id = apply_filters( 'prc_schema_seo_primary_term_id', $term_id, $taxonomy, $post_id );

		if ( $filtered_id ) {
			return absint( $filtered_id );
		}

		// Fallback to first assigned term if requested.
		if ( $fallback ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				return absint( $terms[0] );
			}
		}

		return null;
	}

	/**
	 * Get the primary term object for a post and taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param bool   $fallback Whether to fall back to first assigned term if no primary set. Default true.
	 * @return \WP_Term|null Term object or null if not found.
	 */
	public static function get_term( int $post_id, string $taxonomy, bool $fallback = true ): ?\WP_Term {
		$term_id = self::get_id( $post_id, $taxonomy, $fallback );

		if ( ! $term_id ) {
			return null;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		return $term;
	}

	/**
	 * Get the primary term name for a post and taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param bool   $fallback Whether to fall back to first assigned term if no primary set. Default true.
	 * @return string Term name or empty string if not found.
	 */
	public static function get_name( int $post_id, string $taxonomy, bool $fallback = true ): string {
		$term = self::get_term( $post_id, $taxonomy, $fallback );
		return $term ? $term->name : '';
	}

	/**
	 * Get the complete primary terms map for a post.
	 *
	 * Returns an associative array of taxonomy => term_id from the post's SEO data.
	 *
	 * @param int $post_id Post ID.
	 * @return array Associative array of taxonomy slug => term ID.
	 */
	public static function get_map( int $post_id ): array {
		$seo_data = get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $seo_data ) ) {
			return array();
		}

		$primary_terms = $seo_data['primary_terms'] ?? array();

		return is_array( $primary_terms ) ? $primary_terms : array();
	}

	/**
	 * Validate primary term mappings.
	 *
	 * Checks that taxonomies exist and term IDs are valid for those taxonomies.
	 *
	 * @param array $primary_terms Associative array of taxonomy => term_id.
	 * @param int   $post_id       Post ID (available for future validation extensions).
	 * @return array Array of error messages. Empty if valid.
	 */
	public static function validate( array $primary_terms, int $post_id = 0 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Reserved for future use.
		$errors = array();

		foreach ( $primary_terms as $taxonomy => $term_id ) {
			$taxonomy = sanitize_key( $taxonomy );
			$term_id  = absint( $term_id );

			if ( ! taxonomy_exists( $taxonomy ) ) {
				/* translators: %s: taxonomy name */
				$errors[] = sprintf( __( 'Taxonomy "%s" does not exist for primary term.', 'prc-schema-seo' ), $taxonomy );
				continue;
			}

			if ( $term_id && ! get_term( $term_id, $taxonomy ) ) {
				/* translators: %1$d: term ID, %2$s: taxonomy name */
				$errors[] = sprintf( __( 'Primary term ID %1$d is invalid for taxonomy %2$s.', 'prc-schema-seo' ), $term_id, $taxonomy );
			}
		}

		return $errors;
	}

	/**
	 * Sanitize primary term mappings.
	 *
	 * Ensures taxonomy keys are sanitized and term IDs are integers.
	 *
	 * @param array $primary_terms Raw primary terms data.
	 * @return array Sanitized primary terms array.
	 */
	public static function sanitize( array $primary_terms ): array {
		$sanitized = array();

		foreach ( $primary_terms as $taxonomy => $term_id ) {
			$taxonomy               = sanitize_key( $taxonomy );
			$sanitized[ $taxonomy ] = absint( $term_id );
		}

		/**
		 * Filter sanitized primary terms mapping.
		 *
		 * @param array $sanitized Associative array taxonomy => term_id.
		 * @param array $primary_terms Original raw data.
		 */
		return apply_filters( 'prc_schema_seo_sanitized_primary_terms', $sanitized, $primary_terms );
	}

	/**
	 * Strip primary term mappings for terms not assigned to the post.
	 *
	 * Removes any primary term selections where the term is not actually
	 * assigned to the post in that taxonomy.
	 *
	 * @param array $primary_terms Associative array of taxonomy => term_id.
	 * @param int   $post_id       Post ID.
	 * @return array Filtered primary terms with unassigned terms removed.
	 */
	public static function strip_unassigned( array $primary_terms, int $post_id ): array {
		$filtered = array();

		foreach ( $primary_terms as $taxonomy => $term_id ) {
			$taxonomy = sanitize_key( $taxonomy );
			$term_id  = absint( $term_id );

			if ( ! $term_id || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$post_term_ids = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );

			if ( is_wp_error( $post_term_ids ) ) {
				continue;
			}

			if ( in_array( $term_id, $post_term_ids, true ) ) {
				$filtered[ $taxonomy ] = $term_id;
			}
		}

		return $filtered;
	}

	/**
	 * Remove primary term mappings that reference missing terms or taxonomies.
	 *
	 * @param array $primary_terms Associative array of taxonomy => term_id.
	 * @return array Filtered primary terms with invalid mappings removed.
	 */
	public static function strip_invalid( array $primary_terms ): array {
		$filtered = array();

		foreach ( $primary_terms as $taxonomy => $term_id ) {
			$taxonomy = sanitize_key( $taxonomy );
			$term_id  = absint( $term_id );

			if ( ! $term_id || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$term = get_term( $term_id, $taxonomy );

			if ( $term && ! is_wp_error( $term ) ) {
				$filtered[ $taxonomy ] = $term_id;
			}
		}

		return $filtered;
	}

	/**
	 * Normalize primary term mappings before persistence or validation.
	 *
	 * @param array $primary_terms Associative array of taxonomy => term_id.
	 * @param int   $post_id       Post ID.
	 * @return array Sanitized primary terms with stale mappings removed.
	 */
	public static function prepare_for_post( array $primary_terms, int $post_id ): array {
		return self::strip_invalid(
			self::strip_unassigned( $primary_terms, $post_id )
		);
	}

	/**
	 * Check if a taxonomy supports primary term selection.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool True if taxonomy supports primary terms.
	 */
	public static function is_supported_taxonomy( string $taxonomy ): bool {
		return in_array( $taxonomy, self::get_supported_taxonomies(), true );
	}
}
