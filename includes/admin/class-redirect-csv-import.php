<?php
/**
 * Redirect_CSV_Import class.
 *
 * Adds a CSV import UI to the Safe Redirect Manager list table screen
 * and handles file upload via AJAX, delegating to srm_import_file().
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Redirect_CSV_Import class.
 */
class Redirect_CSV_Import {

	/**
	 * AJAX action name.
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'prc_seo_import_redirects_csv';

	/**
	 * Nonce action name.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'prc_seo_import_csv';

	/**
	 * Loader instance.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;

		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_' . self::AJAX_ACTION, $this, 'handle_csv_import' );
	}

	/**
	 * Check whether Safe Redirect Manager is available.
	 *
	 * @return bool
	 */
	private function is_srm_available() {
		return function_exists( 'srm_import_file' );
	}

	/**
	 * Enqueue the import script on the SRM list table screen.
	 *
	 * @hook admin_enqueue_scripts
	 */
	public function enqueue_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-redirect_rule' !== $screen->id ) {
			return;
		}

		if ( ! $this->is_srm_available() ) {
			return;
		}

		if ( ! current_user_can( 'srm_manage_redirects' ) ) {
			return;
		}

		$asset_path = plugin_dir_path( PRC_SCHEMA_SEO_FILE ) . 'build/admin-surfaces/redirect-csv-import/index.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = include $asset_path;

		wp_enqueue_script(
			'prc-seo-redirect-csv-import',
			plugins_url( 'build/admin-surfaces/redirect-csv-import/index.js', PRC_SCHEMA_SEO_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'prc-seo-redirect-csv-import',
			'prcSeoRedirectImport',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'action'   => self::AJAX_ACTION,
			)
		);
	}

	/**
	 * Handle the CSV import AJAX request.
	 *
	 * @hook wp_ajax_prc_seo_import_redirects_csv
	 */
	public function handle_csv_import() {
		// Verify nonce.
		check_ajax_referer( self::NONCE_ACTION );

		// Check capability.
		if ( ! current_user_can( 'srm_manage_redirects' ) ) {
			wp_send_json_error(
				array( 'message' => 'You do not have permission to import redirects.' ),
				403
			);
		}

		// Check SRM availability.
		if ( ! $this->is_srm_available() ) {
			wp_send_json_error(
				array( 'message' => 'Safe Redirect Manager is not active.' ),
				500
			);
		}

		// Validate file upload.
		if ( empty( $_FILES['csv_file'] ) ) {
			wp_send_json_error(
				array( 'message' => 'No file was uploaded.' ),
				400
			);
		}

		$file = $_FILES['csv_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Check for upload errors.
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			wp_send_json_error(
				array( 'message' => 'File upload error (code: ' . (int) $file['error'] . ').' ),
				400
			);
		}

		// Validate file extension.
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $extension ) {
			wp_send_json_error(
				array( 'message' => 'Invalid file type. Please upload a .csv file.' ),
				400
			);
		}

		// Enforce a reasonable file size limit (5 MB).
		$max_size = apply_filters( 'prc_schema_seo_csv_import_max_size', 5 * MB_IN_BYTES );
		if ( $file['size'] > $max_size ) {
			wp_send_json_error(
				array( 'message' => 'File is too large. Maximum size is ' . size_format( $max_size ) . '.' ),
				400
			);
		}

		// Default column mapping matching the standard SRM CLI format.
		$mapping = array(
			'source' => 'source',
			'target' => 'target',
			'regex'  => 'regex',
			'code'   => 'code',
			'order'  => 'order',
			'notes'  => 'notes',
		);

		/**
		 * Filter the column mapping for CSV import.
		 *
		 * @hook prc_schema_seo_csv_import_column_mapping
		 * @param array $mapping Column name mapping.
		 * @return array Filtered mapping.
		 */
		$mapping = apply_filters( 'prc_schema_seo_csv_import_column_mapping', $mapping );

		// Run the import using SRM's built-in function.
		$result = srm_import_file( $file['tmp_name'], $mapping );

		// Clean up the temp file.
		if ( file_exists( $file['tmp_name'] ) ) {
			@unlink( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( false === $result ) {
			wp_send_json_error(
				array( 'message' => 'Failed to process the CSV file. Please check the file format.' ),
				500
			);
		}

		wp_send_json_success(
			array(
				'created' => $result['created'],
				'skipped' => $result['skipped'],
			)
		);
	}
}
