<?php
/**
 * Google Index Status Column
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO\Columns;

use AC\Column;
use ACP\Filtering\Filterable;
use ACP\Sorting\Sortable;
use ACP\Export\Exportable;
use PRC\Platform\Schema_SEO\Search_Console;

/**
 * Google Index Status Column
 *
 * Displays a coloured indicator showing Google's index verdict for the
 * post, based on cached URL Inspection API results.
 *
 * @package PRC\Platform\Schema_SEO
 */
class Google_Index_Status extends Column implements Sortable, Filterable, Exportable {

	const VERDICT_COLORS = array(
		'PASS'    => '#00a32a',
		'PARTIAL' => '#dba617',
		'FAIL'    => '#d63638',
		'NEUTRAL' => '#ccc',
	);

	const VERDICT_LABELS = array(
		'PASS'    => 'Indexed',
		'PARTIAL' => 'Partial',
		'FAIL'    => 'Not indexed',
		'NEUTRAL' => 'Unknown',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_type( 'prc_seo_google_index' )
			->set_label( __( 'Google Index', 'prc-schema-seo' ) )
			->set_group( 'prc-seo' );
	}

	/**
	 * Get the raw verdict string.
	 *
	 * @param int $post_id The post ID.
	 * @return string Verdict (PASS, PARTIAL, FAIL, NEUTRAL) or empty.
	 */
	public function get_raw_value( $post_id ) {
		$data = get_post_meta( $post_id, Search_Console::META_KEY, true );
		if ( ! is_array( $data ) || empty( $data['verdict'] ) ) {
			return '';
		}
		return $data['verdict'];
	}

	/**
	 * Render the column value as a coloured dot with label and coverage state.
	 *
	 * @param int $post_id The post ID.
	 * @return string HTML output.
	 */
	public function get_value( $post_id ) {
		$data = get_post_meta( $post_id, Search_Console::META_KEY, true );
		if ( ! is_array( $data ) || empty( $data ) ) {
			return $this->get_empty_char();
		}

		$verdict = $data['verdict'] ?? 'NEUTRAL';
		$color   = self::VERDICT_COLORS[ $verdict ] ?? self::VERDICT_COLORS['NEUTRAL'];
		$label   = self::VERDICT_LABELS[ $verdict ] ?? self::VERDICT_LABELS['NEUTRAL'];

		$html = sprintf(
			'<span style="display:inline-flex;align-items:center;gap:4px;">'
			. '<span style="display:inline-block;width:10px;height:10px;border-radius:50%%;background:%s;" aria-hidden="true"></span>'
			. '<span>%s</span>'
			. '</span>',
			esc_attr( $color ),
			esc_html( $label )
		);

		if ( ! empty( $data['coverage_state'] ) ) {
			$html .= sprintf(
				'<br><span style="color:#757575;font-size:12px;">%s</span>',
				esc_html( $data['coverage_state'] )
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
		return new \ACP\Sorting\Model\Post\Meta( Search_Console::META_KEY );
	}

	/**
	 * Get filtering model.
	 *
	 * @return Google_Index_Filtering
	 */
	public function filtering() {
		return new Google_Index_Filtering( $this );
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
 * Google Index Filtering Model
 *
 * Provides filtering options by index verdict.
 */
class Google_Index_Filtering extends \ACP\Filtering\Model {

	/**
	 * Get the filtering data.
	 *
	 * @return array The filtering options.
	 */
	public function get_filtering_data() {
		return array(
			'options' => array(
				'PASS'      => __( 'Indexed', 'prc-schema-seo' ),
				'PARTIAL'   => __( 'Partially indexed', 'prc-schema-seo' ),
				'FAIL'      => __( 'Not indexed', 'prc-schema-seo' ),
				'NEUTRAL'   => __( 'Unknown', 'prc-schema-seo' ),
				'unchecked' => __( 'Not checked', 'prc-schema-seo' ),
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

		$meta_key = Search_Console::META_KEY;

		if ( 'unchecked' === $value ) {
			$vars['meta_query'][] = array(
				'key'     => $meta_key,
				'compare' => 'NOT EXISTS',
			);
		} else {
			$vars['meta_query'][] = array(
				'key'     => $meta_key,
				'value'   => '"verdict";s:' . strlen( $value ) . ':"' . $value . '"',
				'compare' => 'LIKE',
			);
		}

		return $vars;
	}
}
