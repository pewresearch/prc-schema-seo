<?php
/**
 * QR Attachment class.
 *
 * Handles saving QR code images to the WordPress media library and
 * storing the resulting attachment ID as post meta.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * QR_Attachment
 *
 * Registers the `_prc_seo_qr_attachment_id` post meta key and a REST endpoint
 * that accepts a base64-encoded PNG from the block editor, uploads it to the
 * media library, and stores the returned attachment ID. Idempotent — returns
 * the existing attachment without re-uploading if one already exists.
 */
class QR_Attachment {
	/**
	 * Post meta key that stores the QR code attachment ID.
	 */
	const META_KEY = '_prc_seo_qr_attachment_id';

	/**
	 * REST namespace shared across prc-schema-seo endpoints.
	 */
	const REST_NAMESPACE = 'prc-schema-seo/v1';

	/**
	 * @var Loader
	 */
	private $loader;

	/**
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;
		$this->init();
	}

	private function init(): void {
		$this->loader->add_action( 'init', $this, 'register_meta' );
		$this->loader->add_action( 'rest_api_init', $this, 'register_rest_route' );
	}

	/**
	 * Register the QR attachment ID meta key for all public post types that
	 * support prc-schema-seo, so the value is exposed via the REST `meta` field.
	 *
	 * @hook init
	 */
	public function register_meta(): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_supports( $post_type, 'prc-schema-seo' ) ) {
				continue;
			}

			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'integer',
					'description'       => __( 'Media library attachment ID for the post\'s QR code image.', 'prc-schema-seo' ),
					'single'            => true,
					'default'           => 0,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Register the REST endpoint for uploading a QR code image.
	 *
	 * POST /wp-json/prc-schema-seo/v1/qr-attachment/{post_id}
	 *
	 * Body (JSON): { "image_data": "data:image/png;base64,..." }
	 *
	 * Returns: { "attachment_id": 123, "attachment_url": "https://..." }
	 *
	 * @hook rest_api_init
	 */
	public function register_rest_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/qr-attachment/(?P<post_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_upload' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'post_id'    => array(
						'required'          => true,
						'validate_callback' => static fn( $param ) => is_numeric( $param ) && (int) $param > 0,
						'sanitize_callback' => 'absint',
					),
					'image_data' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Base64-encoded PNG (data URI or raw base64).', 'prc-schema-seo' ),
					),
					'force'      => array(
						'required'    => false,
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'When true, regenerate and replace any existing QR attachment.', 'prc-schema-seo' ),
					),
				),
			)
		);
	}

	/**
	 * Check permission for the upload endpoint.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return bool|\WP_Error
	 */
	public function check_permission( \WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You are not allowed to upload media for this post.', 'prc-schema-seo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Handle the QR image upload request.
	 *
	 * When an attachment already exists and `force` is false, returns the
	 * existing attachment without performing a new upload.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_upload( \WP_REST_Request $request ) {
		$post_id    = (int) $request->get_param( 'post_id' );
		$image_data = $request->get_param( 'image_data' );
		$force      = (bool) $request->get_param( 'force' );

		// Return existing attachment unless caller explicitly forces regeneration.
		if ( ! $force ) {
			$existing_id = (int) get_post_meta( $post_id, self::META_KEY, true );
			if ( $existing_id > 0 && wp_attachment_is_image( $existing_id ) ) {
				return rest_ensure_response(
					array(
						'attachment_id'  => $existing_id,
						'attachment_url' => wp_get_attachment_url( $existing_id ),
					)
				);
			}
		}

		// Decode base64 PNG (accepts both raw base64 and data URI).
		$raw_base64 = preg_replace( '/^data:image\/png;base64,/', '', $image_data );
		$image_bits = base64_decode( $raw_base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $image_bits || empty( $image_bits ) ) {
			return new \WP_Error(
				'invalid_image_data',
				__( 'The image_data parameter could not be decoded as a valid PNG.', 'prc-schema-seo' ),
				array( 'status' => 400 )
			);
		}

		// Basic PNG magic-bytes sanity check (\x89PNG).
		if ( substr( $image_bits, 0, 4 ) !== "\x89PNG" ) {
			return new \WP_Error(
				'invalid_image_format',
				__( 'Only PNG images are accepted.', 'prc-schema-seo' ),
				array( 'status' => 400 )
			);
		}

		// Ensure wp_upload_bits and media functions are available.
		if ( ! function_exists( 'wp_upload_bits' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$filename = sprintf( 'qr-shortlink-%d.png', $post_id );
		$upload   = wp_upload_bits( $filename, null, $image_bits );

		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error(
				'upload_failed',
				/* translators: %s: upload error message */
				sprintf( __( 'File upload failed: %s', 'prc-schema-seo' ), $upload['error'] ),
				array( 'status' => 500 )
			);
		}

		$attachment = array(
			'post_title'     => sprintf( __( 'QR Code — Post %d', 'prc-schema-seo' ), $post_id ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/png',
			'post_parent'    => $post_id,
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id, true );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Tag the attachment as hidden so it is excluded from the media library UI.
		wp_set_object_terms( $attachment_id, 'hidden', '_media_visibility' );

		// If force-replacing, delete the old attachment.
		$old_id = (int) get_post_meta( $post_id, self::META_KEY, true );
		if ( $force && $old_id > 0 && $old_id !== $attachment_id ) {
			wp_delete_attachment( $old_id, true );
		}

		update_post_meta( $post_id, self::META_KEY, $attachment_id );

		return rest_ensure_response(
			array(
				'attachment_id'  => $attachment_id,
				'attachment_url' => wp_get_attachment_url( $attachment_id ),
			)
		);
	}
}
