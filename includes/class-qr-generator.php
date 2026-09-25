<?php
/**
 * QR_Generator class.
 *
 * Server-side branded QR PNG generation and media-library sideload.
 *
 * @package PRC\Platform\Schema_SEO
 */

declare(strict_types=1);

namespace PRC\Platform\Schema_SEO;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Generate a Pew-branded QR PNG and save it to the media library.
 */
class QR_Generator {

	/**
	 * Default square PNG size in pixels.
	 */
	public const DEFAULT_SIZE = 512;

	/**
	 * Minimum accepted square size.
	 */
	public const MIN_SIZE = 128;

	/**
	 * Maximum accepted square size.
	 */
	public const MAX_SIZE = 2048;

	/**
	 * Center logo diameter as a fraction of canvas width (matches editor JS).
	 */
	public const LOGO_DIAMETER_FRACTION = 0.25;

	/**
	 * White plate radius relative to the logo radius (matches editor JS).
	 */
	public const PLATE_RADIUS_FACTOR = 1.12;

	/**
	 * QR module dark color RGB (#1a1a2e).
	 *
	 * @var array{0: int, 1: int, 2: int}
	 */
	public const DARK_COLOR = array( 26, 26, 46 );

	/**
	 * QR background / light module RGB.
	 *
	 * @var array{0: int, 1: int, 2: int}
	 */
	public const LIGHT_COLOR = array( 255, 255, 255 );

	/**
	 * Resolve the logo URL for the QR code center overlay.
	 *
	 * Priority: site icon > custom logo > bundled Pew symbol SVG.
	 * Filterable via `prc_schema_seo_qr_logo_url`.
	 *
	 * The editor paints this URL on canvas (SVG is fine). PHP GD cannot
	 * rasterize SVG, so `overlay_logo()` falls back to `assets/symbol-circle.png`.
	 *
	 * @return string Absolute URL to a logo image, or empty string when none is available.
	 */
	public static function get_logo_url(): string {
		return (string) apply_filters( 'prc_schema_seo_qr_logo_url', self::get_default_logo_url() );
	}

	/**
	 * Sanitize and validate an absolute http(s) URL for encoding.
	 *
	 * @param string $url Raw URL.
	 * @return string|\WP_Error Sanitized URL or error.
	 */
	public static function validate_url( string $url ): string|\WP_Error {
		$url = esc_url_raw( trim( $url ) );

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new \WP_Error(
				'invalid_url',
				__( 'Provide a valid http or https URL.', 'prc-schema-seo' )
			);
		}

		return $url;
	}

	/**
	 * Clamp a requested PNG size to the supported range.
	 *
	 * @param int $size Requested square size in pixels.
	 * @return int
	 */
	public static function normalize_size( int $size ): int {
		if ( $size <= 0 ) {
			return self::DEFAULT_SIZE;
		}

		return max( self::MIN_SIZE, min( self::MAX_SIZE, $size ) );
	}

	/**
	 * Render a branded QR PNG for a URL.
	 *
	 * @param string    $url          Absolute http(s) URL (already validated).
	 * @param int       $size         Square size in pixels.
	 * @param bool      $with_logo    Whether to overlay the site logo.
	 * @param bool|null $logo_applied Set to true when the center mark was drawn.
	 * @return string|\WP_Error PNG binary or error.
	 */
	public static function render_png( string $url, int $size = self::DEFAULT_SIZE, bool $with_logo = true, ?bool &$logo_applied = null ): string|\WP_Error {
		$logo_applied = false;
		if ( ! class_exists( QRCode::class ) ) {
			return new \WP_Error(
				'missing_qr_library',
				__( 'The QR library (chillerlan/php-qrcode) is not available.', 'prc-schema-seo' )
			);
		}

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return new \WP_Error(
				'missing_gd',
				__( 'The PHP GD extension is required to generate QR images.', 'prc-schema-seo' )
			);
		}

		$size = self::normalize_size( $size );

		$options = new QROptions(
			array(
				'outputType'     => QROutputInterface::GDIMAGE_PNG,
				'outputBase64'   => false,
				'returnResource' => true,
				'eccLevel'       => EccLevel::H,
				'quietzoneSize'  => 2,
				'scale'          => 10,
				'bgColor'        => self::LIGHT_COLOR,
				'moduleValues'   => self::module_values(),
			)
		);

		try {
			$resource = ( new QRCode( $options ) )->render( $url );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'qr_render_failed',
				sprintf(
					/* translators: %s: exception message */
					__( 'QR generation failed: %s', 'prc-schema-seo' ),
					$e->getMessage()
				)
			);
		}

		if ( ! $resource instanceof \GdImage ) {
			return new \WP_Error(
				'qr_render_failed',
				__( 'QR generation did not return an image resource.', 'prc-schema-seo' )
			);
		}

		$canvas = self::resize_square( $resource, $size );
		imagedestroy( $resource );

		if ( $with_logo ) {
			$logo_applied = self::overlay_logo( $canvas, $size );
		}

		ob_start();
		imagepng( $canvas );
		$bits = ob_get_clean();
		imagedestroy( $canvas );

		if ( ! is_string( $bits ) || '' === $bits ) {
			return new \WP_Error(
				'qr_encode_failed',
				__( 'Could not encode the QR image as PNG.', 'prc-schema-seo' )
			);
		}

		return $bits;
	}

	/**
	 * Sideload a PNG into the media library as a visible, unattached image.
	 *
	 * Does not set `_media_visibility=hidden` or `prc_hide_media`.
	 *
	 * @param string $image_bits PNG binary.
	 * @param string $target_url Encoded URL (for title / filename).
	 * @return int|\WP_Error Attachment ID or error.
	 */
	public static function save_to_media_library( string $image_bits, string $target_url ): int|\WP_Error {
		if ( substr( $image_bits, 0, 4 ) !== "\x89PNG" ) {
			return new \WP_Error(
				'invalid_image_format',
				__( 'Only PNG images are accepted.', 'prc-schema-seo' )
			);
		}

		if ( ! function_exists( 'wp_upload_bits' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$filename = sprintf( 'qr-custom-%s.png', substr( md5( $target_url ), 0, 12 ) );
		$upload   = wp_upload_bits( $filename, null, $image_bits );

		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error(
				'upload_failed',
				sprintf(
					/* translators: %s: upload error message */
					__( 'File upload failed: %s', 'prc-schema-seo' ),
					$upload['error']
				)
			);
		}

		$title = sprintf(
			/* translators: %s: encoded URL */
			__( 'QR Code — %s', 'prc-schema-seo' ),
			$target_url
		);
		if ( strlen( $title ) > 180 ) {
			$title = substr( $title, 0, 177 ) . '...';
		}

		$attachment = array(
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/png',
			'post_parent'    => 0,
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'], 0, true );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		update_post_meta( $attachment_id, '_prc_seo_qr_target_url', $target_url );

		return (int) $attachment_id;
	}

	/**
	 * Default logo URL before the `prc_schema_seo_qr_logo_url` filter.
	 *
	 * @return string
	 */
	private static function get_default_logo_url(): string {
		$site_icon = get_site_icon_url( 512 );
		if ( is_string( $site_icon ) && '' !== $site_icon ) {
			return $site_icon;
		}

		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_url = wp_get_attachment_image_url( (int) $custom_logo_id, 'medium' );
			if ( $logo_url ) {
				return $logo_url;
			}
		}

		return self::bundled_symbol_svg_url();
	}

	/**
	 * Bundled Pew symbol SVG URL (editor canvas can paint SVG).
	 *
	 * @return string
	 */
	private static function bundled_symbol_svg_url(): string {
		if ( ! defined( 'PRC_SCHEMA_SEO_FILE' ) || ! function_exists( 'plugins_url' ) ) {
			return '';
		}

		return plugins_url( 'assets/symbol.svg', PRC_SCHEMA_SEO_FILE );
	}

	/**
	 * Attachment ID that produced the default logo URL.
	 *
	 * @return int
	 */
	private static function get_default_logo_attachment_id(): int {
		$site_icon = (int) get_option( 'site_icon' );
		if ( $site_icon > 0 ) {
			return $site_icon;
		}

		return (int) get_theme_mod( 'custom_logo' );
	}

	/**
	 * Module color map matching the editor QR (#1a1a2e on white).
	 *
	 * @return array<int, array{0: int, 1: int, 2: int}>
	 */
	private static function module_values(): array {
		$dark  = self::DARK_COLOR;
		$light = self::LIGHT_COLOR;

		return array(
			QRMatrix::M_FINDER_DARK    => $dark,
			QRMatrix::M_FINDER_DOT     => $dark,
			QRMatrix::M_FINDER         => $light,
			QRMatrix::M_ALIGNMENT_DARK => $dark,
			QRMatrix::M_ALIGNMENT      => $light,
			QRMatrix::M_TIMING_DARK    => $dark,
			QRMatrix::M_TIMING         => $light,
			QRMatrix::M_FORMAT_DARK    => $dark,
			QRMatrix::M_FORMAT         => $light,
			QRMatrix::M_VERSION_DARK   => $dark,
			QRMatrix::M_VERSION        => $light,
			QRMatrix::M_DATA_DARK      => $dark,
			QRMatrix::M_DATA           => $light,
			QRMatrix::M_DARKMODULE     => $dark,
			QRMatrix::M_SEPARATOR      => $light,
			QRMatrix::M_QUIETZONE      => $light,
		);
	}

	/**
	 * Resize a QR image to a square canvas.
	 *
	 * @param \GdImage $src  Source image.
	 * @param int      $size Target size.
	 * @return \GdImage
	 */
	private static function resize_square( \GdImage $src, int $size ): \GdImage {
		$dst   = imagecreatetruecolor( $size, $size );
		$white = imagecolorallocate( $dst, self::LIGHT_COLOR[0], self::LIGHT_COLOR[1], self::LIGHT_COLOR[2] );
		imagefill( $dst, 0, 0, $white );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $size, $size, imagesx( $src ), imagesy( $src ) );
		return $dst;
	}

	/**
	 * Overlay the site logo in a white circular plate.
	 *
	 * SVG logos (the PRC site icon) cannot be decoded by GD. Imagick is tried
	 * first; if that fails, the bundled `assets/symbol-circle.png` is used.
	 *
	 * @param \GdImage $canvas Target QR canvas.
	 * @param int      $size   Canvas size.
	 * @return bool True when a center mark was drawn.
	 */
	private static function overlay_logo( \GdImage $canvas, int $size ): bool {
		$logo  = null;
		$bytes = self::load_logo_bytes();
		if ( null !== $bytes ) {
			$logo = self::gd_image_from_bytes( $bytes );
		}

		if ( ! $logo instanceof \GdImage ) {
			$logo = self::gd_image_from_bundled_symbol();
		}

		if ( ! $logo instanceof \GdImage ) {
			return false;
		}

		$logo_diameter = (int) round( $size * self::LOGO_DIAMETER_FRACTION );
		if ( $logo_diameter < 8 ) {
			imagedestroy( $logo );
			return false;
		}

		$logo_r  = $logo_diameter / 2;
		$plate_r = $logo_r * self::PLATE_RADIUS_FACTOR;
		$cx      = (int) round( $size / 2 );
		$cy      = (int) round( $size / 2 );
		$white   = imagecolorallocate( $canvas, 255, 255, 255 );
		imagefilledellipse( $canvas, $cx, $cy, (int) round( $plate_r * 2 ), (int) round( $plate_r * 2 ), $white );

		$dest_x = (int) round( $cx - $logo_r );
		$dest_y = (int) round( $cy - $logo_r );
		self::copy_circle( $canvas, $logo, $dest_x, $dest_y, $logo_diameter );
		imagedestroy( $logo );

		return true;
	}

	/**
	 * Load logo image bytes from a local file or, if needed, via download_url().
	 *
	 * @return string|null
	 */
	private static function load_logo_bytes(): ?string {
		$url = self::get_logo_url();
		if ( '' === $url ) {
			return null;
		}

		$path = self::local_path_for_logo_url( $url );
		if ( null !== $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local plugin, content, or attachment path.
			$bytes = file_get_contents( $path );
			return is_string( $bytes ) && '' !== $bytes ? $bytes : null;
		}

		if ( ! function_exists( 'download_url' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'download_url' ) ) {
			return null;
		}

		$tmp = download_url( $url, 15 );
		if ( is_wp_error( $tmp ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- temp file from download_url().
		$bytes = file_get_contents( $tmp );
		wp_delete_file( $tmp );

		return is_string( $bytes ) && '' !== $bytes ? $bytes : null;
	}

	/**
	 * Resolve a logo URL to a readable local path when possible.
	 *
	 * @param string $url Logo URL.
	 * @return string|null
	 */
	private static function local_path_for_logo_url( string $url ): ?string {
		if ( self::get_default_logo_url() === $url ) {
			$attachment_id = self::get_default_logo_attachment_id();
			$file          = self::readable_attached_file( $attachment_id );
			if ( null !== $file ) {
				return $file;
			}
		}

		$attachment_id = 0;
		if ( function_exists( 'wpcom_vip_attachment_url_to_postid' ) ) {
			$attachment_id = (int) wpcom_vip_attachment_url_to_postid( $url );
		} elseif ( function_exists( 'attachment_url_to_postid' ) ) {
			$attachment_id = (int) attachment_url_to_postid( $url ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid -- fallback when VIP helper is absent.
		}
		$file = self::readable_attached_file( $attachment_id );
		if ( null !== $file ) {
			return $file;
		}

		return self::path_under_known_url_prefix( $url );
	}

	/**
	 * Attached file path when the attachment ID is valid and readable.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|null
	 */
	private static function readable_attached_file( int $attachment_id ): ?string {
		if ( $attachment_id <= 0 || ! function_exists( 'get_attached_file' ) ) {
			return null;
		}

		$file = get_attached_file( $attachment_id );
		if ( is_string( $file ) && is_readable( $file ) ) {
			return $file;
		}

		return null;
	}

	/**
	 * Map a content or plugin URL onto a local filesystem path.
	 *
	 * The PRC site icon filter points at `wp-content/images/symbol-alt.svg`,
	 * which is not a media attachment.
	 *
	 * @param string $url Logo URL.
	 * @return string|null
	 */
	private static function path_under_known_url_prefix( string $url ): ?string {
		$candidates = array();

		if ( function_exists( 'content_url' ) && defined( 'WP_CONTENT_DIR' ) ) {
			$candidates[] = array(
				'url'  => trailingslashit( content_url() ),
				'root' => trailingslashit( WP_CONTENT_DIR ),
			);
		}

		if ( defined( 'PRC_SCHEMA_SEO_FILE' ) && function_exists( 'plugins_url' ) && function_exists( 'plugin_dir_path' ) ) {
			$candidates[] = array(
				'url'  => trailingslashit( plugins_url( '', PRC_SCHEMA_SEO_FILE ) ),
				'root' => trailingslashit( plugin_dir_path( PRC_SCHEMA_SEO_FILE ) ),
			);
		}

		foreach ( $candidates as $candidate ) {
			if ( ! str_starts_with( $url, $candidate['url'] ) ) {
				continue;
			}
			$rel = rawurldecode( substr( $url, strlen( $candidate['url'] ) ) );
			$rel = ltrim( $rel, '/' );
			if ( str_contains( $rel, '..' ) || str_contains( $rel, "\0" ) ) {
				continue;
			}
			$file = $candidate['root'] . $rel;
			if ( is_readable( $file ) ) {
				return $file;
			}
		}

		return null;
	}

	/**
	 * Create a GD image from raster bytes, converting SVG via Imagick when needed.
	 *
	 * @param string $bytes Image bytes.
	 * @return \GdImage|null
	 */
	private static function gd_image_from_bytes( string $bytes ): ?\GdImage {
		if ( self::bytes_look_like_svg( $bytes ) ) {
			return self::gd_image_from_svg_bytes( $bytes );
		}

		$image = @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- GD warns on unsupported formats.
		return $image instanceof \GdImage ? $image : null;
	}

	/**
	 * Whether raw bytes look like SVG markup.
	 *
	 * @param string $bytes Image bytes.
	 * @return bool
	 */
	private static function bytes_look_like_svg( string $bytes ): bool {
		$trim = ltrim( $bytes );
		return str_starts_with( $trim, '<svg' ) || str_starts_with( $trim, '<?xml' );
	}

	/**
	 * Rasterize SVG bytes with Imagick. Returns null when Imagick or the SVG delegate is missing.
	 *
	 * @param string $bytes SVG markup.
	 * @return \GdImage|null
	 */
	private static function gd_image_from_svg_bytes( string $bytes ): ?\GdImage {
		if ( ! class_exists( '\Imagick' ) ) {
			return null;
		}

		try {
			$imagick = new \Imagick();
			$imagick->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
			$imagick->setResolution( 288, 288 );
			$imagick->readImageBlob( $bytes );
			$imagick->setImageFormat( 'png32' );
			$converted = $imagick->getImageBlob();
			$imagick->clear();
			$imagick->destroy();
		} catch ( \Throwable $e ) {
			return null;
		}

		$image = @imagecreatefromstring( $converted ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- converted SVG blob.
		return $image instanceof \GdImage ? $image : null;
	}

	/**
	 * Bundled Pew symbol PNG that GD can overlay without SVG support.
	 *
	 * @return \GdImage|null
	 */
	private static function gd_image_from_bundled_symbol(): ?\GdImage {
		$path = dirname( __DIR__ ) . '/assets/symbol-circle.png';
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$image = imagecreatefrompng( $path );
		return $image instanceof \GdImage ? $image : null;
	}

	/**
	 * Copy a circular crop of $src onto $dst.
	 *
	 * @param \GdImage $dst      Destination canvas.
	 * @param \GdImage $src      Source logo.
	 * @param int      $dst_x    Destination X.
	 * @param int      $dst_y    Destination Y.
	 * @param int      $diameter Circle diameter.
	 * @return void
	 */
	private static function copy_circle( \GdImage $dst, \GdImage $src, int $dst_x, int $dst_y, int $diameter ): void {
		$tmp = imagecreatetruecolor( $diameter, $diameter );
		imagealphablending( $tmp, false );
		imagesavealpha( $tmp, true );
		$clear = imagecolorallocatealpha( $tmp, 0, 0, 0, 127 );
		imagefill( $tmp, 0, 0, $clear );
		imagecopyresampled( $tmp, $src, 0, 0, 0, 0, $diameter, $diameter, imagesx( $src ), imagesy( $src ) );

		$radius = $diameter / 2;
		for ( $y = 0; $y < $diameter; $y++ ) {
			for ( $x = 0; $x < $diameter; $x++ ) {
				$dx = $x - $radius + 0.5;
				$dy = $y - $radius + 0.5;
				if ( ( $dx * $dx ) + ( $dy * $dy ) > $radius * $radius ) {
					imagesetpixel( $tmp, $x, $y, $clear );
				}
			}
		}

		imagealphablending( $dst, true );
		imagecopy( $dst, $tmp, $dst_x, $dst_y, 0, 0, $diameter, $diameter );
		imagedestroy( $tmp );
	}
}
