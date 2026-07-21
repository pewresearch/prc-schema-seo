<?php
/**
 * Google Search Console URL Inspection integration.
 *
 * Queries the URL Inspection API on publish/update to surface Google's index
 * status for each post. Results are cached in post meta with a 24-hour TTL
 * and exposed read-only via the prc_seo_data REST field.
 *
 * Requires a Google service account with Search Console read-only access.
 * The JSON key is stored as PRC_PLATFORM_GSC_SERVICE_ACCOUNT and the Search
 * Console property URL as PRC_PLATFORM_GSC_SITE_URL.
 *
 * @package PRC\Platform\Schema_SEO
 * @see     https://developers.google.com/webmaster-tools/v1/urlInspection.index/inspect
 */

namespace PRC\Platform\Schema_SEO;

class Search_Console {
	const META_KEY       = '_prc_schema_seo_gsc_inspection';
	const TTL_SECONDS    = 86400; // 24 hours
	const TOKEN_TRANSIENT = 'prc_schema_seo_gsc_token';
	const INSPECTION_API = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
	const TOKEN_URI      = 'https://oauth2.googleapis.com/token';
	const SCOPE          = 'https://www.googleapis.com/auth/webmasters.readonly';

	/**
	 * @var Loader
	 */
	private $loader;

	/**
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;

		if ( ! self::is_configured() ) {
			return;
		}

		$this->init();
	}

	private function init() {
		$this->loader->add_action( 'prc_platform_async_on_publish', $this, 'on_publish_or_update' );
		$this->loader->add_action( 'prc_platform_async_on_update', $this, 'on_publish_or_update' );
		$this->loader->add_action( 'rest_api_init', $this, 'register_refresh_route' );
	}

	/**
	 * Whether both required constants are defined and non-empty.
	 */
	public static function is_configured(): bool {
		return defined( 'PRC_PLATFORM_GSC_SERVICE_ACCOUNT' )
			&& ! empty( PRC_PLATFORM_GSC_SERVICE_ACCOUNT )
			&& defined( 'PRC_PLATFORM_GSC_SITE_URL' )
			&& ! empty( PRC_PLATFORM_GSC_SITE_URL );
	}

	/**
	 * Inspect a post after publish/update via the async pipeline tier.
	 *
	 * @param object $ref_post Extended WP_Post from the publish pipeline.
	 */
	public function on_publish_or_update( $ref_post ) {
		$this->inspect_and_cache( (int) $ref_post->ID, true );
	}

	/**
	 * Call the URL Inspection API and store the result in post meta.
	 *
	 * @param int  $post_id       Post ID.
	 * @param bool $force_refresh Skip the TTL check when true.
	 * @return array|false Normalised inspection data or false on failure.
	 */
	public function inspect_and_cache( int $post_id, bool $force_refresh = false ) {
		$url = get_permalink( $post_id );
		if ( ! $url || is_wp_error( $url ) ) {
			return false;
		}

		if ( ! $force_refresh ) {
			$cached = get_post_meta( $post_id, self::META_KEY, true );
			if ( is_array( $cached ) && isset( $cached['fetched_at'] ) ) {
				if ( ( time() - $cached['fetched_at'] ) < self::TTL_SECONDS ) {
					return $cached;
				}
			}
		}

		$result = $this->call_inspection_api( $url );
		if ( false === $result ) {
			return false;
		}

		$result['fetched_at'] = time();
		update_post_meta( $post_id, self::META_KEY, $result );

		return $result;
	}

	/**
	 * Get cached inspection data without calling the API.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Cached data or null.
	 */
	public function get_cached_data( int $post_id ) {
		$cached = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}
		return null;
	}

	// -- REST refresh endpoint ------------------------------------------------

	/**
	 * Register a POST endpoint for manual inspection refresh.
	 *
	 * @hook rest_api_init
	 */
	public function register_refresh_route() {
		register_rest_route(
			'prc-schema-seo/v1',
			'/gsc-inspect/(?P<id>\\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_refresh' ),
				'permission_callback' => function ( $request ) {
					return current_user_can( 'edit_post', (int) $request['id'] );
				},
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Handle a manual refresh request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_refresh( \WP_REST_Request $request ) {
		$post_id = (int) $request['id'];

		$result = $this->inspect_and_cache( $post_id, true );
		if ( false === $result ) {
			return new \WP_Error(
				'gsc_inspection_failed',
				__( 'Failed to retrieve Google Search Console data. Check that the service account has access to the Search Console property.', 'prc-schema-seo' ),
				array( 'status' => 502 )
			);
		}

		return new \WP_REST_Response( $result, 200 );
	}

	// -- Google API plumbing --------------------------------------------------

	/**
	 * POST to the URL Inspection API and return normalised data.
	 *
	 * @param string $url The page URL to inspect.
	 * @return array|false Normalised result or false on failure.
	 */
	private function call_inspection_api( string $url ) {
		$access_token = $this->get_access_token();
		if ( ! $access_token ) {
			return false;
		}

		$url      = $this->get_inspection_url( $url );
		$site_url = PRC_PLATFORM_GSC_SITE_URL;

		$response = wp_remote_post(
			self::INSPECTION_API,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode(
					array(
						'inspectionUrl' => $url,
						'siteUrl'       => $site_url,
					)
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['inspectionResult'] ) ) {
			return false;
		}

		return self::normalize_result( $body['inspectionResult'] );
	}

	/**
	 * Flatten the API response into a simple associative array suitable for
	 * post meta storage and REST output.
	 *
	 * @param array $result inspectionResult from the API.
	 * @return array
	 */
	private static function normalize_result( array $result ): array {
		$index  = $result['indexStatusResult'] ?? array();
		$mobile = $result['mobileUsabilityResult'] ?? array();
		$rich   = $result['richResultsResult'] ?? array();

		return array(
			'verdict'               => $index['verdict'] ?? 'NEUTRAL',
			'coverage_state'        => $index['coverageState'] ?? '',
			'robotstxt_state'       => $index['robotsTxtState'] ?? '',
			'indexing_state'        => $index['indexingState'] ?? '',
			'last_crawl_time'       => $index['lastCrawlTime'] ?? null,
			'page_fetch_state'      => $index['pageFetchState'] ?? '',
			'crawled_as'            => $index['crawledAs'] ?? '',
			'mobile_verdict'        => $mobile['verdict'] ?? '',
			'mobile_issues'         => array_map(
				function ( $issue ) {
					return $issue['message'] ?? '';
				},
				$mobile['issues'] ?? array()
			),
			'rich_results_verdict'  => $rich['verdict'] ?? '',
			'rich_results_issues'   => array_map(
				function ( $issue ) {
					return $issue['issueMessage'] ?? '';
				},
				$rich['issues'] ?? array()
			),
			'inspection_link'       => $result['inspectionResultLink'] ?? '',
		);
	}

	/**
	 * Translate a local/dev permalink to its production equivalent so the
	 * URL Inspection API receives a URL it can actually look up.
	 *
	 * On production this is a no-op. In local or development environments
	 * the site's home_url() prefix is swapped for the production origin.
	 *
	 * @param string $url Permalink as returned by get_permalink().
	 * @return string Production-equivalent URL.
	 */
	private function get_inspection_url( string $url ): string {
		if ( 'production' === wp_get_environment_type() ) {
			return $url;
		}

		$home_url       = trailingslashit( home_url() );
		$production_url = trailingslashit(
			apply_filters( 'prc_schema_seo_gsc_production_url', 'https://www.pewresearch.org' )
		);

		if ( $home_url !== $production_url && str_starts_with( $url, $home_url ) ) {
			$url = $production_url . substr( $url, strlen( $home_url ) );
		}

		return $url;
	}

	// -- Service-account OAuth ------------------------------------------------

	/**
	 * Obtain a Google OAuth 2.0 access token using the service account JWT
	 * bearer flow. Tokens are cached in a transient for their lifetime minus
	 * a 60-second safety buffer.
	 *
	 * @return string|false Access token or false on failure.
	 */
	private function get_access_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( $cached ) {
			return $cached;
		}

		$sa = $this->get_service_account();
		if ( ! $sa ) {
			return false;
		}

		$jwt = $this->create_jwt( $sa );
		if ( ! $jwt ) {
			return false;
		}

		$response = wp_remote_post(
			self::TOKEN_URI,
			array(
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['access_token'] ) ) {
			return false;
		}

		$expires_in = ( $body['expires_in'] ?? 3600 ) - 60;
		set_transient( self::TOKEN_TRANSIENT, $body['access_token'], max( $expires_in, 60 ) );

		return $body['access_token'];
	}

	/**
	 * Decode the service account JSON key from the environment constant.
	 * Accepts either raw JSON or base64-encoded JSON.
	 *
	 * @return array{client_email: string, private_key: string}|false
	 */
	private function get_service_account() {
		$raw = PRC_PLATFORM_GSC_SERVICE_ACCOUNT;

		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) && isset( $decoded['client_email'], $decoded['private_key'] ) ) {
			return $decoded;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded = json_decode( base64_decode( $raw, true ), true );
		if ( is_array( $decoded ) && isset( $decoded['client_email'], $decoded['private_key'] ) ) {
			return $decoded;
		}

		return false;
	}

	/**
	 * Create an RS256-signed JWT for the service account bearer flow.
	 *
	 * @param array $sa Decoded service account JSON key.
	 * @return string|false Signed JWT or false on failure.
	 */
	private function create_jwt( array $sa ) {
		$header = self::base64url_encode(
			wp_json_encode(
				array(
					'alg' => 'RS256',
					'typ' => 'JWT',
				)
			)
		);

		$now    = time();
		$claims = self::base64url_encode(
			wp_json_encode(
				array(
					'iss'   => $sa['client_email'],
					'scope' => self::SCOPE,
					'aud'   => self::TOKEN_URI,
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);

		$signing_input = $header . '.' . $claims;

		$private_key = openssl_pkey_get_private( $sa['private_key'] );
		if ( ! $private_key ) {
			return false;
		}

		$signature = '';
		if ( ! openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 ) ) {
			return false;
		}

		return $signing_input . '.' . self::base64url_encode( $signature );
	}

	/**
	 * URL-safe base64 encoding per RFC 7515.
	 *
	 * @param string $data Raw bytes or string.
	 * @return string Base64url-encoded string.
	 */
	private static function base64url_encode( string $data ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
