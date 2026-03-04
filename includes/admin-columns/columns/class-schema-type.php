<?php
/**
 * Schema Type Column
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO\Columns;

use AC\Column;
use ACP\Editing\Editable;
use ACP\Filtering\Filterable;
use ACP\Sorting\Sortable;
use PRC\Platform\Schema_SEO\Storage\SEO_Data_Storage;

/**
 * Schema Type Column
 *
 * Displays the Schema.org type for posts.
 *
 * @package PRC\Platform\Schema_SEO
 */
class Schema_Type extends Column implements Editable, Filterable, Sortable {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_type( 'prc_seo_schema_type' )
			->set_label( __( 'Schema Type', 'prc-schema-seo' ) )
			->set_group( 'prc-seo' );
	}

	/**
	 * Get the raw, underlying value for the column.
	 *
	 * @param int $post_id The post ID.
	 * @return string The schema type.
	 */
	public function get_raw_value( $post_id ) {
		$data = get_post_meta( $post_id, '_prc_seo_data', true );
		return is_array( $data ) && isset( $data['schema_type'] ) ? $data['schema_type'] : '';
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
			// Show the default based on post type.
			$value = $this->get_default_schema_type( $post_id );
			if ( empty( $value ) ) {
				return $this->get_empty_char();
			}
			// Indicate it's a default with muted styling.
			return sprintf(
				'<span style="color: #999;">%s</span>',
				esc_html( $value )
			);
		}

		return esc_html( $value );
	}

	/**
	 * Get the default schema type for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return string The default schema type.
	 */
	private function get_default_schema_type( $post_id ) {
		$post_type = get_post_type( $post_id );
		$mapping   = array(
			'post'       => 'Article',
			'page'       => 'WebPage',
			'staff'      => 'Person',
			'short-read' => 'Article',
			'fact-sheet' => 'Article',
			'decoded'    => 'Article',
			'event'      => 'Event',
			'course'     => 'Course',
			'report'     => 'Report',
			'quiz'       => 'Quiz',
			'dataset'    => 'Dataset',
		);

		return $mapping[ $post_type ] ?? 'WebPage';
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
	 * @return Schema_Type_Filtering
	 */
	public function filtering() {
		return new Schema_Type_Filtering( $this );
	}

	/**
	 * Get the available schema type options.
	 *
	 * @return array The schema type options.
	 */
	private function get_schema_type_options() {
		return array(
			''            => __( '— Use Default —', 'prc-schema-seo' ),
			'Article'     => 'Article',
			'WebPage'     => 'WebPage',
			'Person'      => 'Person',
			'Event'       => 'Event',
			'Course'      => 'Course',
			'Report'      => 'Report',
			'Quiz'        => 'Quiz',
			'Dataset'     => 'Dataset',
			'FAQPage'     => 'FAQPage',
			'HowTo'       => 'HowTo',
			'NewsArticle' => 'NewsArticle',
		);
	}

	/**
	 * Get editing service.
	 *
	 * @return \ACP\Editing\Service
	 */
	public function editing() {
		$view = new \ACP\Editing\View\Select( $this->get_schema_type_options() );
		$view->set_clear_button( true );

		return new \ACP\Editing\Service\Basic(
			$view,
			new SEO_Data_Storage( 'schema_type' )
		);
	}
}

/**
 * Schema Type Filtering Model
 *
 * Provides filtering options for schema types.
 */
class Schema_Type_Filtering extends \ACP\Filtering\Model {

	/**
	 * Get the filtering data.
	 *
	 * @return array The filtering options.
	 */
	public function get_filtering_data() {
		$schema_types = array(
			'Article'     => 'Article',
			'WebPage'     => 'WebPage',
			'Person'      => 'Person',
			'Event'       => 'Event',
			'Course'      => 'Course',
			'Report'      => 'Report',
			'Quiz'        => 'Quiz',
			'Dataset'     => 'Dataset',
			'FAQPage'     => 'FAQPage',
			'HowTo'       => 'HowTo',
			'NewsArticle' => 'NewsArticle',
		);

		return array(
			'options' => $schema_types,
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

		$vars['meta_query'][] = array(
			'key'     => '_prc_seo_data',
			'value'   => '"schema_type";s:' . strlen( $value ) . ':"' . $value . '"',
			'compare' => 'LIKE',
		);

		return $vars;
	}
}
