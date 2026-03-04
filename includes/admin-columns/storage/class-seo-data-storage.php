<?php
/**
 * SEO Data Storage
 *
 * Custom storage class for Admin Columns Pro that handles
 * individual field access within the serialized _prc_seo_data array.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO\Storage;

use ACP\Editing\Storage;

/**
 * SEO Data Storage
 *
 * Provides read/write access to individual fields within the
 * serialized _prc_seo_data post meta array.
 *
 * @package PRC\Platform\Schema_SEO
 */
class SEO_Data_Storage implements Storage {

	/**
	 * The meta key for SEO data.
	 */
	const META_KEY = '_prc_seo_data';

	/**
	 * The field key within the SEO data array.
	 *
	 * @var string
	 */
	private $field_key;

	/**
	 * Constructor.
	 *
	 * @param string $field_key The field key within the SEO data array.
	 */
	public function __construct( string $field_key ) {
		$this->field_key = $field_key;
	}

	/**
	 * Get the value for a specific post.
	 *
	 * @param int $id The post ID.
	 * @return mixed The field value (empty string if not set, to enable editing on new posts).
	 */
	public function get( int $id ) {
		$data = get_post_meta( $id, self::META_KEY, true );

		if ( ! is_array( $data ) ) {
			return '';
		}

		return $data[ $this->field_key ] ?? '';
	}

	/**
	 * Update the value for a specific post.
	 *
	 * @param int   $id    The post ID.
	 * @param mixed $value The new value.
	 * @return bool Whether the update was successful.
	 */
	public function update( int $id, $value ): bool {
		$data = get_post_meta( $id, self::META_KEY, true );

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// Handle empty values - remove the key if value is empty.
		if ( '' === $value || null === $value ) {
			unset( $data[ $this->field_key ] );
		} else {
			$data[ $this->field_key ] = $value;
		}

		// If data is empty, delete the meta entirely.
		if ( empty( $data ) ) {
			return delete_post_meta( $id, self::META_KEY );
		}

		return (bool) update_post_meta( $id, self::META_KEY, $data );
	}
}
