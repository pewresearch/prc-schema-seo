<?php
/**
 * Contact_Resolver class.
 *
 * Resolves media contacts based on post primary category chain.
 * Post → Primary Category → Expertise Area → Staff Media Contact.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Contact_Resolver class.
 *
 * Provides a reusable service to resolve the contact chain from posts
 * through categories and expertise areas to designated staff contacts.
 */
class Contact_Resolver {

	/**
	 * Cache group for contact resolution.
	 */
	const CACHE_GROUP = 'prc_contact_resolver_03112026';

	/**
	 * Cache TTL (1 hour).
	 */
	const CACHE_TTL = 3600;

	/**
	 * Get media contact for a post based on primary category chain.
	 *
	 * Resolves contact through: Post → Primary Category → Expertise Area → Staff.
	 *
	 * @param int $post_id Post ID.
	 * @return array Contact data array with name, title, email, phone, url keys.
	 */
	public static function get_contact_for_post( $post_id ) {
		$cache_key = 'contact_' . $post_id;

		// Check cache only if caching is enabled.
		if ( ! defined( 'PRC_SCHEMA_SEO_DISABLE_CACHE' ) || ! PRC_SCHEMA_SEO_DISABLE_CACHE ) {
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$contact = null;

		// 1. Get primary category from SEO data.
		$primary_cat_id = \PRC\Platform\Schema_SEO\Utils\get_primary_term_id( $post_id, 'category' );
		if ( $primary_cat_id ) {
			// 2. Get expertise area from category.
			$expertise_id = \PRC\Platform\Schema_SEO\Utils\get_category_expertise_id( $primary_cat_id );
			if ( $expertise_id ) {
				// 3. Find staff designated as contact for this expertise.
				$contact = self::get_staff_for_expertise( $expertise_id );
			}
		}

		// Fall back to default if no contact found.
		if ( ! $contact ) {
			$contact = self::get_default_contact();
		}

		/**
		 * Filter the resolved contact data for a post.
		 *
		 * @param array $contact The contact data array.
		 * @param int   $post_id The post ID.
		 */
		$contact = apply_filters( 'prc_schema_seo_resolved_contact', $contact, $post_id );

		// Cache result only if caching is enabled.
		if ( ! defined( 'PRC_SCHEMA_SEO_DISABLE_CACHE' ) || ! PRC_SCHEMA_SEO_DISABLE_CACHE ) {
			wp_cache_set( $cache_key, $contact, self::CACHE_GROUP, self::CACHE_TTL );
		}

		return $contact;
	}

	/**
	 * Get staff contact data for an expertise area.
	 *
	 * @param int $expertise_id Expertise area term ID.
	 * @return array|null Contact data array or null if not found or staff not employed.
	 */
	private static function get_staff_for_expertise( $expertise_id ) {
		// Get contact_staff_id from expertise term meta.
		$term_meta = get_term_meta( $expertise_id, Taxonomy_UI::TERM_META_KEY, true );
		$term_meta = is_array( $term_meta ) ? $term_meta : array();
		$staff_id  = ! empty( $term_meta['contact_staff_id'] ) ? absint( $term_meta['contact_staff_id'] ) : 0;

		if ( ! $staff_id ) {
			return null;
		}

		// Verify staff post exists and is published.
		$staff_post = get_post( $staff_id );
		if ( ! $staff_post || 'staff' !== $staff_post->post_type || 'publish' !== $staff_post->post_status ) {
			return null;
		}

		// Verify staff is still employed.
		if ( class_exists( '\PRC\Platform\Staff_Bylines\Staff' ) ) {
			$staff = new \PRC\Platform\Staff_Bylines\Staff( $staff_id );
			if ( ! $staff->is_currently_employed ) {
				return null; // Falls back to default contact.
			}
		}

		// Build and return contact data.
		$staff_data = self::build_staff_contact_data( $staff_post );

		return $staff_data;
	}

	/**
	 * Build contact data array from a staff post.
	 *
	 * @param \WP_Post $staff_post Staff post object.
	 * @return array Contact data array.
	 */
	private static function build_staff_contact_data( $staff_post ) {
		$contact = array(
			'name'     => get_the_title( $staff_post ),
			'title'    => get_post_meta( $staff_post->ID, 'jobTitle', true ),
			'email'    => get_post_meta( $staff_post->ID, 'email', true ),
			'phone'    => get_post_meta( $staff_post->ID, 'phone', true ),
			'url'      => get_permalink( $staff_post ),
			'staff_id' => $staff_post->ID,
		);

		// Try to use Staff class if available for more complete data.
		if ( class_exists( '\PRC\Platform\Staff_Bylines\Staff' ) ) {
			$staff = new \PRC\Platform\Staff_Bylines\Staff( $staff_post->ID );
			if ( ! empty( $staff->name ) ) {
				$contact['name'] = $staff->name;
			}
			if ( ! empty( $staff->job_title ) ) {
				$contact['title'] = $staff->job_title;
			}
		}

		return $contact;
	}

	/**
	 * Get default contact information.
	 *
	 * Returns fallback contact data when no specific staff contact is found.
	 *
	 * @return array Default contact data array.
	 */
	public static function get_default_contact() {
		$default = array(
			'name'  => 'Communications Department',
			'title' => '',
			'email' => '',
			'phone' => '202.419.4372',
			'url'   => 'https://www.pewresearch.org',
		);

		/**
		 * Filter the default contact data.
		 *
		 * @param array $default The default contact data array.
		 */
		return apply_filters( 'prc_schema_seo_default_contact', $default );
	}

	/**
	 * Clear contact cache for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function clear_cache( $post_id ) {
		wp_cache_delete( 'contact_' . $post_id, self::CACHE_GROUP );
	}

	/**
	 * Clear all contact caches for posts with a specific category.
	 *
	 * Called when category term meta changes to invalidate related caches.
	 *
	 * @param int $category_id Category term ID.
	 * @return void
	 */
	public static function clear_cache_for_category( $category_id ) {
		// Get all posts in this category.
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'category',
						'field'    => 'term_id',
						'terms'    => $category_id,
					),
				),
			)
		);

		foreach ( $posts as $post_id ) {
			self::clear_cache( $post_id );
		}
	}

	/**
	 * Clear caches for all posts that have an expertise area linked.
	 *
	 * Called when staff media contact designation changes.
	 *
	 * @param int $expertise_id Expertise area term ID.
	 * @return void
	 */
	public static function clear_cache_for_expertise( $expertise_id ) {
		// Find all categories linked to this expertise area.
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'     => '_prc_seo_term_data',
						'value'   => sprintf( '"expertise_id";i:%d', $expertise_id ),
						'compare' => 'LIKE',
					),
				),
			)
		);

		if ( is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			self::clear_cache_for_category( $term->term_id );
		}
	}
}
