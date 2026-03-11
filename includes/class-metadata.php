<?php
/**
 * Metadata class.
 *
 * Handles storage, retrieval, and manipulation of SEO metadata.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Metadata class.
 *
 * Manages data storage for SEO data with caching and fallback logic for posts and terms via
 * postmeta and termmeta.
 */
class Metadata {
	/**
	 * Cache group for SEO data.
	 */
	const CACHE_GROUP = 'prc_schema_seo_data_03112026';

	/**
	 * Cache TTL (1 hour).
	 */
	const CACHE_TTL = 3600;

	/**
	 * The loader instance.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;
	}

	/**
	 * Get SEO data for a post with caching.
	 *
	 * @param int $post_id Post ID.
	 * @return array SEO data with defaults applied.
	 */
	public function get_seo_data( $post_id ) {
		// First, check the cache for data.
		$cache_key = 'seo_data_' . $post_id;

		// Check cache only if caching is enabled.
		if ( ! defined( 'PRC_SCHEMA_SEO_DISABLE_CACHE' ) || ! PRC_SCHEMA_SEO_DISABLE_CACHE ) {
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Get the data for the post.
		$raw_data = get_post_meta( $post_id, '_prc_seo_data', true );

		// Merge with defaults.
		$seo_data = wp_parse_args( $raw_data, $this->get_default_seo_data( $post_id ) );

		// Apply basic fallbacks (WordPress defaults only, no template patterns).
		// Template pattern resolution happens in resolve_for_display() at output time.
		$seo_data = $this->apply_fallbacks( $seo_data, $post_id );

		// Store in cache only if caching is enabled.
		if ( ! defined( 'PRC_SCHEMA_SEO_DISABLE_CACHE' ) || ! PRC_SCHEMA_SEO_DISABLE_CACHE ) {
			wp_cache_set( $cache_key, $seo_data, self::CACHE_GROUP, self::CACHE_TTL );
		}

		return $seo_data;
	}

	/**
	 * Get default SEO data structure.
	 *
	 * @param int $post_id Post ID.
	 * @return array Default SEO data.
	 */
	private function get_default_seo_data( $post_id ) {
		return array(
			'title'          => null,
			'description'    => null,
			'og_title'       => null,
			'og_description' => null,
			'schema_type'    => $this->get_default_schema_type( get_post_type( $post_id ), $post_id ),
			'noindex'        => false,
			'canonical_url'  => '',
			'primary_terms'  => array(),
			'custom_schema'  => array(),
		);
	}

	/**
	 * Get default schema type for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $post_id   Post ID (optional, for context-specific checks).
	 * @return string Schema.org type.
	 */
	private function get_default_schema_type( $post_type, $post_id = 0 ) {
		$mapping = array(
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

		$filtered = apply_filters(
			'prc_schema_seo_schema_type_default',
			$mapping[ $post_type ] ?? 'WebPage',
			$post_type,
			$post_id
		);
		return $filtered;
	}

	/**
	 * Apply basic fallback logic for SEO data (cached).
	 * This applies WordPress defaults only. Template pattern resolution
	 * happens in resolve_for_display() at output time.
	 *
	 * @param array $seo_data SEO data.
	 * @param int   $post_id  Post ID.
	 * @return array SEO data with basic fallbacks applied.
	 */
	private function apply_fallbacks( $seo_data, $post_id ) {
		// Title fallback to post title.
		if ( empty( $seo_data['title'] ) ) {
			$seo_data['title'] = get_the_title( $post_id );
		}

		// Description fallback to excerpt or trimmed content.
		if ( empty( $seo_data['description'] ) ) {
			$post                    = get_post( $post_id );
			$seo_data['description'] = ! empty( $post->post_excerpt )
				? $post->post_excerpt
				: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		}

		// Open Graph title fallback.
		if ( empty( $seo_data['og_title'] ) ) {
			$seo_data['og_title'] = $seo_data['title'];
		}

		// Open Graph description fallback.
		if ( empty( $seo_data['og_description'] ) ) {
			$seo_data['og_description'] = $seo_data['description'];
		}

		// Open Graph image fallback.
		// We always default to Art Direction social image, then fall back to post thumbnail.
		$og_image_id = null;
		if ( function_exists( '\\PRC\\Platform\\Art_Direction\\get' ) ) {
			$social_art = \PRC\Platform\Art_Direction\get( $post_id, 'social' );
			if ( false !== $social_art && ! empty( $social_art['url'] ) ) {
				$og_image_id = $social_art['id'];
			}
		}
		// If no social art, fall back to the post thumbnail.
		if ( empty( $og_image_id ) ) {
			$og_image_id = get_post_thumbnail_id( $post_id );
		}
		$seo_data['og_image'] = $og_image_id;

		return $seo_data;
	}

	/**
	 * Resolve template patterns for display.
	 * This method applies template default patterns and should be called
	 * at output time (in Meta_Tags and Generator classes), not during caching.
	 *
	 * @param array $seo_data SEO data from get_seo_data().
	 * @param int   $post_id  Post ID.
	 * @return array SEO data with template patterns resolved.
	 */
	public function resolve_for_display( $seo_data, $post_id ) {
		// Strip numeric prefixes from titles (e.g., "1. Introduction" → "Introduction").
		// This is commonly used for child posts that display numbers on-page but shouldn't in SEO.
		$seo_data['title'] = preg_replace( '/^\d+\.\s+/', '', $seo_data['title'] );

		// Resolve post-level tokens (%post_title%, %primary_category%, etc.) before template wrapping.
		// use_raw_post_title prevents circular reference when stored title contains %post_title%.
		$context = Template_Context::get_current_context();
		if ( Token_Resolver::has_tokens( $seo_data['title'] ) ) {
			$seo_data['title'] = Token_Resolver::resolve( $seo_data['title'], $post_id, $context, array(), true );
		}
		if ( Token_Resolver::has_tokens( $seo_data['description'] ) ) {
			$seo_data['description'] = Token_Resolver::resolve( $seo_data['description'], $post_id, $context, array(), true );
		}
		if ( ! empty( $seo_data['og_title'] ) && Token_Resolver::has_tokens( $seo_data['og_title'] ) ) {
			$seo_data['og_title'] = Token_Resolver::resolve( $seo_data['og_title'], $post_id, $context, array(), true );
		}
		if ( ! empty( $seo_data['og_description'] ) && Token_Resolver::has_tokens( $seo_data['og_description'] ) ) {
			$seo_data['og_description'] = Token_Resolver::resolve( $seo_data['og_description'], $post_id, $context, array(), true );
		}

		// Apply template pattern for title.
		$seo_data['title'] = apply_filters( 'prc_schema_seo_title', $seo_data['title'], $post_id );

		// Apply template pattern for description.
		$seo_data['description'] = apply_filters(
			'prc_schema_seo_description_fallback',
			$seo_data['description'],
			$post_id
		);

		// Apply template default fallback for OG image.
		$seo_data['og_image'] = apply_filters(
			'prc_schema_seo_og_image_fallback',
			$seo_data['og_image'],
			$post_id
		);

		// Update OG title/description if they still match the base values.
		// This ensures template patterns are applied to OG fields as well.
		$post_title = get_the_title( $post_id );
		// Also strip numeric prefix from post title for comparison.
		$post_title_normalized = preg_replace( '/^\d+\.\s+/', '', $post_title );
		if ( $seo_data['og_title'] === $post_title || $seo_data['og_title'] === $post_title_normalized ) {
			$seo_data['og_title'] = $seo_data['title'];
		}
		if ( empty( $seo_data['og_description'] ) || $seo_data['og_description'] === $this->get_raw_description( $post_id ) ) {
			$seo_data['og_description'] = $seo_data['description'];
		}

		// Decode HTML entities so downstream consumers (meta tag attributes, JSON-LD)
		// receive plain text rather than HTML-encoded strings.
		$text_fields = array( 'title', 'description', 'og_title', 'og_description' );
		foreach ( $text_fields as $field ) {
			if ( ! empty( $seo_data[ $field ] ) ) {
				$seo_data[ $field ] = $this->decode_html_entities( $seo_data[ $field ] );
			}
		}

		return $seo_data;
	}

	/**
	 * Decode HTML entities to plain text for downstream output (meta tags, JSON-LD).
	 *
	 * @param string $text Text potentially containing HTML entities.
	 * @return string Plain text with entities decoded.
	 */
	private function decode_html_entities( string $text ): string {
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Get raw description for a post (without template patterns).
	 *
	 * @param int $post_id Post ID.
	 * @return string Raw description.
	 */
	private function get_raw_description( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		return ! empty( $post->post_excerpt )
			? $post->post_excerpt
			: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
	}

	/**
	 * Update SEO data for a post.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $new_data New SEO data.
	 * @return array Sanitized SEO data.
	 */
	public function update_seo_data( $post_id, $new_data ) {
		// Get existing data
		$existing = get_post_meta( $post_id, '_prc_seo_data', true );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		// Merge new data
		$merged = array_merge( $existing, $new_data );

		// Sanitize
		$sanitized = $this->sanitize_seo_data( $merged );

		// Update
		update_post_meta( $post_id, '_prc_seo_data', $sanitized );

		// Invalidate caches
		$this->clear_cache( $post_id );

		return $sanitized;
	}

	/**
	 * Sanitize SEO data.
	 *
	 * @param array $data Raw SEO data.
	 * @return array Sanitized SEO data.
	 */
	public function sanitize_seo_data( $data ) {
		$sanitized = array();

		// Title (255 chars max)
		if ( isset( $data['title'] ) ) {
			$sanitized['title'] = sanitize_text_field( $data['title'] );
			$sanitized['title'] = mb_substr( $sanitized['title'], 0, 255 );
		}

		// Description (500 chars max)
		if ( isset( $data['description'] ) ) {
			$sanitized['description'] = sanitize_textarea_field( $data['description'] );
			$sanitized['description'] = mb_substr( $sanitized['description'], 0, 500 );
		}

		// OG title (255 chars max)
		if ( isset( $data['og_title'] ) ) {
			$sanitized['og_title'] = sanitize_text_field( $data['og_title'] );
			$sanitized['og_title'] = mb_substr( $sanitized['og_title'], 0, 255 );
		}

		// OG description (500 chars max)
		if ( isset( $data['og_description'] ) ) {
			$sanitized['og_description'] = sanitize_textarea_field( $data['og_description'] );
			$sanitized['og_description'] = mb_substr( $sanitized['og_description'], 0, 500 );
		}

		// OG image
		if ( isset( $data['og_image'] ) ) {
			$sanitized['og_image'] = absint( $data['og_image'] );
			if ( $sanitized['og_image'] && ! wp_attachment_is_image( $sanitized['og_image'] ) ) {
				$sanitized['og_image'] = null;
			}
		}

		// Schema type
		if ( isset( $data['schema_type'] ) ) {
			$sanitized['schema_type'] = sanitize_text_field( $data['schema_type'] );
		}

		// Noindex
		if ( isset( $data['noindex'] ) ) {
			$sanitized['noindex'] = rest_sanitize_boolean( $data['noindex'] );
		}

		// Canonical URL
		if ( isset( $data['canonical_url'] ) ) {
			$sanitized['canonical_url'] = esc_url_raw( $data['canonical_url'] );
			// Empty string becomes null
			if ( empty( $sanitized['canonical_url'] ) ) {
				$sanitized['canonical_url'] = '';
			}
		}

		// Primary terms
		if ( isset( $data['primary_terms'] ) && is_array( $data['primary_terms'] ) ) {
			$sanitized['primary_terms'] = Primary_Term::sanitize( $data['primary_terms'] );
		}

		// Custom schema (limited depth)
		if ( isset( $data['custom_schema'] ) && is_array( $data['custom_schema'] ) ) {
			$sanitized['custom_schema'] = $this->sanitize_array_recursive( $data['custom_schema'], 3 );
		}

		return $sanitized;
	}

	/**
	 * Recursively sanitize array with depth limit.
	 *
	 * @param array $array Array to sanitize.
	 * @param int   $depth Maximum depth.
	 * @return array Sanitized array.
	 */
	private function sanitize_array_recursive( $array, $depth = 3 ) {
		if ( $depth <= 0 ) {
			return array();
		}

		$sanitized = array();
		foreach ( $array as $key => $value ) {
			$key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_array_recursive( $value, $depth - 1 );
			} elseif ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $key ] = $value;
			}
		}
		return $sanitized;
	}

	/**
	 * Clear cache for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public function clear_cache( $post_id ) {
		wp_cache_delete( 'seo_data_' . $post_id, self::CACHE_GROUP );
		wp_cache_delete( 'schema_' . $post_id, Generator::CACHE_GROUP );
		wp_cache_delete( 'meta_tags_' . $post_id, Meta_Tags::CACHE_GROUP );

		do_action( 'prc_schema_seo_cache_cleared', $post_id );
	}
}
