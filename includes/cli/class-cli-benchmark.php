<?php
/**
 * CLI Benchmark utilities for PRC Schema SEO.
 *
 * Provides a WP-CLI command to measure cold vs warm performance for
 * schema generation and meta tag rendering, including memory usage.
 *
 * Usage:
 *   wp prc-seo benchmark --post=123
 *   wp prc-seo benchmark --term=55 --taxonomy=category
 *   wp prc-seo benchmark --post=123 --iterations=3
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

use WP_CLI;
use WPCOM_VIP_CLI_Command;

/**
 * CLI_Benchmark class.
 */
if ( ! class_exists( 'WPCOM_VIP_CLI_Command' ) ) {
	return;
}
class CLI_Benchmark extends WPCOM_VIP_CLI_Command {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Run schema and meta tag performance benchmarks for a post or term.
	 *
	 * ## OPTIONS
	 *
	 * [--post=<id>]
	 * : Post ID to benchmark.
	 *
	 * [--term=<id>]
	 * : Term ID to benchmark (requires --taxonomy).
	 *
	 * [--taxonomy=<taxonomy>]
	 * : Taxonomy for term benchmark.
	 *
	 * [--iterations=<n>]
	 * : Extra warm iterations (default: 1). Useful for averaging.
	 *
	 * ## EXAMPLES
	 *
	 *     wp prc-seo benchmark --post=123
	 *     wp prc-seo benchmark --term=55 --taxonomy=category
	 *     wp prc-seo benchmark --post=123 --iterations=5
	 *
	 * @param array $args  Positional args.
	 * @param array $assoc Assoc args.
	 * @return void
	 */
	public function run( $args, $assoc ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		$post_id    = isset( $assoc['post'] ) ? absint( $assoc['post'] ) : 0;
		$term_id    = isset( $assoc['term'] ) ? absint( $assoc['term'] ) : 0;
		$taxonomy   = isset( $assoc['taxonomy'] ) ? sanitize_key( $assoc['taxonomy'] ) : '';
		$iterations = isset( $assoc['iterations'] ) ? max( 1, absint( $assoc['iterations'] ) ) : 1;

		if ( ! $post_id && ! $term_id ) {
			WP_CLI::error( 'Provide either --post=<id> or --term=<id> --taxonomy=<tax>.' );
			return;
		}

		if ( $term_id && empty( $taxonomy ) ) {
			WP_CLI::error( 'Term benchmark requires --taxonomy=<taxonomy>.' );
			return;
		}

		$loader           = new Loader();
		$schema_generator = new Generator( $loader );
		$seo_metadata     = new Metadata( $loader );
		$meta_tags        = new Meta_Tags( $loader );

		$result = array(
			'version'    => PRC_SCHEMA_SEO_VERSION,
			'timestamp'  => gmdate( 'c' ),
			'post'       => $post_id ?: null,
			'term'       => $term_id ?: null,
			'taxonomy'   => $taxonomy ?: null,
			'iterations' => $iterations,
			'metrics'    => array(),
		);

		if ( $post_id ) {
			$result['metrics']['post'] = $this->benchmark_post( $post_id, $schema_generator, $seo_metadata, $meta_tags, $iterations );
		}

		if ( $term_id ) {
			$result['metrics']['term'] = $this->benchmark_term( $term_id, $taxonomy, $schema_generator, $iterations );
		}

		WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

		$this->vip_inmemory_cleanup();

		WP_CLI::success( 'Benchmark complete.' );
	}

	/**
	 * Benchmark post schema & meta tags cold vs warm + average warm.
	 *
	 * @param int        $post_id      Post ID.
	 * @param Generator  $generator    Generator.
	 * @param Metadata   $seo_metadata SEO metadata service.
	 * @param Meta_Tags  $meta_tags    Meta tags service.
	 * @param int        $iterations   Warm iterations.
	 * @return array Metrics.
	 */
	private function benchmark_post( $post_id, $generator, $seo_metadata, $meta_tags, $iterations ) {
		$this->clear_post_cache( $post_id );

		$schema_cold = $this->time( function() use ( $generator, $post_id ) {
			return $generator->generate_schema( $post_id );
		} );

		$meta_cold = $this->time( function() use ( $seo_metadata, $post_id ) {
			return $this->build_meta_tags_for_benchmark( $seo_metadata, $post_id );
		} );

		$schema_warm_times = array();
		$meta_warm_times   = array();

		for ( $i = 0; $i < $iterations; $i++ ) {
			$schema_warm_times[] = $this->time( function() use ( $generator, $post_id ) {
				return $generator->generate_schema( $post_id );
			} );
			$meta_warm_times[] = $this->time( function() use ( $seo_metadata, $post_id ) {
				return $this->build_meta_tags_for_benchmark( $seo_metadata, $post_id );
			} );
		}

		return array(
			'schema' => $this->summarize_timings( $schema_cold, $schema_warm_times ),
			'meta'   => $this->summarize_timings( $meta_cold, $meta_warm_times ),
		);
	}

	/**
	 * Benchmark term schema cold vs warm.
	 *
	 * @param int       $term_id    Term ID.
	 * @param string    $taxonomy   Taxonomy.
	 * @param Generator $generator  Generator.
	 * @param int       $iterations Warm iterations.
	 * @return array Metrics.
	 */
	private function benchmark_term( $term_id, $taxonomy, $generator, $iterations ) {
		$this->clear_term_cache( $term_id );

		$schema_cold = $this->time( function() use ( $generator, $term_id ) {
			return $generator->generate_term_schema( $term_id );
		} );

		$schema_warm_times = array();

		for ( $i = 0; $i < $iterations; $i++ ) {
			$schema_warm_times[] = $this->time( function() use ( $generator, $term_id ) {
				return $generator->generate_term_schema( $term_id );
			} );
		}

		return array(
			'schema' => $this->summarize_timings( $schema_cold, $schema_warm_times ),
		);
	}

	/**
	 * Time a callable and capture memory delta and output length.
	 *
	 * @param callable $fn Function to execute.
	 * @return array { ms, memory_kb, size_bytes } + raw result.
	 */
	private function time( $fn ) {
		$start     = microtime( true );
		$mem_start = memory_get_usage();
		$result    = $fn();
		$mem_end   = memory_get_usage();
		$end       = microtime( true );
		$ms        = ( $end - $start ) * 1000;
		$size      = is_string( $result )
			? strlen( $result )
			: ( is_array( $result ) ? strlen( wp_json_encode( $result ) ) : 0 );

		return array(
			'ms'         => round( $ms, 2 ),
			'memory_kb'  => round( ( $mem_end - $mem_start ) / 1024, 2 ),
			'size_bytes' => $size,
			'raw_result' => $result,
		);
	}

	/**
	 * Summarize cold and warm timings.
	 *
	 * @param array $cold       Cold timing.
	 * @param array $warm_times Warm timing entries.
	 * @return array Summary.
	 */
	private function summarize_timings( $cold, $warm_times ) {
		$warm_ms = array_map( function( $t ) { return $t['ms']; }, $warm_times );
		$avg     = ! empty( $warm_ms ) ? round( array_sum( $warm_ms ) / count( $warm_ms ), 2 ) : 0;

		return array(
			'cold_ms'        => $cold['ms'],
			'warm_ms'        => $warm_ms,
			'warm_avg_ms'    => $avg,
			'cache_ratio'    => $avg ? round( $cold['ms'] / $avg, 2 ) : null,
			'cold_size'      => $cold['size_bytes'],
			'warm_sizes'     => array_map( function( $t ) { return $t['size_bytes']; }, $warm_times ),
			'memory_cold_kb' => $cold['memory_kb'],
			'memory_warm_kb' => array_map( function( $t ) { return $t['memory_kb']; }, $warm_times ),
		);
	}

	/**
	 * Clear post-related caches.
	 *
	 * @param int $post_id Post ID.
	 */
	private function clear_post_cache( $post_id ) {
		Cache_Keys::delete_multiple( Cache_Keys::post_level_keys( (int) $post_id ), Cache_Keys::GROUP );
	}

	/**
	 * Clear term-related caches.
	 *
	 * @param int $term_id Term ID.
	 */
	private function clear_term_cache( $term_id ) {
		wp_cache_delete( Cache_Keys::term_schema( (int) $term_id ), Generator::CACHE_GROUP );
		Meta_Tags::clear_term_meta_tags_cache( $term_id );
	}

	/**
	 * Build meta tags HTML for benchmarking.
	 *
	 * @param Metadata $seo_metadata SEO metadata service.
	 * @param int      $post_id      Post ID.
	 * @return string Meta tags HTML.
	 */
	private function build_meta_tags_for_benchmark( $seo_metadata, $post_id ) {
		$data         = $seo_metadata->get_seo_data( $post_id );
		$canonical    = get_permalink( $post_id );
		$noindex      = ! empty( $data['noindex'] );
		$robots       = $noindex ? 'noindex,follow' : 'index,follow';
		$og_image_id  = ! empty( $data['og_image'] ) ? $data['og_image'] : 0;
		$og_image_url = $og_image_id ? wp_get_attachment_image_url( $og_image_id, 'full' ) : '';

		$meta = array(
			'description'    => $data['description'],
			'og:type'        => strtolower( $data['schema_type'] ) === 'article' ? 'article' : 'website',
			'og:title'       => $data['og_title'],
			'og:description' => $data['og_description'],
			'og:url'         => $canonical,
			'og:image'       => $og_image_url,
			'twitter:card'   => $og_image_url ? 'summary_large_image' : 'summary',
			'robots'         => $robots,
			'canonical'      => $canonical,
		);

		$lines = array();
		foreach ( $meta as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}
			if ( 'canonical' === $key ) {
				$lines[] = sprintf( '<link rel="canonical" href="%s" />', esc_url( $value ) );
				continue;
			}
			if ( 0 === strpos( $key, 'og:' ) ) {
				$lines[] = sprintf( '<meta property="%s" content="%s" />', esc_attr( $key ), esc_attr( $value ) );
				continue;
			}
			if ( 0 === strpos( $key, 'twitter:' ) ) {
				$lines[] = sprintf( '<meta name="%s" content="%s" />', esc_attr( $key ), esc_attr( $value ) );
				continue;
			}
			$lines[] = sprintf( '<meta name="%s" content="%s" />', esc_attr( $key ), esc_attr( $value ) );
		}

		return PHP_EOL . implode( PHP_EOL, $lines ) . PHP_EOL;
	}
}
