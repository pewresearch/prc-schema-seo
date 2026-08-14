<?php
/**
 * DataViews provider for SEO list fields and inline edit.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

use WP_Error;
use WP_Post;
use WP_REST_Request;

/**
 * Enriches prc-wp-admin-dataview lists for post types with prc-schema-seo support.
 */
class DataViews_Provider {
	public const META_KEY = '_prc_seo_data';

	/**
	 * Editable SEO data keys mapped to DataViews field ids.
	 */
	public const FIELD_MAP = array(
		'seoTitle'       => 'title',
		'seoDescription' => 'description',
		'noindex'        => 'noindex',
		'schemaType'     => 'schema_type',
	);

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Loader.
	 */
	public function __construct( $loader ) {
		$loader->add_filter( 'prc_wp_admin_dataview_shape_row', $this, 'shape_row', 10, 3 );
		$loader->add_filter( 'prc_wp_admin_dataview_localize', $this, 'localize', 10, 2 );
		$loader->add_filter( 'prc_wp_admin_dataview_update_field', $this, 'update_field', 10, 5 );
		$loader->add_filter( 'prc_wp_admin_dataview_query_args', $this, 'query_args', 10, 3 );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_provider_script', 20 );
	}

	/**
	 * Whether the post type supports SEO.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function supports( string $post_type ): bool {
		return post_type_supports( $post_type, 'prc-schema-seo' );
	}

	/**
	 * Enrich list rows with SEO fields.
	 *
	 * @param array   $row       Row.
	 * @param WP_Post $post      Post.
	 * @param string  $post_type Post type.
	 * @return array
	 */
	public function shape_row( $row, $post, $post_type ) {
		if ( ! $post instanceof WP_Post || ! $this->supports( (string) $post_type ) ) {
			return $row;
		}

		$data = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$primary = $this->get_primary_term( $post->ID, $data );

		$row['seoTitle']          = isset( $data['title'] ) ? (string) $data['title'] : '';
		$row['seoDescription']    = isset( $data['description'] ) ? (string) $data['description'] : '';
		$row['noindex']           = ! empty( $data['noindex'] );
		$row['schemaType']        = isset( $data['schema_type'] ) ? (string) $data['schema_type'] : '';
		$row['primaryTerm']       = $primary['label'];
		$row['primaryTermId']     = $primary['id'];
		$row['primaryTermTaxonomy'] = $primary['taxonomy'];
		$row['indexNowStatus']    = (string) get_post_meta( $post->ID, '_prc_indexnow_status', true );
		$row['googleIndexStatus'] = (string) get_post_meta( $post->ID, '_prc_gsc_index_status', true );

		return $row;
	}

	/**
	 * Resolve primary term id + label for the first supported taxonomy with a value.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    SEO data.
	 * @return array{id:int,label:string,taxonomy:string}
	 */
	private function get_primary_term( int $post_id, array $data ): array {
		$taxonomies = Primary_Term::get_supported_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			$term_id = Primary_Term::get_id( $post_id, (string) $taxonomy, false );
			if ( ! $term_id ) {
				continue;
			}
			$term = get_term( (int) $term_id, (string) $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return array(
					'id'       => (int) $term_id,
					'label'    => (string) $term->name,
					'taxonomy' => (string) $taxonomy,
				);
			}
		}

		return array(
			'id'       => 0,
			'label'    => '',
			'taxonomy' => isset( $taxonomies[0] ) ? (string) $taxonomies[0] : 'category',
		);
	}

	/**
	 * Localize schema + primary-term options for the list app.
	 *
	 * @param array  $localize Localized data.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function localize( $localize, $post_type ) {
		if ( ! $this->supports( (string) $post_type ) ) {
			return $localize;
		}

		$taxonomy = 'category';
		$supported = Primary_Term::get_supported_taxonomies();
		if ( ! empty( $supported[0] ) ) {
			$taxonomy = (string) $supported[0];
		}

		$localize['seo'] = array(
			'enabled'              => true,
			'schemaTypeOptions'    => $this->get_schema_type_options(),
			'primaryTermTaxonomy'  => $taxonomy,
			'primaryTermOptions'   => $this->get_primary_term_options( $taxonomy ),
			'noindexFilterOptions' => array(
				array(
					'value' => 'indexed',
					'label' => __( 'Indexed', 'prc-schema-seo' ),
				),
				array(
					'value' => 'noindexed',
					'label' => __( 'Not Indexed', 'prc-schema-seo' ),
				),
			),
		);

		return $localize;
	}

	/**
	 * Schema type select options.
	 *
	 * @return array<int, array{value:string,label:string}>
	 */
	private function get_schema_type_options(): array {
		$types = array(
			'',
			'Article',
			'WebPage',
			'Person',
			'Event',
			'Course',
			'Report',
			'Quiz',
			'Dataset',
			'FAQPage',
			'HowTo',
			'NewsArticle',
		);

		$options = array();
		foreach ( $types as $type ) {
			$options[] = array(
				'value' => $type,
				'label' => '' === $type ? __( '— Use Default —', 'prc-schema-seo' ) : $type,
			);
		}
		return $options;
	}

	/**
	 * Primary term select options for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array<int, array{value:string,label:string}>
	 */
	private function get_primary_term_options( string $taxonomy ): array {
		$options = array(
			array(
				'value' => '',
				'label' => __( '— Select —', 'prc-schema-seo' ),
			),
		);

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return $options;
		}

		foreach ( $terms as $term ) {
			$options[] = array(
				'value' => (string) $term->term_id,
				'label' => (string) $term->name,
			);
		}

		return $options;
	}

	/**
	 * Map SEO filters onto WP_Query meta_query args.
	 *
	 * @param array           $query_args Query args.
	 * @param WP_REST_Request $request    Request.
	 * @param string          $post_type  Post type.
	 * @return array
	 */
	public function query_args( $query_args, $request, $post_type ) {
		if ( ! $this->supports( (string) $post_type ) || ! $request instanceof WP_REST_Request ) {
			return $query_args;
		}

		$meta_query = isset( $query_args['meta_query'] ) && is_array( $query_args['meta_query'] )
			? $query_args['meta_query']
			: array();

		$noindex = sanitize_key( (string) $request->get_param( 'noindex' ) );
		if ( 'indexed' === $noindex ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => self::META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META_KEY,
					'value'   => '"noindex";b:0',
					'compare' => 'LIKE',
				),
				array(
					'key'     => self::META_KEY,
					'value'   => '"noindex"',
					'compare' => 'NOT LIKE',
				),
			);
		} elseif ( 'noindexed' === $noindex ) {
			$meta_query[] = array(
				'key'     => self::META_KEY,
				'value'   => '"noindex";b:1',
				'compare' => 'LIKE',
			);
		}

		$schema_type = sanitize_text_field( (string) $request->get_param( 'schemaType' ) );
		if ( '' !== $schema_type ) {
			$meta_query[] = self::schema_type_meta_clause( $schema_type );
		}

		$primary_term = absint( $request->get_param( 'primaryTerm' ) );
		if ( $primary_term > 0 ) {
			$taxonomy = sanitize_key( (string) $request->get_param( 'primaryTermTaxonomy' ) );
			if ( '' === $taxonomy ) {
				$supported = Primary_Term::get_supported_taxonomies();
				$taxonomy  = isset( $supported[0] ) ? (string) $supported[0] : 'category';
			}
			$meta_query[] = self::primary_term_meta_clause( $taxonomy, $primary_term );
		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		return $query_args;
	}

	/**
	 * Serialized-meta clause for schema type (matches ACP filtering).
	 *
	 * @param string $schema_type Schema type.
	 * @return array<string, string>
	 */
	public static function schema_type_meta_clause( string $schema_type ): array {
		return array(
			'key'     => self::META_KEY,
			'value'   => '"schema_type";s:' . strlen( $schema_type ) . ':"' . $schema_type . '"',
			'compare' => 'LIKE',
		);
	}

	/**
	 * Serialized-meta clause for primary term (matches ACP filtering).
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $term_id  Term ID.
	 * @return array<string, string>
	 */
	public static function primary_term_meta_clause( string $taxonomy, int $term_id ): array {
		return array(
			'key'     => self::META_KEY,
			'value'   => '"' . $taxonomy . '";i:' . $term_id,
			'compare' => 'LIKE',
		);
	}

	/**
	 * Handle inline field updates for SEO fields.
	 *
	 * @param true|WP_Error|null $result    Prior result.
	 * @param int                $post_id   Post ID.
	 * @param string             $field     Field id.
	 * @param mixed              $value     Value.
	 * @param string             $post_type Post type.
	 * @return true|WP_Error|null
	 */
	public function update_field( $result, $post_id, $field, $value, $post_type ) {
		if ( null !== $result ) {
			return $result;
		}
		if ( ! $this->supports( (string) $post_type ) ) {
			return null;
		}
		if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return new WP_Error( 'prc_seo_dataview_forbidden', __( 'You cannot edit this post.', 'prc-schema-seo' ), array( 'status' => 403 ) );
		}

		if ( 'primaryTerm' === $field ) {
			return $this->update_primary_term( (int) $post_id, $value );
		}

		if ( ! isset( self::FIELD_MAP[ $field ] ) ) {
			return null;
		}

		$meta_key = self::FIELD_MAP[ $field ];
		$data     = get_post_meta( (int) $post_id, self::META_KEY, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( 'noindex' === $meta_key ) {
			if ( self::is_noindex_value( $value ) ) {
				$data['noindex'] = true;
			} else {
				unset( $data['noindex'] );
			}
		} elseif ( 'description' === $meta_key ) {
			if ( '' === $value || null === $value ) {
				unset( $data[ $meta_key ] );
			} else {
				$data[ $meta_key ] = sanitize_textarea_field( (string) $value );
			}
		} elseif ( '' === $value || null === $value ) {
			unset( $data[ $meta_key ] );
		} else {
			$data[ $meta_key ] = sanitize_text_field( (string) $value );
		}

		return $this->persist_seo_data( (int) $post_id, $data );
	}

	/**
	 * Whether a list field value means noindex.
	 *
	 * Accepts booleans, 1/0, and the list tokens "noindexed" / "indexed".
	 *
	 * @param mixed $value Raw field value.
	 * @return bool
	 */
	public static function is_noindex_value( $value ): bool {
		if ( true === $value || 1 === $value || '1' === $value ) {
			return true;
		}
		if ( is_string( $value ) ) {
			$normalized = strtolower( $value );
			return in_array( $normalized, array( 'noindexed', 'noindex', 'true', 'yes', 'on' ), true );
		}
		return false;
	}

	/**
	 * Update primary term for the default supported taxonomy.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $value   Term ID or empty.
	 * @return true|WP_Error
	 */
	private function update_primary_term( int $post_id, $value ) {
		$supported = Primary_Term::get_supported_taxonomies();
		$taxonomy  = isset( $supported[0] ) ? (string) $supported[0] : 'category';

		$data = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		if ( ! isset( $data['primary_terms'] ) || ! is_array( $data['primary_terms'] ) ) {
			$data['primary_terms'] = array();
		}

		if ( '' === $value || null === $value || 0 === (int) $value ) {
			unset( $data['primary_terms'][ $taxonomy ] );
		} else {
			$data['primary_terms'][ $taxonomy ] = (int) $value;
			$data['primary_terms']              = Primary_Term::prepare_for_post( $data['primary_terms'], $post_id );
		}

		if ( empty( $data['primary_terms'] ) ) {
			unset( $data['primary_terms'] );
		}

		return $this->persist_seo_data( $post_id, $data );
	}

	/**
	 * Persist SEO meta and clear derived caches.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    SEO data.
	 * @return true|WP_Error
	 */
	private function persist_seo_data( int $post_id, array $data ) {
		if ( empty( $data ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			$this->clear_seo_cache( $post_id );
			return true;
		}

		$updated = update_post_meta( $post_id, self::META_KEY, $data );
		if ( false === $updated ) {
			$current = get_post_meta( $post_id, self::META_KEY, true );
			if ( $current === $data ) {
				$this->clear_seo_cache( $post_id );
				return true;
			}
			return new WP_Error( 'prc_seo_dataview_update_failed', __( 'Could not update SEO data.', 'prc-schema-seo' ), array( 'status' => 500 ) );
		}

		$this->clear_seo_cache( $post_id );
		return true;
	}

	/**
	 * Bust Metadata caches when SEO list fields change.
	 *
	 * @param int $post_id Post ID.
	 */
	private function clear_seo_cache( int $post_id ): void {
		if ( class_exists( Metadata::class ) && class_exists( Loader::class ) ) {
			( new Metadata( new Loader() ) )->clear_cache( $post_id );
		}
	}

	/**
	 * Enqueue the JS field provider on DataViews list screens.
	 *
	 * @param string $hook_suffix Admin hook.
	 */
	public function enqueue_provider_script( $hook_suffix ): void {
		if ( ! wp_script_is( 'prc-wp-admin-dataview', 'enqueued' ) ) {
			return;
		}

		$asset_file = PRC_SCHEMA_SEO_DIR . '/build/admin-dataview/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_script(
			'prc-schema-seo-admin-dataview',
			plugins_url( 'build/admin-dataview/index.js', PRC_SCHEMA_SEO_FILE ),
			array_merge( $asset['dependencies'], array( 'prc-wp-admin-dataview' ) ),
			$asset['version'],
			true
		);
	}
}
