<?php
/**
 * Example custom filters for PRC Schema SEO plugin.
 */

// Add a modified date token.
add_filter(
	'prc_schema_seo_pattern_tokens',
	function ( $tokens, $post_id ) {
		$modified                  = get_post( $post_id ) ? get_post_modified_time( 'Y-m-d', false, $post_id ) : '';
		$tokens['%modified_date%'] = $modified;
		return $tokens;
	},
	10,
	2
);

// Force WebPage schema for pages regardless of selected type.
add_filter(
	'prc_schema_seo_schema_type_default',
	function ( $default, $post_type ) {
		if ( 'page' === $post_type ) {
			return 'WebPage';
		}
		return $default;
	},
	10,
	2
);

// Override canonical URL to remove tracking parameters.
add_filter(
	'prc_schema_seo_canonical_url',
	function ( $canonical, $post_id, $seo_data ) {
		$parts = wp_parse_url( $canonical );
		if ( empty( $parts['query'] ) ) {
			return $canonical;
		}
		// Rebuild without query string.
		return trailingslashit( home_url( $parts['path'] ?? '' ) );
	},
	10,
	3
);

// Disable schema output for a specific post ID (example: legacy content).
add_filter(
	'prc_schema_seo_should_output_schema',
	function ( $should, $post_id ) {
		$legacy_ids = array( 42 );
		if ( in_array( $post_id, $legacy_ids, true ) ) {
			return false;
		}
		return $should;
	},
	10,
	2
);

// Log when cache cleared.
add_action(
	'prc_schema_seo_cache_cleared',
	function ( $post_id ) {
		error_log( '[prc-schema-seo] Cache cleared for post ' . $post_id );
	},
	10,
	1
);
