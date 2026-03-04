<?php
/**
 * Noindex Column
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO\Columns;

use AC\Column;
use AC\Helper\Select\Option;
use AC\Type\ToggleOptions;
use ACP\Editing\Editable;
use ACP\Filtering\Filterable;
use ACP\Sorting\Sortable;
use PRC\Platform\Schema_SEO\Storage\SEO_Data_Storage;

/**
 * Noindex Column
 *
 * Displays the noindex status as an icon.
 *
 * @package PRC\Platform\Schema_SEO
 */
class Noindex extends Column implements Editable, Filterable, Sortable {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_type( 'prc_seo_noindex' )
			->set_label( __( 'Is Indexed', 'prc-schema-seo' ) )
			->set_group( 'prc-seo' );
	}

	/**
	 * Get the raw, underlying value for the column.
	 *
	 * @param int $post_id The post ID.
	 * @return bool The noindex value.
	 */
	public function get_raw_value( $post_id ) {
		$data = get_post_meta( $post_id, '_prc_seo_data', true );
		return is_array( $data ) && isset( $data['noindex'] ) ? (bool) $data['noindex'] : false;
	}

	/**
	 * Returns the display value for the column.
	 *
	 * Shows a checkmark icon if indexed (noindex = false),
	 * or an X icon if not indexed (noindex = true).
	 *
	 * @param int $post_id The post ID.
	 * @return string The formatted value.
	 */
	public function get_value( $post_id ) {
		$noindex = $this->get_raw_value( $post_id );
		// Invert the value: show "yes" (indexed) when noindex is false.
		return ac_helper()->icon->yes_or_no( ! $noindex );
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
	 * Get filtering model.
	 *
	 * @return Noindex_Filtering
	 */
	public function filtering() {
		return new Noindex_Filtering( $this );
	}

	/**
	 * Get editing service.
	 *
	 * @return \ACP\Editing\Service
	 */
	public function editing() {
		// Toggle options: disabled (indexed) = false/0, enabled (noindex) = true/1
		$toggle_options = new ToggleOptions(
			new Option( '', __( 'Indexed', 'prc-schema-seo' ) ),
			new Option( '1', __( 'Noindex', 'prc-schema-seo' ) )
		);

		return new \ACP\Editing\Service\Basic(
			new \ACP\Editing\View\Toggle( $toggle_options ),
			new SEO_Data_Storage( 'noindex' )
		);
	}
}

/**
 * Noindex Filtering Model
 *
 * Provides filtering options for indexed/noindexed posts.
 */
class Noindex_Filtering extends \ACP\Filtering\Model {

	/**
	 * Get the filtering data.
	 *
	 * @return array The filtering options.
	 */
	public function get_filtering_data() {
		return array(
			'options' => array(
				'indexed'   => __( 'Indexed', 'prc-schema-seo' ),
				'noindexed' => __( 'Not Indexed', 'prc-schema-seo' ),
			),
		);
	}

	/**
	 * Get the filtering vars.
	 *
	 * @param array $vars The query vars.
	 * @return array Modified query vars.
	 */
	public function get_filtering_vars( $vars ) {
		$value = $this->get_filter_value();

		if ( empty( $value ) ) {
			return $vars;
		}

		// Add meta query for filtering.
		if ( ! isset( $vars['meta_query'] ) ) {
			$vars['meta_query'] = array();
		}

		if ( 'indexed' === $value ) {
			// Posts without noindex set or with noindex = false.
			$vars['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => '_prc_seo_data',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_prc_seo_data',
					'value'   => '"noindex";b:0',
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_prc_seo_data',
					'value'   => '"noindex"',
					'compare' => 'NOT LIKE',
				),
			);
		} elseif ( 'noindexed' === $value ) {
			// Posts with noindex = true.
			$vars['meta_query'][] = array(
				'key'     => '_prc_seo_data',
				'value'   => '"noindex";b:1',
				'compare' => 'LIKE',
			);
		}

		return $vars;
	}
}
