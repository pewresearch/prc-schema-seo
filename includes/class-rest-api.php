<?php
/**
 * REST_API class for updating and retrieving SEO metadata via REST API.
 *
 * Registers REST API fields for SEO data on enabled post types.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * REST_API class for updating and retrieving SEO metadata via REST API.
 *
 * Handles REST API field registration with get/update callbacks for core-data compatibility.
 */
class REST_API {
	/**
	 * The loader instance.
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
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader       = $loader;
		$this->seo_metadata = new Metadata( $loader );

		$this->loader->add_action( 'rest_api_init', $this, 'register_rest_fields' );
	}

	/**
	 * Register REST API fields for enabled post types.
	 *
	 * @hook rest_api_init
	 */
	public function register_rest_fields() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type, 'prc-schema-seo' ) ) {
				register_rest_field(
					$post_type,
					'prc_seo_data',
					array(
						'get_callback'    => array( $this, 'get_seo_data' ),
						'update_callback' => array( $this, 'update_seo_data' ),
						'schema'          => $this->get_rest_schema(),
					)
				);
			}
		}
	}

	/**
	 * Get SEO data for REST API.
	 *
	 * @param array $object Post object.
	 * @return array SEO data.
	 */
	public function get_seo_data( $object ) {
		$post_id  = $object['id'];
		$seo_data = $this->seo_metadata->get_seo_data( $post_id );

		$submitted_at = get_post_meta( $post_id, '_prc_schema_seo_indexnow_submitted_at', true );
		$seo_data['indexnow_submitted_at'] = $submitted_at ? (int) $submitted_at : null;

		$gsc_data = get_post_meta( $post_id, Search_Console::META_KEY, true );
		$seo_data['gsc_index_status'] = is_array( $gsc_data ) && ! empty( $gsc_data ) ? $gsc_data : null;

		return apply_filters( 'prc_schema_seo_rest_prepare', $seo_data, $post_id );
	}

	/**
	 * Update SEO data via REST API.
	 *
	 * @param mixed  $value  New value.
	 * @param object $object Post object.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function update_seo_data( $value, $object ) {
		// Check permissions
		if ( ! current_user_can( 'edit_post', $object->ID ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You are not allowed to update SEO data for this post.', 'prc-schema-seo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		// Validate value is array
		if ( ! is_array( $value ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'SEO data must be an object.', 'prc-schema-seo' ),
				array( 'status' => 400 )
			);
		}

		// Validate individual fields
		$validation_errors = $this->validate_seo_data( $value );
		if ( ! empty( $validation_errors ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				implode( ' ', $validation_errors ),
				array( 'status' => 400 )
			);
		}

		unset( $value['indexnow_submitted_at'] );
		unset( $value['gsc_index_status'] );

		// Strip primary term mappings that don't belong to the post's assigned terms.
		if ( isset( $value['primary_terms'] ) && is_array( $value['primary_terms'] ) ) {
			$value['primary_terms'] = Primary_Term::strip_unassigned( $value['primary_terms'], $object->ID );
		}

		// Update meta with sanitized data.
		$this->seo_metadata->update_seo_data( $object->ID, $value );

		return true;
	}

	/**
	 * Validate SEO data.
	 *
	 * @param array $data SEO data to validate.
	 * @return array Array of error messages.
	 */
	private function validate_seo_data( $data ) {
		$errors = array();

		// Title length
		if ( isset( $data['title'] ) && mb_strlen( $data['title'] ) > 255 ) {
			$errors[] = __( 'Title must be 255 characters or less.', 'prc-schema-seo' );
		}

		// Description length
		if ( isset( $data['description'] ) && mb_strlen( $data['description'] ) > 500 ) {
			$errors[] = __( 'Description must be 500 characters or less.', 'prc-schema-seo' );
		}

		// OG title length
		if ( isset( $data['og_title'] ) && mb_strlen( $data['og_title'] ) > 255 ) {
			$errors[] = __( 'Open Graph title must be 255 characters or less.', 'prc-schema-seo' );
		}

		// OG description length
		if ( isset( $data['og_description'] ) && mb_strlen( $data['og_description'] ) > 500 ) {
			$errors[] = __( 'Open Graph description must be 500 characters or less.', 'prc-schema-seo' );
		}

		// OG image attachment ID
		if ( isset( $data['og_image'] ) && $data['og_image'] ) {
			$attachment_id = absint( $data['og_image'] );
			if ( ! wp_attachment_is_image( $attachment_id ) ) {
				$errors[] = __( 'Open Graph image must be a valid image attachment.', 'prc-schema-seo' );
			}
		}

		// Schema type
		if ( isset( $data['schema_type'] ) ) {
			$allowed_types = apply_filters(
				'prc_schema_seo_allowed_schema_types',
				array( 'Article', 'NewsArticle', 'BlogPosting', 'Report', 'WebPage', 'Person', 'Event', 'Course', 'Dataset', 'Quiz' ),
				''
			);
			if ( ! in_array( $data['schema_type'], $allowed_types, true ) ) {
				$errors[] = sprintf(
					/* translators: %s: comma-separated list of allowed schema types */
					__( 'Schema type must be one of: %s.', 'prc-schema-seo' ),
					implode( ', ', $allowed_types )
				);
			}
		}

		// Canonical URL
		if ( isset( $data['canonical_url'] ) && ! empty( $data['canonical_url'] ) ) {
			$url = esc_url_raw( $data['canonical_url'] );
			if ( empty( $url ) ) {
				$errors[] = __( 'Canonical URL must be a valid URL.', 'prc-schema-seo' );
			}
		}

		// Primary terms mapping validation.
		if ( isset( $data['primary_terms'] ) && is_array( $data['primary_terms'] ) ) {
			$primary_term_errors = Primary_Term::validate( $data['primary_terms'] );
			$errors              = array_merge( $errors, $primary_term_errors );
		}

		return $errors;
	}

	/**
	 * Get REST API schema for SEO data.
	 *
	 * @return array REST schema.
	 */
	private function get_rest_schema() {
		return array(
			'type'        => 'object',
			'description' => __( 'SEO metadata for the post.', 'prc-schema-seo' ),
			'properties'  => array(
				'title'          => array(
					'type'        => 'string',
					'description' => __( 'Custom SEO title.', 'prc-schema-seo' ),
				),
				'description'    => array(
					'type'        => 'string',
					'description' => __( 'Meta description.', 'prc-schema-seo' ),
				),
				'og_title'       => array(
					'type'        => 'string',
					'description' => __( 'Open Graph title.', 'prc-schema-seo' ),
				),
				'og_description' => array(
					'type'        => 'string',
					'description' => __( 'Open Graph description.', 'prc-schema-seo' ),
				),
				'og_image'       => array(
					'type'        => 'integer',
					'description' => __( 'Open Graph image attachment ID.', 'prc-schema-seo' ),
				),
				'schema_type'    => array(
					'type'        => 'string',
					'description' => __( 'Schema.org type.', 'prc-schema-seo' ),
				),
				'noindex'        => array(
					'type'        => 'boolean',
					'description' => __( 'Prevent search engine indexing.', 'prc-schema-seo' ),
				),
				'canonical_url'  => array(
					'type'        => 'string',
					'description' => __( 'Custom canonical URL.', 'prc-schema-seo' ),
					'format'      => 'uri',
				),
				'primary_terms'  => array(
					'type'        => 'object',
					'description' => __( 'Primary term IDs per taxonomy.', 'prc-schema-seo' ),
				),
				'custom_schema'          => array(
					'type'        => 'object',
					'description' => __( 'Additional schema.org properties.', 'prc-schema-seo' ),
				),
				'indexnow_submitted_at' => array(
					'type'        => array( 'integer', 'null' ),
					'description' => __( 'Unix timestamp of last IndexNow submission.', 'prc-schema-seo' ),
					'readOnly'    => true,
				),
				'gsc_index_status'     => array(
					'type'        => array( 'object', 'null' ),
					'description' => __( 'Google Search Console URL Inspection data.', 'prc-schema-seo' ),
					'readOnly'    => true,
					'properties'  => array(
						'verdict'              => array( 'type' => 'string' ),
						'coverage_state'       => array( 'type' => 'string' ),
						'robotstxt_state'      => array( 'type' => 'string' ),
						'indexing_state'       => array( 'type' => 'string' ),
						'last_crawl_time'      => array( 'type' => array( 'string', 'null' ) ),
						'page_fetch_state'     => array( 'type' => 'string' ),
						'crawled_as'           => array( 'type' => 'string' ),
						'mobile_verdict'       => array( 'type' => 'string' ),
						'mobile_issues'        => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'rich_results_verdict' => array( 'type' => 'string' ),
						'rich_results_issues'  => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'inspection_link'      => array( 'type' => 'string' ),
						'fetched_at'           => array( 'type' => 'integer' ),
					),
				),
			),
		);
	}
}
