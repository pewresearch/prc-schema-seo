<?php
/**
 * SEO Description Column
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO\Columns;

use AC\Column;
use ACP\Editing\Editable;
use ACP\Export\Exportable;
use ACP\Search\Searchable;
use ACP\Sorting\Sortable;
use PRC\Platform\Schema_SEO\Storage\SEO_Data_Storage;

/**
 * SEO Description Column
 *
 * Displays the custom meta description for posts.
 *
 * @package PRC\Platform\Schema_SEO
 */
class Description extends Column implements Editable, Exportable, Searchable, Sortable {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_type( 'prc_seo_description' )
			->set_label( __( 'Meta Description', 'prc-schema-seo' ) )
			->set_group( 'prc-seo' );
	}

	/**
	 * Get the raw, underlying value for the column.
	 *
	 * @param int $post_id The post ID.
	 * @return string The meta description.
	 */
	public function get_raw_value( $post_id ) {
		$data = get_post_meta( $post_id, '_prc_seo_data', true );
		return is_array( $data ) && isset( $data['description'] ) ? $data['description'] : '';
	}

	/**
	 * Returns the display value for the column.
	 *
	 * @param int $post_id The post ID.
	 * @return string The formatted value.
	 */
	public function get_value( $post_id ) {
		$value = $this->get_raw_value( $post_id );
		if ( empty( $value ) ) {
			return $this->get_empty_char();
		}
		// Truncate for display if longer than 100 chars.
		$display = mb_strlen( $value ) > 100 ? mb_substr( $value, 0, 97 ) . '...' : $value;
		return esc_html( $display );
	}

	/**
	 * Get sorting model.
	 *
	 * @return \ACP\Sorting\Model
	 */
	public function sorting() {
		return new \ACP\Sorting\Model\Post\Meta( '_prc_seo_data' );
	}

	/**
	 * Get search model.
	 *
	 * @return \ACP\Search\Comparison
	 */
	public function search() {
		return new \ACP\Search\Comparison\Meta\Text( '_prc_seo_data' );
	}

	/**
	 * Get export model.
	 *
	 * @return \ACP\Export\Model
	 */
	public function export() {
		return new \ACP\Export\Model\StrippedRawValue( $this );
	}

	/**
	 * Get editing service.
	 *
	 * @return \ACP\Editing\Service
	 */
	public function editing() {
		return new \ACP\Editing\Service\Basic(
			( new \ACP\Editing\View\TextArea() )
				->set_placeholder( __( 'Enter meta description', 'prc-schema-seo' ) ),
			new SEO_Data_Storage( 'description' )
		);
	}
}
