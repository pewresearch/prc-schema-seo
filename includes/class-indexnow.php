<?php
/**
 * IndexNow integration.
 *
 * Notifies Bing, Yandex, Seznam, and Naver of URL changes via the IndexNow
 * protocol when posts are published, updated, unpublished, or trashed.
 *
 * @package PRC\Platform\Schema_SEO
 * @see     https://www.indexnow.org/documentation
 */

namespace PRC\Platform\Schema_SEO;

class IndexNow {
	const API_ENDPOINT = 'https://api.indexnow.org/indexnow';

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

	private function init() {
		$this->loader->add_action( 'init', $this, 'register_rewrite_rule' );
		$this->loader->add_action( 'template_redirect', $this, 'serve_key_file' );

		$this->loader->add_action( 'prc_platform_async_on_publish', $this, 'on_publish_or_update' );
		$this->loader->add_action( 'prc_platform_async_on_update', $this, 'on_publish_or_update' );
		$this->loader->add_action( 'prc_platform_async_on_untrash', $this, 'on_publish_or_update' );
		$this->loader->add_action( 'prc_platform_async_on_unpublish', $this, 'on_removal' );
		$this->loader->add_action( 'prc_platform_async_on_trash', $this, 'on_removal' );
	}

	/**
	 * Get the IndexNow API key from platform config.
	 *
	 * The key is a public verification token (not a secret) that must also
	 * be served at {site_url}/{key}.txt for IndexNow to accept submissions.
	 * Defined in vip-config/keys-and-tokens.php (PRC_PLATFORM_INDEXNOW_KEY).
	 *
	 * @return string 32-character hex key, or empty string if not configured.
	 */
	public function get_api_key(): string {
		if ( ! defined( 'PRC_PLATFORM_INDEXNOW_KEY' ) || empty( PRC_PLATFORM_INDEXNOW_KEY ) ) {
			return '';
		}
		return PRC_PLATFORM_INDEXNOW_KEY;
	}

	/**
	 * Register a rewrite rule so the key verification file is served
	 * without an actual file on disk (VIP doesn't allow file writes).
	 *
	 * @hook init
	 */
	public function register_rewrite_rule() {
		$key = $this->get_api_key();
		if ( '' === $key ) {
			return;
		}
		add_rewrite_rule(
			'^' . preg_quote( $key, '/' ) . '\.txt$',
			'index.php?indexnow_key_file=1',
			'top'
		);
		add_rewrite_tag( '%indexnow_key_file%', '([0-9]+)' );
	}

	/**
	 * Serve the key verification file as plain text.
	 *
	 * @hook template_redirect
	 */
	public function serve_key_file() {
		if ( ! get_query_var( 'indexnow_key_file' ) ) {
			return;
		}

		$key = $this->get_api_key();
		if ( '' === $key ) {
			return;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo esc_html( $key );
		exit;
	}

	/**
	 * Handle publish and update events. Skips noindex posts.
	 *
	 * @param object $ref_post Extended WP_Post object from the pipeline.
	 */
	public function on_publish_or_update( $ref_post ) {
		if ( $this->is_noindex( $ref_post->ID ) ) {
			return;
		}

		$this->submit_for_post( $ref_post );
	}

	/**
	 * Handle unpublish and trash events.
	 * Notifying search engines about removed content is valid per the protocol.
	 * Uses the last stored public URL because WordPress has already mutated the
	 * post (trashed slug, draft permalink) by the time the pipeline runs.
	 *
	 * @param object $ref_post Extended WP_Post object from the pipeline.
	 */
	public function on_removal( $ref_post ) {
		$this->submit_for_post( $ref_post, true );
	}

	/**
	 * Check if a post is marked noindex.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_noindex( int $post_id ): bool {
		$metadata = new Metadata( $this->loader );
		$seo_data = $metadata->get_seo_data( $post_id );

		return (bool) apply_filters(
			'prc_schema_seo_noindex',
			! empty( $seo_data['noindex'] ),
			$post_id,
			$seo_data
		);
	}

	/**
	 * Submit a single post URL to IndexNow.
	 *
	 * @param object $ref_post    Extended WP_Post object from the pipeline.
	 * @param bool   $for_removal True when notifying removal (unpublish/trash); use last stored public URL.
	 */
	private function submit_for_post( $ref_post, $for_removal = false ) {
		if ( ! apply_filters( 'prc_schema_seo_indexnow_enabled', true ) ) {
			return;
		}

		if ( $for_removal ) {
			$url = get_post_meta( $ref_post->ID, '_prc_schema_seo_last_public_url', true );
			if ( empty( $url ) || ! is_string( $url ) ) {
				return;
			}
		} else {
			$url = ! empty( $ref_post->canonical_url ) ? $ref_post->canonical_url : get_permalink( $ref_post->ID );
			if ( ! empty( $url ) && ! is_wp_error( $url ) ) {
				update_post_meta( $ref_post->ID, '_prc_schema_seo_last_public_url', $url );
			}
		}

		if ( empty( $url ) || is_wp_error( $url ) ) {
			return;
		}

		$key = $this->get_api_key();
		if ( '' === $key ) {
			return;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		$body = array(
			'host'        => $host,
			'key'         => $key,
			'keyLocation' => home_url( '/' . $key . '.txt' ),
			'urlList'     => array( $url ),
		);

		wp_remote_post(
			self::API_ENDPOINT,
			array(
				'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'     => wp_json_encode( $body ),
				'timeout'  => 5,
				'blocking' => false,
			)
		);

		update_post_meta( $ref_post->ID, '_prc_schema_seo_indexnow_submitted_at', time() );
	}
}
