<?php
/**
 * Primary Term Column
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO\Columns;

use AC\Column;
use ACP\Editing\Editable;
use ACP\Filtering\Filterable;
use PRC\Platform\Schema_SEO\Editing\Primary_Term_Editing;

/**
 * Primary Term Column
 *
 * Displays the primary term for a selected taxonomy.
 *
 * @package PRC\Platform\Schema_SEO
 */
class Primary_Term extends Column implements Editable, Filterable {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_type( 'prc_seo_primary_term' )
			->set_label( __( 'Primary Term', 'prc-schema-seo' ) )
			->set_group( 'prc-seo' );
	}

	/**
	 * Register settings for this column.
	 */
	protected function register_settings() {
		$this->add_setting( new Primary_Term_Setting( $this ) );
	}

	/**
	 * Get the selected taxonomy from settings.
	 *
	 * @return string The taxonomy slug.
	 */
	public function get_taxonomy() {
		$setting = $this->get_setting( 'primary_taxonomy' );

		if ( ! $setting instanceof Primary_Term_Setting ) {
			return 'category';
		}

		return $setting->get_primary_taxonomy();
	}

	/**
	 * Get the raw, underlying value for the column.
	 *
	 * @param int $post_id The post ID.
	 * @return int|null The primary term ID.
	 */
	public function get_raw_value( $post_id ) {
		$data     = get_post_meta( $post_id, '_prc_seo_data', true );
		$taxonomy = $this->get_taxonomy();

		if ( ! is_array( $data ) || ! isset( $data['primary_terms'] ) || ! is_array( $data['primary_terms'] ) ) {
			return null;
		}

		return isset( $data['primary_terms'][ $taxonomy ] ) ? (int) $data['primary_terms'][ $taxonomy ] : null;
	}

	/**
	 * Returns the display value for the column.
	 *
	 * @param int $post_id The post ID.
	 * @return string The formatted value.
	 */
	public function get_value( $post_id ) {
		$term_id = $this->get_raw_value( $post_id );

		if ( empty( $term_id ) ) {
			return $this->get_empty_char();
		}

		$term = get_term( $term_id, $this->get_taxonomy() );

		if ( ! $term instanceof \WP_Term ) {
			return $this->get_empty_char();
		}

		// Create a link to filter by this term in the admin.
		$edit_link = get_edit_term_link( $term->term_id, $term->taxonomy );
		if ( $edit_link ) {
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_link ),
				esc_html( $term->name )
			);
		}

		return esc_html( $term->name );
	}

	/**
	 * Get filtering model.
	 *
	 * @return Primary_Term_Filtering
	 */
	public function filtering() {
		return new Primary_Term_Filtering( $this );
	}

	/**
	 * Get editing service.
	 *
	 * @return \ACP\Editing\Service
	 */
	public function editing() {
		return new Primary_Term_Editing( $this->get_taxonomy() );
	}
}

/**
 * Primary Term Setting
 *
 * Allows selecting which taxonomy to show the primary term for.
 */
class Primary_Term_Setting extends \AC\Settings\Column {

	/**
	 * The selected taxonomy.
	 *
	 * @var string
	 */
	private $primary_taxonomy;

	/**
	 * Define the options for this setting.
	 *
	 * @return array The option keys with defaults.
	 */
	protected function define_options() {
		return array(
			'primary_taxonomy' => 'category',
		);
	}

	/**
	 * Get the available taxonomy options.
	 *
	 * @return array The taxonomy options.
	 */
	protected function get_taxonomy_options() {
		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		$options = array();
		foreach ( $taxonomies as $taxonomy ) {
			$options[ $taxonomy->name ] = $taxonomy->labels->singular_name;
		}

		return $options;
	}

	/**
	 * Create the setting input.
	 *
	 * @return \AC\View
	 */
	public function create_view() {
		$select = $this->create_element( 'select', 'primary_taxonomy' );
		$select->set_options( $this->get_taxonomy_options() );

		return new \AC\View(
			array(
				'label'   => __( 'Taxonomy', 'prc-schema-seo' ),
				'setting' => $select,
			)
		);
	}

	/**
	 * Get the selected taxonomy.
	 *
	 * @return string The taxonomy slug.
	 */
	public function get_primary_taxonomy() {
		return $this->primary_taxonomy ?: 'category';
	}

	/**
	 * Set the taxonomy.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return bool
	 */
	public function set_primary_taxonomy( $taxonomy ) {
		$this->primary_taxonomy = $taxonomy;
		return true;
	}
}

/**
 * Primary Term Filtering Model
 *
 * Provides filtering options for primary terms.
 */
class Primary_Term_Filtering extends \ACP\Filtering\Model {

	/**
	 * Get the filtering data.
	 *
	 * @return array The filtering options.
	 */
	public function get_filtering_data() {
		$taxonomy = $this->column->get_taxonomy();
		$terms    = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		$options = array();
		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->term_id ] = $term->name;
			}
		}

		return array(
			'options' => $options,
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

		$taxonomy = $this->column->get_taxonomy();

		// Add meta query for filtering by primary term.
		if ( ! isset( $vars['meta_query'] ) ) {
			$vars['meta_query'] = array();
		}

		// Match the serialized array format for primary_terms.
		$vars['meta_query'][] = array(
			'key'     => '_prc_seo_data',
			'value'   => '"' . $taxonomy . '";i:' . (int) $value,
			'compare' => 'LIKE',
		);

		return $vars;
	}
}
