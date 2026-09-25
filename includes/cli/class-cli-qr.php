<?php
/**
 * CLI QR utilities for PRC Schema SEO.
 *
 * Generate a branded QR PNG for an arbitrary URL and save it to the
 * media library as a visible attachment.
 *
 * Usage:
 *   wp prc seo generate-qr 'https://example.com/path/'
 *   wp prc seo generate-qr 'https://example.com/path/' --porcelain
 *
 * @package PRC\Platform\Schema_SEO
 */

declare(strict_types=1);

namespace PRC\Platform\Schema_SEO;

use WP_CLI;
use WP_CLI\Utils;
use WPCOM_VIP_CLI_Command;

if ( ! class_exists( 'WPCOM_VIP_CLI_Command' ) ) {
	return;
}

/**
 * CLI_QR class.
 */
class CLI_QR extends WPCOM_VIP_CLI_Command {

	/**
	 * Generate a branded QR code PNG for a URL and save it to the media library.
	 *
	 * The URL is a positional argument so it does not collide with WP-CLI's
	 * global `--url` flag (required on multisite to select the site).
	 *
	 * The resulting attachment is visible in the media library (not tagged
	 * `_media_visibility=hidden`).
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : Absolute http or https URL to encode in the QR code.
	 *
	 * [--size=<px>]
	 * : Square PNG size in pixels. Default: 512. Clamped to 128–2048.
	 *
	 * [--skip-logo]
	 * : Skip the center brand mark.
	 *
	 * [--porcelain]
	 * : Print only the attachment URL.
	 *
	 * ## EXAMPLES
	 *
	 *     wp prc seo generate-qr 'https://example.com/topic/politics/'
	 *     wp prc seo generate-qr 'https://example.com/' --skip-logo --size=800
	 *     wp prc seo generate-qr 'https://example.com/' --porcelain
	 *
	 * @param array $args  Positional args.
	 * @param array $assoc Assoc args.
	 * @return void
	 */
	public function generate( $args, $assoc ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		$raw_url   = isset( $args[0] ) ? (string) $args[0] : '';
		$size      = isset( $assoc['size'] ) ? absint( $assoc['size'] ) : QR_Generator::DEFAULT_SIZE;
		$skip_logo = (bool) Utils\get_flag_value( $assoc, 'skip-logo', false );
		$porcelain = (bool) Utils\get_flag_value( $assoc, 'porcelain', false );

		$target_url = QR_Generator::validate_url( $raw_url );
		if ( is_wp_error( $target_url ) ) {
			WP_CLI::error( $target_url->get_error_message() );
			return;
		}

		$logo_applied = false;
		$png          = QR_Generator::render_png( $target_url, $size, ! $skip_logo, $logo_applied );
		if ( is_wp_error( $png ) ) {
			WP_CLI::error( $png->get_error_message() );
			return;
		}

		if ( ! $skip_logo && ! $logo_applied ) {
			WP_CLI::warning( 'Center logo could not be loaded; QR was saved without a brand mark.' );
		}

		$attachment_id = QR_Generator::save_to_media_library( $png, $target_url );
		if ( is_wp_error( $attachment_id ) ) {
			WP_CLI::error( $attachment_id->get_error_message() );
			return;
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );
		if ( ! is_string( $attachment_url ) || '' === $attachment_url ) {
			WP_CLI::error( sprintf( 'QR attachment %d was created but has no URL.', $attachment_id ) );
			return;
		}

		if ( $porcelain ) {
			WP_CLI::line( $attachment_url );
			return;
		}

		WP_CLI::success(
			sprintf(
				'QR code saved. Attachment ID: %d URL: %s',
				$attachment_id,
				$attachment_url
			)
		);
	}
}
