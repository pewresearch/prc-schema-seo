<?php
/**
 * Cache_Invalidator class.
 *
 * Hooks into post lifecycle events to clear cached SEO and schema data.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Cache_Invalidator class.
 */
class Cache_Invalidator {
	/**
	 * Loader instance.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Metadata instance.
	 *
	 * @var Metadata
	 */
	protected $seo_metadata;

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader       = $loader;
		$this->seo_metadata = new Metadata( $loader );

		// Invalidate cache when a post is saved (including updates).
		$this->loader->add_action( 'save_post', $this, 'maybe_clear_cache', 10, 3 );
		// Invalidate cache when a post is deleted / trashed.
		$this->loader->add_action( 'deleted_post', $this, 'clear_cache_on_delete', 10, 1 );
		$this->loader->add_action( 'wp_trash_post', $this, 'clear_cache_on_delete', 10, 1 );
		// Invalidate contact cache when staff posts are deleted/trashed.
		$this->loader->add_action( 'deleted_post', $this, 'maybe_clear_contact_cache_on_staff_delete', 10, 1 );
		$this->loader->add_action( 'wp_trash_post', $this, 'maybe_clear_contact_cache_on_staff_delete', 10, 1 );
		// Invalidate cache when art direction metadata updates (social images).
		$this->loader->add_action( 'updated_post_meta', $this, 'maybe_clear_cache_on_art_direction_update', 10, 4 );

		// Invalidate contact cache when staff employment status changes (staff-type taxonomy).
		$this->loader->add_action( 'set_object_terms', $this, 'maybe_clear_contact_cache_on_staff_type_change', 10, 6 );

		// Invalidate caches when term meta changes (expertise binding, contact staff).
		$this->loader->add_action( 'prc_schema_seo_term_meta_updated', $this, 'clear_cache_on_term_meta_update', 10, 2 );
	}

	/**
	 * Maybe clear the cache on save if post type is enabled.
	 *
	 * @param int      $post_id   Post ID.
	 * @param \WP_Post $post      Post object.
	 * @param bool     $update    Whether this is an existing post being updated.
	 */
	public function maybe_clear_cache( $post_id, $post, $update ) {
		// Autosave / revisions ignored.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! post_type_supports( $post_type, 'prc-schema-seo' ) ) {
			return;
		}

		$this->seo_metadata->clear_cache( $post_id );
	}

	/**
	 * Clear cache when a post is deleted or trashed.
	 *
	 * @param int $post_id Post ID.
	 */
	public function clear_cache_on_delete( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( $post_type && post_type_supports( $post_type, 'prc-schema-seo' ) ) {
			$this->seo_metadata->clear_cache( $post_id );
		}
	}

	/**
	 * Clear cache when art direction metadata is updated.
	 *
	 * @param int    $meta_id    ID of updated metadata entry.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function maybe_clear_cache_on_art_direction_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		// Only clear if artDirection meta key is updated.
		if ( 'artDirection' !== $meta_key ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( $post_type && post_type_supports( $post_type, 'prc-schema-seo' ) ) {
			$this->seo_metadata->clear_cache( $post_id );
		}
	}

	/**
	 * Clear contact cache when staff employment status changes (staff-type taxonomy).
	 *
	 * When a staff member's employment status changes (e.g., assigned to 'former-staff'),
	 * we need to invalidate contact caches for all expertise areas they were designated as contact for.
	 *
	 * @param int    $object_id  Object ID (staff post ID).
	 * @param array  $terms      Array of term IDs.
	 * @param array  $tt_ids     Array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $append     Whether to append terms.
	 * @param array  $old_tt_ids Old term taxonomy IDs.
	 * @return void
	 */
	public function maybe_clear_contact_cache_on_staff_type_change( $object_id, $terms = array(), $tt_ids = array(), $taxonomy = '', $append = false, $old_tt_ids = array() ) {
		// Only process staff-type taxonomy changes on staff posts.
		if ( 'staff-type' !== $taxonomy ) {
			return;
		}

		$post_type = get_post_type( $object_id );
		if ( 'staff' !== $post_type ) {
			return;
		}

		// Clear contact caches for all expertise areas this staff is assigned to.
		$this->clear_contact_caches_for_staff( $object_id );
	}

	/**
	 * Clear contact cache when staff posts are deleted or trashed.
	 *
	 * When a staff post is deleted or trashed, we need to invalidate contact caches
	 * for all expertise areas they were designated as contact for.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function maybe_clear_contact_cache_on_staff_delete( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( 'staff' !== $post_type ) {
			return;
		}

		// Clear contact caches for all expertise areas this staff is assigned to.
		$this->clear_contact_caches_for_staff( $post_id );
	}

	/**
	 * Clear contact caches for all expertise areas where a staff member is designated as contact.
	 *
	 * @param int $staff_id Staff post ID.
	 * @return void
	 */
	private function clear_contact_caches_for_staff( $staff_id ) {
		// Find all expertise areas where this staff is designated as contact.
		$expertise_terms = get_terms(
			array(
				'taxonomy'   => 'areas-of-expertise',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'     => Taxonomy_UI::TERM_META_KEY,
						'value'   => sprintf( '"contact_staff_id";i:%d', $staff_id ),
						'compare' => 'LIKE',
					),
				),
			)
		);

		if ( is_wp_error( $expertise_terms ) || empty( $expertise_terms ) ) {
			return;
		}

		// Clear contact caches for all expertise areas this staff is assigned to.
		foreach ( $expertise_terms as $expertise_term ) {
			Contact_Resolver::clear_cache_for_expertise( $expertise_term->term_id );
		}
	}

	/**
	 * Clear caches when term meta is updated (e.g., category-expertise binding, contact staff).
	 *
	 * @param int   $term_id Term ID.
	 * @param array $meta    Updated term meta.
	 */
	public function clear_cache_on_term_meta_update( $term_id, $meta ) {
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		// If category expertise binding changed, clear contact caches for related posts.
		if ( 'category' === $term->taxonomy && isset( $meta['expertise_id'] ) ) {
			Contact_Resolver::clear_cache_for_category( $term_id );
		}

		// If expertise area contact staff changed, clear contact caches for related posts.
		if ( 'areas-of-expertise' === $term->taxonomy && isset( $meta['contact_staff_id'] ) ) {
			Contact_Resolver::clear_cache_for_expertise( $term_id );
		}

		// Clear schema caches for the term itself.
		wp_cache_delete( Cache_Keys::term_schema( (int) $term_id ), Generator::CACHE_GROUP );
		Meta_Tags::clear_term_meta_tags_cache( $term_id );
		Parsely_Integration::clear_term_cache( (int) $term_id );
	}
}
