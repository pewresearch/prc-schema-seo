<?php
/**
 * Output class.
 *
 * Handles wp_head output for schema markup.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Output class.
 *
 * Outputs schema markup in the document head.
 */

/**
 * Output
 *
 * Coordinates frontend head output (schema for posts, term archives, post type archives, and home page).
 */
class JSON_Output {
	/**
	 * The loader instance.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Generator instance.
	 *
	 * @var Generator
	 */
	protected $schema_generator;

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader           = $loader;
		$this->schema_generator = new Generator( $loader );

		$this->loader->add_action( 'wp_head', $this, 'output_schema', 1 );
	}

	/**
	 * Output schema markup in wp_head.
	 *
	 * @hook wp_head
	 */
	/**
	 * Main dispatcher for schema output (posts, term archives, post type archives, and home page).
	 *
	 * @hook wp_head
	 * @return void
	 */
	public function output_schema() {
		// Singular post schema.
		if ( is_singular() ) {
			$post_id = get_the_ID();
			if ( $post_id ) {
				$post_type = get_post_type( $post_id );
				if ( post_type_supports( $post_type, 'prc-schema-seo' ) ) {
					Cache_Keys::prime_post_level( (int) $post_id );
					$should_output = apply_filters( 'prc_schema_seo_should_output_schema', true, $post_id );
					if ( $should_output ) {
						$schema = $this->schema_generator->generate_schema( $post_id );
						if ( ! empty( $schema ) ) {
							echo $this->wrap_schema_output( $schema ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped schema string.
							do_action( 'prc_schema_seo_schema_output', $post_id, $schema );
						}
					}
				}
			}
			return;
		}

		// Term archive schema.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && ! empty( $term->term_id ) ) {
				$schema = $this->schema_generator->generate_term_schema( $term->term_id );
				if ( ! empty( $schema ) ) {
					echo $this->wrap_schema_output( $schema ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped schema string.
					do_action( 'prc_schema_seo_schema_output', $term->term_id, $schema );
				}
			}
			return;
		}

		// Home page schema (publications page).
		if ( is_home() ) {
			$schema = $this->schema_generator->generate_publications_page_schema();
			if ( ! empty( $schema ) ) {
				echo $this->wrap_schema_output( $schema ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped schema string.
				do_action( 'prc_schema_seo_schema_output', 'publications', $schema );
			}
			return;
		}

		// Post type archive schema.
		if ( is_post_type_archive() ) {
			$post_type = get_post_type();
			if ( $post_type ) {
				$schema = $this->schema_generator->generate_post_type_archive_schema( $post_type );
				if ( ! empty( $schema ) ) {
					echo $this->wrap_schema_output( $schema ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped schema string.
					do_action( 'prc_schema_seo_schema_output', $post_type, $schema );
				}
			}
		}
	}

	/**
	 * Wrap a schema string with leading/trailing newlines using PHP_EOL.
	 *
	 * @param string $schema Raw schema markup.
	 * @return string Newline wrapped schema for head output.
	 */
	private function wrap_schema_output( $schema ) {
		return PHP_EOL . $schema . PHP_EOL;
	}
}
