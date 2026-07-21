<?php
/**
 * Primary Term Editing Service
 *
 * Custom editing service for Admin Columns Pro that handles
 * primary term selection for a specific taxonomy.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO\Editing;

use ACP\Editing\Service;
use ACP\Editing\View;

/**
 * Primary Term Editing Service
 *
 * Provides inline editing for primary term selection using a dropdown
 * populated with terms from the configured taxonomy.
 *
 * @package PRC\Platform\Schema_SEO
 */
class Primary_Term_Editing implements Service {

	/**
	 * The meta key for SEO data.
	 */
	const META_KEY = '_prc_seo_data';

	/**
	 * The taxonomy slug.
	 *
	 * @var string
	 */
	private $taxonomy;

	/**
	 * Constructor.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 */
	public function __construct( string $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	/**
	 * Get the editing view.
	 *
	 * @param string $context The editing context (single or bulk).
	 * @return View|null The editing view.
	 */
	public function get_view( string $context ): ?View {
		$terms   = get_terms(
			array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
			)
		);
		$options = array( '' => __( '— Select —', 'prc-schema-seo' ) );

		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->term_id ] = $term->name;
			}
		}

		$view = new View\Select( $options );
		$view->set_clear_button( true );

		return $view;
	}

	/**
	 * Get the current value for a post.
	 *
	 * @param int $id The post ID.
	 * @return mixed The primary term ID (empty string if not set, to enable editing on new posts).
	 */
	public function get_value( int $id ) {
		$data = get_post_meta( $id, self::META_KEY, true );

		if ( ! is_array( $data ) || ! isset( $data['primary_terms'] ) || ! is_array( $data['primary_terms'] ) ) {
			return '';
		}

		return $data['primary_terms'][ $this->taxonomy ] ?? '';
	}

	/**
	 * Update the primary term for a post.
	 *
	 * @param int   $id   The post ID.
	 * @param mixed $data The new term ID.
	 */
	public function update( int $id, $data ): void {
		$seo_data = get_post_meta( $id, self::META_KEY, true );

		if ( ! is_array( $seo_data ) ) {
			$seo_data = array();
		}

		if ( ! isset( $seo_data['primary_terms'] ) || ! is_array( $seo_data['primary_terms'] ) ) {
			$seo_data['primary_terms'] = array();
		}

		// Handle empty value - remove the primary term for this taxonomy.
		if ( '' === $data || null === $data ) {
			unset( $seo_data['primary_terms'][ $this->taxonomy ] );
		} else {
			$seo_data['primary_terms'][ $this->taxonomy ] = (int) $data;
		}

		// Clean up empty primary_terms array.
		if ( empty( $seo_data['primary_terms'] ) ) {
			unset( $seo_data['primary_terms'] );
		}

		// If data is empty, delete the meta entirely.
		if ( empty( $seo_data ) ) {
			delete_post_meta( $id, self::META_KEY );
		} else {
			update_post_meta( $id, self::META_KEY, $seo_data );
		}

		// Bust derived caches (seo_data, schema, meta_tags, contact).
		( new \PRC\Platform\Schema_SEO\Metadata( new \PRC\Platform\Schema_SEO\Loader() ) )->clear_cache( $id );
	}
}
