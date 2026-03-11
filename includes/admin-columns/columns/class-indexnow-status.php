<?php
/**
 * IndexNow Status Column
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO\Columns;

use AC\Column;
use ACP\Filtering\Filterable;
use ACP\Sorting\Sortable;
use ACP\Export\Exportable;

/**
 * IndexNow Status Column
 *
 * Displays a green/red indicator showing whether the post has been
 * submitted to IndexNow, with the submission date when available.
 *
 * @package PRC\Platform\Schema_SEO
 */
class IndexNow_Status extends Column implements Sortable, Filterable, Exportable {

	const META_KEY = '_prc_schema_seo_indexnow_submitted_at';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_type( 'prc_seo_indexnow_status' )
			->set_label( __( 'IndexNow', 'prc-schema-seo' ) )
			->set_group( 'prc-seo' );
	}

	/**
	 * Get the raw value (unix timestamp or null).
	 *
	 * @param int $post_id The post ID.
	 * @return int|null Unix timestamp or null.
	 */
	public function get_raw_value( $post_id ) {
		$submitted_at = get_post_meta( $post_id, self::META_KEY, true );
		return $submitted_at ? (int) $submitted_at : null;
	}

	/**
	 * Render the column value as a coloured dot with label.
	 *
	 * @param int $post_id The post ID.
	 * @return string HTML output.
	 */
	public function get_value( $post_id ) {
		$submitted_at = $this->get_raw_value( $post_id );
		$is_submitted = ! empty( $submitted_at );

		$color = $is_submitted ? '#00a32a' : '#d63638';
		$label = $is_submitted
			? __( 'Submitted', 'prc-schema-seo' )
			: __( 'Not submitted', 'prc-schema-seo' );

		$html = sprintf(
			'<span style="display:inline-flex;align-items:center;gap:4px;">'
			. '<span style="display:inline-block;width:10px;height:10px;border-radius:50%%;background:%s;" aria-hidden="true"></span>'
			. '<span>%s</span>'
			. '</span>',
			esc_attr( $color ),
			esc_html( $label )
		);

		if ( $is_submitted ) {
			$date  = wp_date( get_option( 'date_format' ), $submitted_at );
			$html .= sprintf(
				'<br><span style="color:#757575;font-size:12px;">%s</span>',
				esc_html( $date )
			);
		}

		return $html;
	}

	/**
	 * Get sorting model.
	 *
	 * @return \ACP\Sorting\Model
	 */
	public function sorting() {
		return new \ACP\Sorting\Model\Post\Meta( self::META_KEY );
	}

	/**
	 * Get filtering model.
	 *
	 * @return IndexNow_Filtering
	 */
	public function filtering() {
		return new IndexNow_Filtering( $this );
	}

	/**
	 * Get export model.
	 *
	 * @return \ACP\Export\Model
	 */
	public function export() {
		return new \ACP\Export\Model\StrippedRawValue( $this );
	}
}

/**
 * IndexNow Filtering Model
 *
 * Provides filtering options for submitted/not submitted posts.
 */
class IndexNow_Filtering extends \ACP\Filtering\Model {

	/**
	 * Get the filtering data.
	 *
	 * @return array The filtering options.
	 */
	public function get_filtering_data() {
		return array(
			'options' => array(
				'submitted'     => __( 'Submitted', 'prc-schema-seo' ),
				'not_submitted' => __( 'Not Submitted', 'prc-schema-seo' ),
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

		if ( ! isset( $vars['meta_query'] ) ) {
			$vars['meta_query'] = array();
		}

		if ( 'submitted' === $value ) {
			$vars['meta_query'][] = array(
				'key'     => IndexNow_Status::META_KEY,
				'compare' => 'EXISTS',
			);
		} else {
			$vars['meta_query'][] = array(
				'key'     => IndexNow_Status::META_KEY,
				'compare' => 'NOT EXISTS',
			);
		}

		return $vars;
	}
}
