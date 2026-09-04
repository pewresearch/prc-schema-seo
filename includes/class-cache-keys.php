<?php
/**
 * Cache_Keys class.
 *
 * Single source of truth for object-cache groups and key construction used by
 * Metadata, Generator, Meta_Tags, Contact_Resolver, and Parsely_Integration.
 *
 * @package PRC\Platform\Schema_SEO
 */

declare(strict_types=1);

namespace PRC\Platform\Schema_SEO;

/**
 * Cache_Keys
 *
 * Post-level entries (seo_data, schema, meta_tags, contact) plus term/archive/
 * publications schema share GROUP so warm singular reads can use
 * wp_cache_get_multiple() and invalidation can use wp_cache_delete_multiple().
 *
 * Term meta-tag version counters and Parse.ly fragments stay in separate groups
 * with different invalidation lifecycles.
 */
class Cache_Keys {

	/**
	 * Unified group for post-level SEO caches and schema output.
	 *
	 * Bumped from the previous per-layer *_03112026 groups so old keys expire
	 * via TTL after deploy.
	 */
	const GROUP = 'prc_schema_seo_07142026';

	/**
	 * Group for versioned term / home meta-tag fragments.
	 */
	const META_TAGS_TERM_GROUP = 'prc_schema_seo_meta_tags_term_07142026';

	/**
	 * Group for home / term Parse.ly HTML fragments.
	 */
	const PARSELY_GROUP = 'prc_schema_seo_parsely_07142026';

	/**
	 * Default TTL in seconds (1 hour).
	 */
	const TTL = 3600;

	/**
	 * Request-local primed values from wp_cache_get_multiple().
	 *
	 * @var array<string, mixed>
	 */
	private static $primed = array();

	/**
	 * Build a bag key for the request-local prime map.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return string
	 */
	private static function primed_bag_key( string $key, string $group ): string {
		return $group . "\0" . $key;
	}

	/**
	 * Remember a value in the request-local prime map.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function remember( string $key, string $group, $value ): void {
		self::$primed[ self::primed_bag_key( $key, $group ) ] = $value;
	}

	/**
	 * Forget primed values for the given keys.
	 *
	 * @param string[] $keys  Cache keys.
	 * @param string   $group Cache group.
	 * @return void
	 */
	public static function forget( array $keys, string $group ): void {
		foreach ( $keys as $key ) {
			unset( self::$primed[ self::primed_bag_key( (string) $key, $group ) ] );
		}
	}

	/**
	 * Recall a primed value, or fall back to wp_cache_get.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return mixed|false
	 */
	public static function get( string $key, string $group ) {
		$bag = self::primed_bag_key( $key, $group );
		if ( array_key_exists( $bag, self::$primed ) ) {
			return self::$primed[ $bag ];
		}
		return wp_cache_get( $key, $group );
	}

	/**
	 * Set a cache value and update the request-local prime map.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value.
	 * @param string $group      Cache group.
	 * @param int    $expiration TTL.
	 * @return bool
	 */
	public static function set( string $key, $value, string $group, int $expiration = self::TTL ): bool {
		self::remember( $key, $group, $value );
		return wp_cache_set( $key, $value, $group, $expiration );
	}

	/**
	 * Prime known post-level keys for a singular request via get_multiple.
	 *
	 * Does not combine payloads — only warms the request-local map so subsequent
	 * per-layer getters avoid extra Memcached round trips.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function prime_post_level( int $post_id ): void {
		if ( ! self::caching_enabled() ) {
			return;
		}

		$keys       = self::post_level_keys( $post_id );
		$all_primed = true;
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( self::primed_bag_key( $key, self::GROUP ), self::$primed ) ) {
				$all_primed = false;
				break;
			}
		}
		if ( $all_primed ) {
			return;
		}

		$found = self::get_multiple( $keys, self::GROUP );
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $found ) && false !== $found[ $key ] ) {
				self::remember( $key, self::GROUP, $found[ $key ] );
			}
		}
	}

	/**
	 * SEO data cache key for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function seo_data( int $post_id ): string {
		return 'seo_data_' . $post_id;
	}

	/**
	 * JSON-LD schema cache key for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function schema( int $post_id ): string {
		return 'schema_' . $post_id;
	}

	/**
	 * Meta tags HTML cache key for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function meta_tags( int $post_id ): string {
		return 'meta_tags_' . $post_id;
	}

	/**
	 * Contact resolver cache key for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function contact( int $post_id ): string {
		return 'contact_' . $post_id;
	}

	/**
	 * Term archive schema cache key.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public static function term_schema( int $term_id ): string {
		return 'term_schema_' . $term_id;
	}

	/**
	 * Post type archive schema cache key.
	 *
	 * @param string $post_type Post type slug.
	 * @return string
	 */
	public static function post_type_archive_schema( string $post_type ): string {
		return 'post_type_archive_schema_' . $post_type;
	}

	/**
	 * Publications / home CollectionPage schema cache key.
	 *
	 * @return string
	 */
	public static function publications_page_schema(): string {
		return 'publications_page_schema';
	}

	/**
	 * Term meta-tags version counter key.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public static function meta_tags_term_version( int $term_id ): string {
		return 'meta_tags_term_version_' . $term_id;
	}

	/**
	 * Versioned, paginated term meta-tags cache key.
	 *
	 * @param int $term_id Term ID.
	 * @param int $version Cache version.
	 * @param int $paged   Page number.
	 * @return string
	 */
	public static function meta_tags_term_page( int $term_id, int $version, int $paged ): string {
		return 'meta_tags_term_' . $term_id . '_v' . $version . '_page_' . $paged;
	}

	/**
	 * Home meta-tags cache key (paginated).
	 *
	 * @param int $paged Page number.
	 * @return string
	 */
	public static function meta_tags_home_page( int $paged ): string {
		return 'meta_tags_home_page_' . $paged;
	}

	/**
	 * Post type archive meta-tags cache key.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $paged     Page number.
	 * @return string
	 */
	public static function meta_tags_post_type_archive( string $post_type, int $paged ): string {
		return 'meta_tags_post_type_archive_' . $post_type . '_page_' . $paged;
	}

	/**
	 * Parse.ly home fragment cache key.
	 *
	 * @return string
	 */
	public static function parsely_home(): string {
		return 'parsely_tags_home';
	}

	/**
	 * Parse.ly term fragment cache key.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public static function parsely_term( int $term_id ): string {
		return 'parsely_tags_term_' . $term_id;
	}

	/**
	 * Parse.ly post type archive fragment cache key.
	 *
	 * @param string $post_type Post type slug.
	 * @return string
	 */
	public static function parsely_post_type_archive( string $post_type ): string {
		return 'parsely_tags_post_type_archive_' . $post_type;
	}

	/**
	 * Parse.ly singular metadata cache key.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function parsely_post( int $post_id ): string {
		return 'parsely_metadata_' . $post_id;
	}

	/**
	 * Post-level keys that share GROUP and are cleared together.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function post_level_keys( int $post_id ): array {
		return array(
			self::seo_data( $post_id ),
			self::schema( $post_id ),
			self::meta_tags( $post_id ),
			self::contact( $post_id ),
		);
	}

	/**
	 * Whether object caching is enabled for this plugin.
	 *
	 * @return bool
	 */
	public static function caching_enabled(): bool {
		return ! defined( 'PRC_SCHEMA_SEO_DISABLE_CACHE' ) || ! PRC_SCHEMA_SEO_DISABLE_CACHE;
	}

	/**
	 * Delete multiple keys from a group, with a looped fallback.
	 *
	 * @param string[] $keys  Cache keys.
	 * @param string   $group Cache group.
	 * @return void
	 */
	public static function delete_multiple( array $keys, string $group ): void {
		if ( empty( $keys ) ) {
			return;
		}

		self::forget( $keys, $group );

		if ( function_exists( 'wp_cache_delete_multiple' ) ) {
			wp_cache_delete_multiple( $keys, $group );
			return;
		}

		foreach ( $keys as $key ) {
			wp_cache_delete( $key, $group );
		}
	}

	/**
	 * Get multiple keys from a group, with a looped fallback.
	 *
	 * @param string[] $keys  Cache keys.
	 * @param string   $group Cache group.
	 * @return array<string, mixed> Map of key => value; missing keys omit or are false.
	 */
	public static function get_multiple( array $keys, string $group ): array {
		if ( empty( $keys ) ) {
			return array();
		}

		if ( function_exists( 'wp_cache_get_multiple' ) ) {
			$found = wp_cache_get_multiple( $keys, $group );
			return is_array( $found ) ? $found : array();
		}

		$results = array();
		foreach ( $keys as $key ) {
			$results[ $key ] = wp_cache_get( $key, $group );
		}
		return $results;
	}
}
