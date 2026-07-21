<?php
/**
 * Generator class.
 *
 * Generates Schema.org JSON-LD markup using Spatie library.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\Person;
use Spatie\SchemaOrg\Article;
use Spatie\SchemaOrg\NewsArticle;
use Spatie\SchemaOrg\BlogPosting;
use Spatie\SchemaOrg\Report;
use Spatie\SchemaOrg\WebPage;
use Spatie\SchemaOrg\PostalAddress;
use Spatie\SchemaOrg\DefinedTerm;

/**
 * Generator
 *
 * Responsible for building JSON-LD schema for posts, term archives, post type archives, and home page
 * using the Spatie schema-org library with object cache for performance.
 */
class Generator {
	/**
	 * Cache group for schema output (unified post-level group).
	 */
	const CACHE_GROUP = Cache_Keys::GROUP;

	/**
	 * Cache TTL (1 hour).
	 */
	const CACHE_TTL = Cache_Keys::TTL;

	/**
	 * Check if JSON-LD output should be minified.
	 *
	 * Minification removes pretty-printing (newlines and indentation) from the JSON output.
	 * When not set via constant or filter, minifies on all environments except 'local'
	 * (see wp_get_environment_type()). Override via PRC_SCHEMA_SEO_MINIFY_JSON or the
	 * 'prc_schema_seo_minify_json' filter.
	 *
	 * @return bool True if output should be minified.
	 */
	public static function should_minify() {
		$minify = null;
		if ( defined( 'PRC_SCHEMA_SEO_MINIFY_JSON' ) ) {
			$minify = PRC_SCHEMA_SEO_MINIFY_JSON;
		}
		if ( null === $minify ) {
			$minify = wp_get_environment_type() !== 'local';
		}

		/**
		 * Filter whether to minify JSON-LD schema output.
		 *
		 * @param bool $minify Whether to minify the output.
		 */
		return apply_filters( 'prc_schema_seo_minify_json', $minify );
	}

	/**
	 * The loader instance.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * SEO_Metadata instance.
	 *
	 * @var Metadata
	 */
	protected $seo_metadata;

	/**
	 * Cached organization config from prc_schema_seo_organization_config filter.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $org_config_cache = null;

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader       = $loader;
		$this->seo_metadata = new Metadata( $loader );
	}

	/**
	 * Organization identity for schema.org (publisher, WebSite name alignment, etc.).
	 *
	 * @return array<string, mixed>
	 */
	private function get_org_config(): array {
		if ( null === $this->org_config_cache ) {
			$this->org_config_cache = apply_filters(
				'prc_schema_seo_organization_config',
				array(
					'name'                   => 'Pew Research Center',
					'url'                    => 'https://www.pewresearch.org', // pragma: allowlist secret — public site URL default, not an API key.
					'alternate_names'        => array( 'Pew Research', 'PRC' ),
					'description'            => 'Pew Research Center is a nonpartisan, nonadvocacy fact tank that informs the public about the issues, attitudes and trends shaping the world.',
					'slogan'                 => 'Numbers, Facts and Trends Shaping Your World',
					'founding_date'          => '2004-07-01',
					'nonprofit_status'       => 'https://schema.org/Nonprofit501c3',
					'publishing_principles'  => 'https://www.pewresearch.org/about/our-mission/', // pragma: allowlist secret — public URL default.
				)
			);
		}

		return $this->org_config_cache;
	}

	public function get_same_as_array() {
		/**
		 * Filter sameAs social profile URLs for Organization / WebSite schema.
		 *
		 * @param array<int, string> $urls Social profile URLs.
		 */
		return apply_filters(
			'prc_schema_seo_same_as',
			array(
				'https://x.com/pewresearch',
				'https://www.facebook.com/pewresearch',
				'https://www.threads.com/@pewresearch',
				'https://www.instagram.com/pewresearch',
				'https://www.youtube.com/user/PewResearchCenter',
				'https://www.linkedin.com/company/pew-research-center',
			)
		);
	}

	/**
	 * Get organization address schema.
	 *
	 * Returns a PostalAddress schema object for Pew Research Center and The Pew Charitable Trusts.
	 *
	 * @return PostalAddress PostalAddress schema instance.
	 */
	private function get_organization_address() {
		/**
		 * Filter postal address fields for organization schema.
		 *
		 * @param array<string, string> $address Keys: streetAddress, addressLocality, addressRegion, postalCode, addressCountry.
		 */
		$address = apply_filters(
			'prc_schema_seo_organization_address',
			array(
				'streetAddress'   => '901 E St NW',
				'addressLocality' => 'Washington',
				'addressRegion'   => 'DC',
				'postalCode'      => '20004',
				'addressCountry'  => 'US',
			)
		);

		return Schema::postalAddress()
			->streetAddress( $address['streetAddress'] )
			->addressLocality( $address['addressLocality'] )
			->addressRegion( $address['addressRegion'] )
			->postalCode( $address['postalCode'] )
			->addressCountry( $address['addressCountry'] );
	}

	/**
	 * Generate schema for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string JSON-LD schema markup.
	 */
	/**
	 * Generate and cache schema for a single post.
	 *
	 * @param int $post_id Post ID.
	 * @return string JSON-LD <script> tag or empty string.
	 */
	public function generate_schema( $post_id ) {
		// Term archive handling delegated elsewhere; this method remains post-specific.
		$cache_key = Cache_Keys::schema( (int) $post_id );

		if ( Cache_Keys::caching_enabled() ) {
			$cached = Cache_Keys::get( $cache_key, self::CACHE_GROUP );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Prefetch common taxonomies in one call to reduce query overhead on cold cache.
		$this->prefetch_post_terms( $post_id );

		$post     = get_post( $post_id );
		$seo_data = $this->seo_metadata->get_seo_data( $post_id );
		$seo_data = $this->seo_metadata->resolve_tokens( $seo_data, $post_id );

		// Build schema array, with the default website schema first.
		$schemas = array(
			$this->generate_website_schema(),
		);

		// Add organization schema (always present) - pass post_id for dynamic contact resolution.
		$schemas[] = $this->generate_organization_schema( $post_id );

		// Add WebPage schema for this specific page (referenced by Article via mainEntityOfPage).
		$schemas[] = $this->generate_webpage_graph_item( $post, $seo_data );

		// Add post-specific schema based on type
		$schemas[] = $this->generate_post_schema( $post, $seo_data );

		// Add breadcrumb schema if available
		$breadcrumb_schema = $this->generate_breadcrumb_schema( $post->ID, $seo_data );
		if ( $breadcrumb_schema ) {
			$schemas[] = $breadcrumb_schema;
		}

		// Allow filtering of schema data
		$schemas = apply_filters( 'prc_schema_seo_schema_data', $schemas, $post_id, $seo_data );

		// Convert to JSON-LD
		$json_ld = $this->schemas_to_json_ld( $schemas );

		if ( Cache_Keys::caching_enabled() ) {
			Cache_Keys::set( $cache_key, $json_ld, self::CACHE_GROUP, self::CACHE_TTL );
		}

		return $json_ld;
	}

	public function generate_website_schema() {
		$search_url_template = home_url( '/search/{search_term_string}' );

		$search_action = Schema::searchAction()
			->target(
				Schema::entryPoint()
					->urlTemplate( $search_url_template )
			)
			->setProperty( 'query-input', 'required name=search_term_string' );

		$website = Schema::webSite()
			->setProperty( '@id', home_url( '/#website' ) )
			->name( get_bloginfo( 'name' ) )
			->description( get_bloginfo( 'description' ) )
			->url( home_url() )
			->publisher( array( '@id' => home_url( '/#organization' ) ) )
			->sameAs( $this->get_same_as_array() )
			->potentialAction( $search_action );

		return $website;
	}

	/**
	 * Generate schema for a term archive (CollectionPage).
	 *
	 * @param int $term_id Term ID.
	 * @return string JSON-LD schema markup.
	 */
	/**
	 * Generate and cache CollectionPage schema for a term archive.
	 *
	 * @param int $term_id Term ID.
	 * @return string JSON-LD <script> tag or empty string.
	 */
	public function generate_term_schema( $term_id ) {
		$cache_key = Cache_Keys::term_schema( (int) $term_id );

		if ( Cache_Keys::caching_enabled() ) {
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( false !== $cached ) {
				return $cached;
			}
		}
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		// Handle bylines taxonomy - generate Person schema instead of CollectionPage.
		if ( 'bylines' === $term->taxonomy && class_exists( '\PRC\Platform\Staff_Bylines\Staff' ) ) {
			$staff = new \PRC\Platform\Staff_Bylines\Staff( false, $term_id );

			// If Staff has an ID (resolved to staff post), use its post for person schema.
			if ( ! empty( $staff->ID ) && is_int( $staff->ID ) ) {
				$post     = get_post( $staff->ID );
				$seo_data = $this->seo_metadata->get_seo_data( $staff->ID );
				$seo_data = $this->seo_metadata->resolve_tokens( $seo_data, $staff->ID );
				$schemas  = array(
					$this->generate_website_schema(),
					$this->generate_organization_schema(),
					$this->generate_person_schema( $post, $seo_data ),
				);
			} else {
				// Guest author - build minimal person schema from Staff object.
				$schemas = array(
					$this->generate_website_schema(),
					$this->generate_organization_schema(),
					$this->generate_guest_person_schema( $staff, $term ),
				);
			}

			$json_ld = $this->schemas_to_json_ld( $schemas );
			if ( Cache_Keys::caching_enabled() ) {
				wp_cache_set( $cache_key, $json_ld, self::CACHE_GROUP, self::CACHE_TTL );
			}
			return $json_ld;
		}

		// Default CollectionPage for other taxonomies.
		$name        = $term->name;
		$description = wp_strip_all_tags( term_description( $term_id ) );
		if ( empty( $description ) ) {
			$description = get_bloginfo( 'description' );
		}
		$url = get_term_link( $term );

		$collection = Schema::collectionPage()
			->name( $name )
			->url( $url )
			->description( $description )
			->isPartOf( $this->generate_organization_schema() );

		$collection = apply_filters( 'prc_schema_seo_term_schema', $collection, $term );

		$breadcrumb_schema = $this->generate_term_breadcrumb_schema( $term );

		// Keep parity with single post output: always include Website + Organization in the graph.
		$json_ld = $this->schemas_to_json_ld(
			array_filter(
				array(
					$this->generate_website_schema(),
					$this->generate_organization_schema(),
					$collection,
					$breadcrumb_schema,
				)
			)
		);
		if ( Cache_Keys::caching_enabled() ) {
			wp_cache_set( $cache_key, $json_ld, self::CACHE_GROUP, self::CACHE_TTL );
		}
		return $json_ld;
	}

	/**
	 * Generate and cache CollectionPage schema for a post type archive.
	 *
	 * @param string $post_type Post type slug.
	 * @return string JSON-LD <script> tag or empty string.
	 */
	public function generate_post_type_archive_schema( $post_type ) {
		$cache_key = Cache_Keys::post_type_archive_schema( (string) $post_type );

		/**
		 * Filter whether to cache the post type archive schema for a given post type.
		 *
		 * Evaluated before both the cache read and cache write so that dynamic post
		 * types (e.g. RLS, whose schema varies per URL) are never served a stale
		 * cached entry from a previous request.
		 *
		 * Return false to skip caching entirely for the given post type.
		 *
		 * @param bool   $should_cache Whether to cache the schema output. Default true.
		 * @param string $post_type    Post type slug.
		 */
		$should_cache = apply_filters( 'prc_schema_seo_cache_post_type_archive_schema', true, $post_type );

		if ( $should_cache && Cache_Keys::caching_enabled() ) {
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object ) {
			return '';
		}

		$name        = $post_type_object->labels->name;
		$description = ! empty( $post_type_object->description ) ? wp_strip_all_tags( $post_type_object->description ) : get_bloginfo( 'description' );
		$url         = get_post_type_archive_link( $post_type );

		if ( ! $url ) {
			return '';
		}

		$collection = Schema::collectionPage()
			->name( $name )
			->url( $url )
			->isPartOf( $this->generate_organization_schema() );

		if ( ! empty( $description ) ) {
			$collection->description( $description );
		}

		$collection = apply_filters( 'prc_schema_seo_post_type_archive_schema', $collection, $post_type_object );

		// Keep parity with single post output: always include Website + Organization in the graph.
		$schemas = array(
			$this->generate_website_schema(),
			$this->generate_organization_schema(),
			$collection,
		);

		/**
		 * Filter the full post type archive schema graph.
		 *
		 * Allows plugins to modify, replace, or suppress the entire schema graph
		 * for post type archives. Returning an empty array suppresses schema output.
		 *
		 * @param array         $schemas          Array of Spatie schema objects.
		 * @param string        $post_type        Post type slug.
		 * @param \WP_Post_Type $post_type_object Post type object.
		 */
		$schemas = apply_filters(
			'prc_schema_seo_post_type_archive_schema_data',
			$schemas,
			$post_type,
			$post_type_object
		);

		$json_ld = $this->schemas_to_json_ld( $schemas );

		// $should_cache was already resolved above (before the cache read) to keep
		// both the read and write gates in sync.
		if ( $should_cache && Cache_Keys::caching_enabled() ) {
			wp_cache_set( $cache_key, $json_ld, self::CACHE_GROUP, self::CACHE_TTL );
		}

		return $json_ld;
	}

	/**
	 * Generate and cache CollectionPage schema for the home page (publications page).
	 *
	 * @return string JSON-LD <script> tag or empty string.
	 */
	public function generate_publications_page_schema() {
		$cache_key = Cache_Keys::publications_page_schema();

		if ( Cache_Keys::caching_enabled() ) {
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$name = 'Publications';
		$url  = home_url( '/publications' );

		$collection = Schema::collectionPage()
			->name( $name )
			->url( $url )
			->isPartOf( $this->generate_organization_schema() );

		$collection = apply_filters( 'prc_schema_seo_publications_page_schema', $collection );

		// Keep parity with single post output: always include Website + Organization in the graph.
		$json_ld = $this->schemas_to_json_ld(
			array(
				$this->generate_website_schema(),
				$this->generate_organization_schema(),
				$collection,
			)
		);

		if ( Cache_Keys::caching_enabled() ) {
			wp_cache_set( $cache_key, $json_ld, self::CACHE_GROUP, self::CACHE_TTL );
		}

		return $json_ld;
	}

	/**
	 * Generate organization schema.
	 *
	 * @param int|null $post_id Optional post ID for context-aware contact resolution.
	 * @return Organization Organization schema object.
	 */
	/**
	 * Generate Organization schema for publisher (always included).
	 *
	 * @param int|null $post_id Optional post ID for dynamic contact point resolution.
	 * @return Organization Organization schema instance (filterable).
	 */
	private function generate_organization_schema( $post_id = null ) {
		// @TODO: Contact someone at PCT for their preferred fully scoped schema JSON definition
		// to ensure accurate representation of parent organization properties (logo, address, sameAs, etc.).
		/**
		 * Filter parent organization for the main publisher Organization schema.
		 * Return false to omit parentOrganization and funder.
		 *
		 * @param array{name:string,url:string}|false $config Parent org name and URL, or false to disable.
		 */
		$parent_org_config = apply_filters(
			'prc_schema_seo_parent_organization',
			array(
				'name' => 'The Pew Charitable Trusts',
				'url'  => 'https://www.pewtrusts.org',
			)
		);

		$parent_org = null;
		if ( false !== $parent_org_config && is_array( $parent_org_config ) ) {
			$parent_org = Schema::organization()
				->name( $parent_org_config['name'] )
				->url( $parent_org_config['url'] )
				->address( $this->get_organization_address() );
		}

		$org_config = $this->get_org_config();

		// Get areas of expertise from taxonomy for knowsAbout property.
		$knows_about = array();
		if ( taxonomy_exists( 'areas-of-expertise' ) ) {
			$expertise_terms = get_terms(
				array(
					'taxonomy'   => 'areas-of-expertise',
					'hide_empty' => false,
					'fields'     => 'names',
				)
			);
			// Strip out "Communications Strategy" and "Digital Strategy" from the list of expertise areas.
			$expertise_terms = array_values(
				array_filter(
					$expertise_terms,
					function ( $term ) {
						return ! in_array( $term, array( 'Communications Strategy', 'Digital Strategy' ) );
					}
				)
			);
			if ( ! is_wp_error( $expertise_terms ) && ! empty( $expertise_terms ) ) {
				$knows_about = $expertise_terms;
			}
		}

		// Resolve contact point dynamically based on post context.
		$contact = null;
		if ( $post_id ) {
			$contact = Contact_Resolver::get_contact_for_post( $post_id );
		}
		if ( ! $contact ) {
			$contact = Contact_Resolver::get_default_contact();
		}

		// Build contact point schema.
		$contact_point = Schema::contactPoint()
			->contactType( 'Media Inquiries' );

		if ( ! empty( $contact['name'] ) ) {
			$contact_point->name( $contact['name'] );
		}
		if ( ! empty( $contact['phone'] ) ) {
			$contact_point->telephone( $contact['phone'] );
		}
		if ( ! empty( $contact['email'] ) ) {
			$contact_point->email( $contact['email'] );
		}
		if ( ! empty( $contact['url'] ) ) {
			$contact_point->url( $contact['url'] );
		}

		$org = Schema::organization()
			->setProperty( '@id', home_url( '/#organization' ) )
			->name( $org_config['name'] )
			->url( $org_config['url'] )
			->alternateName( $org_config['alternate_names'] )
			->slogan( $org_config['slogan'] )
			->foundingDate( $org_config['founding_date'] )
			->setProperty( 'nonprofitStatus', $org_config['nonprofit_status'] )
			->publishingPrinciples( $org_config['publishing_principles'] )
			->correctionsPolicy( '' ) // @TODO: Add corrections policy URL when available.
			->contactPoint( $contact_point )
			->logo(
				Schema::imageObject()
				->url( content_url( 'images/logo.png' ) )
				->width( 600 )
				->height( 60 )
			)
			->address( $this->get_organization_address() )
			->sameAs(
				$this->get_same_as_array()
			);

		if ( ! empty( $org_config['description'] ) ) {
			$org->description( $org_config['description'] );
		}

		if ( null !== $parent_org ) {
			$org->parentOrganization( $parent_org );
			$org->funder( $parent_org );
		}

		// Add knowsAbout if we have expertise areas.
		if ( ! empty( $knows_about ) ) {
			$org->knowsAbout( $knows_about );
		}

		return apply_filters( 'prc_schema_seo_organization_schema', $org, $post_id );
	}

	/**
	 * Generate schema for a post.
	 *
	 * @param \WP_Post $post     Post object.
	 * @param array    $seo_data SEO metadata.
	 * @return mixed Schema object.
	 */
	/**
	 * Generate appropriate schema object based on post schema_type.
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $seo_data Resolved SEO metadata.
	 * @return mixed Spatie schema object.
	 */
	private function generate_post_schema( $post, $seo_data ) {
		$schema_type = $seo_data['schema_type'] ?? 'Article';

		switch ( $schema_type ) {
			case 'Person':
				return $this->generate_person_schema( $post, $seo_data );
			case 'NewsArticle':
			case 'BlogPosting':
			case 'Report':
			case 'Article':
				return $this->generate_article_schema( $post, $seo_data, $schema_type );
			case 'WebPage':
			default:
				return $this->generate_webpage_schema( $post, $seo_data );
		}
	}

	/**
	 * Generate Person schema for staff pages.
	 *
	 * @param \WP_Post $post     Post object.
	 * @param array    $seo_data SEO metadata.
	 * @return Person Person schema object.
	 */
	/**
	 * Build Person schema for staff/person pages.
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $seo_data SEO metadata.
	 * @return Person
	 */
	private function generate_person_schema( $post, $seo_data ) {
		$person = Schema::person()
			->name( $seo_data['title'] )
			->url( get_permalink( $post ) );

		// Instantiate Staff class if available to access staff-specific data.
		$staff = null;
		if ( class_exists( '\PRC\Platform\Staff_Bylines\Staff' ) ) {
			$staff = new \PRC\Platform\Staff_Bylines\Staff( $post->ID );
		}

		// Add jobTitle from staff data.
		if ( $staff && ! empty( $staff->job_title ) ) {
			$person->jobTitle( $staff->job_title );
		}

		// Add worksFor if currently employed.
		if ( $staff && $staff->is_currently_employed ) {
			$person->worksFor( $this->generate_organization_schema() );
		}

		// Add knowsAbout from expertise areas.
		if ( $staff && ! empty( $staff->expertise ) ) {
			$knows_about = array_column( $staff->expertise, 'label' );
			$person->knowsAbout( $knows_about );
		}

		// Add image - prefer staff photo over og_image when available.
		if ( $staff && ! empty( $staff->photo['full'][0] ) ) {
			$person->image( $staff->photo['full'][0] );
		} elseif ( ! empty( $seo_data['og_image'] ) ) {
			$image_url = wp_get_attachment_image_url( $seo_data['og_image'], 'full' );
			if ( $image_url ) {
				$person->image( $image_url );
			}
		}

		// Add sameAs from social profiles when available.
		if ( $staff && ! empty( $staff->social_profiles ) ) {
			$person->sameAs( array_column( $staff->social_profiles, 'url' ) );
		}

		// Add description.
		if ( ! empty( $seo_data['description'] ) ) {
			$person->description( $seo_data['description'] );
		}

		// Add custom schema properties.
		if ( ! empty( $seo_data['custom_schema'] ) ) {
			foreach ( $seo_data['custom_schema'] as $key => $value ) {
				$person->setProperty( $key, $value );
			}
		}

		// Allow filtering of person schema before return.
		return apply_filters( 'prc_schema_seo_person_schema', $person, $post->ID, $seo_data );
	}

	/**
	 * Generate minimal Person schema for guest authors without staff posts.
	 *
	 * @param \PRC\Platform\Staff_Bylines\Staff $staff Staff object (guest).
	 * @param \WP_Term                          $term  Byline term.
	 * @return Person Person schema object.
	 */
	private function generate_guest_person_schema( $staff, $term ) {
		$person = Schema::person()
			->name( $staff->name )
			->url( get_term_link( $term ) );

		if ( ! empty( $staff->job_title ) ) {
			$person->jobTitle( $staff->job_title );
		}

		// Allow filtering of person schema before return.
		return apply_filters( 'prc_schema_seo_person_schema', $person, $term->term_id, array() );
	}

	/**
	 * Get primary term name for a taxonomy if designated.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $seo_data SEO metadata containing primary_terms map (unused, kept for BC).
	 * @return string|null Primary term name or null if none designated.
	 */
	private function get_primary_term_name( $post_id, $taxonomy, $seo_data ) {
		$name = Primary_Term::get_name( $post_id, $taxonomy, false );
		return ! empty( $name ) ? $name : null;
	}

	/**
	 * Generate DefinedTerm schema for a taxonomy term.
	 *
	 * @param \WP_Term $term      Term object.
	 * @param array    $term_meta Optional term meta array from _prc_seo_term_data.
	 * @return DefinedTerm DefinedTerm schema object.
	 */
	private function generate_defined_term_schema( $term, $term_meta = array() ) {
		$defined_term = Schema::definedTerm()
			->name( $term->name )
			->url( get_term_link( $term ) );

		// Add termCode if available in term meta.
		if ( ! empty( $term_meta['term_code'] ) ) {
			$defined_term->termCode( $term_meta['term_code'] );
		}

		// Add description from term meta or term description.
		if ( ! empty( $term_meta['description'] ) ) {
			$defined_term->description( $term_meta['description'] );
		} elseif ( ! empty( $term->description ) ) {
			$defined_term->description( wp_strip_all_tags( $term->description ) );
		}

		// Add inDefinedTermSet - use custom URL if provided, otherwise generate from taxonomy.
		if ( ! empty( $term_meta['defined_term_set'] ) ) {
			$defined_term->inDefinedTermSet( $term_meta['defined_term_set'] );
		} else {
			$defined_term->inDefinedTermSet( home_url( '/taxonomy/' . $term->taxonomy ) );
		}

		/**
		 * Filter the DefinedTerm schema before returning.
		 *
		 * @param DefinedTerm $defined_term The DefinedTerm schema object.
		 * @param \WP_Term    $term         The term object.
		 * @param array       $term_meta    The term meta array.
		 */
		return apply_filters( 'prc_schema_seo_defined_term_schema', $defined_term, $term, $term_meta );
	}

	/**
	 * Get terms for the about property based on expertise areas and marked terms.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $seo_data SEO metadata.
	 * @return array Array of DefinedTerm schema objects for the about property.
	 */
	private function get_about_terms( $post_id, $seo_data ) {
		$about_terms = array();

		// Get primary category and its linked expertise area.
		$category_id = Primary_Term::get_id( $post_id, 'category' );
		if ( $category_id ) {
			$expertise_id = \PRC\Platform\Schema_SEO\Utils\get_category_expertise_id( $category_id );

			if ( $expertise_id ) {
				$expertise_term = get_term( $expertise_id, 'areas-of-expertise' );
				if ( $expertise_term && ! is_wp_error( $expertise_term ) ) {
					$expertise_meta = get_term_meta( $expertise_id, Taxonomy_UI::TERM_META_KEY, true );
					$expertise_meta = is_array( $expertise_meta ) ? $expertise_meta : array();
					$about_terms[]  = $this->generate_defined_term_schema( $expertise_term, $expertise_meta );
				}
			}
		}

		// Also include any terms explicitly marked as "is_about_term".
		$taxonomies = apply_filters( 'prc_schema_seo_about_taxonomies', array( 'category', 'post_tag', 'areas-of-expertise' ) );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$term_meta = get_term_meta( $term->term_id, Taxonomy_UI::TERM_META_KEY, true );
				$term_meta = is_array( $term_meta ) ? $term_meta : array();

				// Check if term is marked as an about term.
				if ( ! empty( $term_meta['is_about_term'] ) ) {
					// Avoid duplicates.
					$existing_urls = array_map(
						function ( $dt ) {
							return $dt->toArray()['url'] ?? '';
						},
						$about_terms
					);
					$term_url      = get_term_link( $term );
					if ( ! in_array( $term_url, $existing_urls, true ) ) {
						$about_terms[] = $this->generate_defined_term_schema( $term, $term_meta );
					}
				}
			}
		}

		/**
		 * Filter the about terms array before returning.
		 *
		 * @param array $about_terms Array of DefinedTerm schema objects.
		 * @param int   $post_id     Post ID.
		 * @param array $seo_data    SEO metadata.
		 */
		return apply_filters( 'prc_schema_seo_about_terms', $about_terms, $post_id, $seo_data );
	}

	/**
	 * Generate Article schema.
	 *
	 * @param \WP_Post $post        Post object.
	 * @param array    $seo_data    SEO metadata.
	 * @param string   $schema_type Schema.org type.
	 * @return Article Article schema object.
	 */
	/**
	 * Build Article-like schema (Article, NewsArticle, BlogPosting, Report).
	 *
	 * @param \WP_Post $post        Post object.
	 * @param array    $seo_data    SEO metadata.
	 * @param string   $schema_type Schema type string.
	 * @return Article
	 */
	private function generate_article_schema( $post, $seo_data, $schema_type ) {
		// Create appropriate article type
		switch ( $schema_type ) {
			case 'NewsArticle':
				$article = Schema::newsArticle();
				break;
			case 'BlogPosting':
				$article = Schema::blogPosting();
				break;
			case 'Report':
				$article = Schema::report();
				break;
			case 'Article':
			default:
				$article = Schema::article();
				break;
		}

		$org_config = $this->get_org_config();

		// Basic properties
		$article
			->headline( $seo_data['title'] )
			->url( get_permalink( $post ) )
			->datePublished( get_the_date( 'c', $post ) )
			->dateModified( get_the_modified_date( 'c', $post ) )
			->publisher(
			Schema::organization()
				->setProperty( '@id', home_url( '/#organization' ) )
				->name( $org_config['name'] )
				->url( $org_config['url'] )
				->logo(
					Schema::imageObject()
						->url( content_url( 'images/logo.png' ) )
						->width( 600 )
						->height( 60 )
				)
		);

		// Description
		if ( ! empty( $seo_data['description'] ) ) {
			$article->description( $seo_data['description'] );
		}

		// Image
		if ( ! empty( $seo_data['og_image'] ) ) {
			$image_url = wp_get_attachment_image_url( $seo_data['og_image'], 'full' );
			if ( $image_url ) {
				$image_data = wp_get_attachment_metadata( $seo_data['og_image'] );
				$article->image(
					Schema::imageObject()
					->url( $image_url )
					->width( $image_data['width'] ?? null )
					->height( $image_data['height'] ?? null )
				);
			}
		}

		// Add about property using expertise/category terms as DefinedTerms.
		$about_terms = $this->get_about_terms( $post->ID, $seo_data );
		if ( ! empty( $about_terms ) ) {
			$article->about( $about_terms );
		}

		// Author - use byline system if available
		$authors = array();
		if ( class_exists( '\PRC\Platform\Staff_Bylines\Bylines' ) ) {
			$bylines_instance = new \PRC\Platform\Staff_Bylines\Bylines( $post->ID );
			$bylines          = $bylines_instance->get();
			if ( ! empty( $bylines ) && ! is_wp_error( $bylines ) ) {
				foreach ( $bylines as $byline ) {
					$person = Schema::person()->name( $byline['name'] );

					if ( ! empty( $byline['link'] ) ) {
						$person->url( $byline['link'] );
					}

					if ( ! empty( $byline['job_title'] ) ) {
						$person->jobTitle( $byline['job_title'] );
					}

					$person->worksFor( $org_config['name'] );

					$authors[] = $person;
				}
			}
		}

		// Fallback to post author if no bylines found
		if ( empty( $authors ) ) {
			$author_id = $post->post_author;
			if ( $author_id ) {
				$author_name = get_the_author_meta( 'display_name', $author_id );
				$authors[]   = Schema::person()->name( $author_name );
			}
		}

		// Set author(s) on article
		if ( ! empty( $authors ) ) {
			if ( count( $authors ) === 1 ) {
				$article->author( $authors[0] );
			} else {
				$article->author( $authors );
			}
		}

		$article->mainEntityOfPage( get_permalink( $post ) );

		// Add primary category as articleSection if available
		$primary_category = $this->get_primary_term_name( $post->ID, 'category', $seo_data );
		if ( $primary_category ) {
			$article->articleSection( $primary_category );
		}

		// Add custom schema properties
		if ( ! empty( $seo_data['custom_schema'] ) ) {
			foreach ( $seo_data['custom_schema'] as $key => $value ) {
				$article->setProperty( $key, $value );
			}
		}

		// Allow filtering of article schema before return.
		return apply_filters( 'prc_schema_seo_article_schema', $article, $post->ID, $seo_data, $schema_type );
	}

	/**
	 * Generate WebPage schema for the @graph (used as mainEntityOfPage target).
	 *
	 * This creates a WebPage with an @id that can be referenced by Article schemas.
	 *
	 * @param \WP_Post $post     Post object.
	 * @param array    $seo_data SEO metadata.
	 * @return WebPage WebPage schema object with @id.
	 */
	private function generate_webpage_graph_item( $post, $seo_data ) {
		$permalink  = get_permalink( $post );
		$webpage_id = $permalink . '#webpage';

		$webpage = Schema::webPage()
			->setProperty( '@id', $webpage_id )
			->url( $permalink )
			->name( $seo_data['title'] )
			->datePublished( get_the_date( 'c', $post ) )
			->dateModified( get_the_modified_date( 'c', $post ) )
			->isPartOf( array( '@id' => home_url( '/#website' ) ) );

		// Description
		if ( ! empty( $seo_data['description'] ) ) {
			$webpage->description( $seo_data['description'] );
		}

		// Image
		if ( ! empty( $seo_data['og_image'] ) ) {
			$image_url = wp_get_attachment_image_url( $seo_data['og_image'], 'full' );
			if ( $image_url ) {
				$webpage->primaryImageOfPage(
					Schema::imageObject()->url( $image_url )
				);
			}
		}

		/**
		 * Filter the WebPage graph item schema before returning.
		 *
		 * @param WebPage  $webpage  The WebPage schema object.
		 * @param int      $post_id  The post ID.
		 * @param array    $seo_data The SEO metadata.
		 */
		return apply_filters( 'prc_schema_seo_webpage_graph_item', $webpage, $post->ID, $seo_data );
	}

	/**
	 * Generate WebPage schema.
	 *
	 * @param \WP_Post $post     Post object.
	 * @param array    $seo_data SEO metadata.
	 * @return WebPage WebPage schema object.
	 */
	/**
	 * Build WebPage schema for generic pages (when schema_type is WebPage).
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $seo_data SEO metadata.
	 * @return WebPage
	 */
	private function generate_webpage_schema( $post, $seo_data ) {
		$permalink  = get_permalink( $post );
		$webpage_id = $permalink . '#webpage';

		$webpage = Schema::webPage()
			->setProperty( '@id', $webpage_id )
			->name( $seo_data['title'] )
			->url( $permalink )
			->datePublished( get_the_date( 'c', $post ) )
			->dateModified( get_the_modified_date( 'c', $post ) )
			->isPartOf( array( '@id' => home_url( '/#website' ) ) );

		// Description
		if ( ! empty( $seo_data['description'] ) ) {
			$webpage->description( $seo_data['description'] );
		}

		// Image
		if ( ! empty( $seo_data['og_image'] ) ) {
			$image_url = wp_get_attachment_image_url( $seo_data['og_image'], 'full' );
			if ( $image_url ) {
				$webpage->primaryImageOfPage(
					Schema::imageObject()->url( $image_url )
				);
			}
		}

		// Add custom schema properties
		if ( ! empty( $seo_data['custom_schema'] ) ) {
			foreach ( $seo_data['custom_schema'] as $key => $value ) {
				$webpage->setProperty( $key, $value );
			}
		}

		// Allow filtering of webpage schema before return.
		return apply_filters( 'prc_schema_seo_webpage_schema', $webpage, $post->ID, $seo_data );
	}

	/**
	 * Generate BreadcrumbList schema for a post.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $seo_data SEO metadata.
	 * @return \Spatie\SchemaOrg\BreadcrumbList|null BreadcrumbList schema object or null if no breadcrumbs.
	 */
	private function generate_breadcrumb_schema( $post_id, $seo_data ) {
		// Build breadcrumb list items using primary terms when available
		// Include full hierarchical path from top-level ancestor down to primary category
		$breadcrumbs = array();
		$position    = 1;

		// Always start with Home
		$breadcrumbs[] = Schema::listItem()
			->position( $position++ )
			->name( 'Home' )
			->item( home_url( '/' ) );

		// Add "research topics" breadcrumb at /topics
		$breadcrumbs[] = Schema::listItem()
			->position( $position++ )
			->name( 'Research Topics' )
			->item( home_url( '/topics' ) );

		// Get primary category term ID to traverse hierarchy
		$primary_category_id = Primary_Term::get_id( $post_id, 'category', true );
		if ( $primary_category_id ) {
			$primary_term = get_term( $primary_category_id, 'category' );
			if ( $primary_term && ! is_wp_error( $primary_term ) ) {
				// Get all ancestor term IDs (returns immediate parent first, so reverse for top-down order)
				$ancestor_ids = get_ancestors( $primary_category_id, 'category', 'taxonomy' );
				$ancestor_ids = array_reverse( $ancestor_ids );

				// Add each ancestor as a breadcrumb from top-level down
				foreach ( $ancestor_ids as $ancestor_id ) {
					$ancestor_term = get_term( $ancestor_id, 'category' );
					if ( $ancestor_term && ! is_wp_error( $ancestor_term ) ) {
						$breadcrumbs[] = Schema::listItem()
							->position( $position++ )
							->name( $ancestor_term->name )
							->item( get_term_link( $ancestor_term ) );
					}
				}

				// Add the primary category itself as the final breadcrumb
				$breadcrumbs[] = Schema::listItem()
					->position( $position++ )
					->name( $primary_term->name )
					->item( get_term_link( $primary_term ) );
			}
		}

		// Only return breadcrumb schema if we have more than just Home and Research Topics
		if ( count( $breadcrumbs ) > 1 ) {
			$breadcrumb_list = Schema::breadcrumbList()->itemListElement( $breadcrumbs );

			/**
			 * Filter the breadcrumb schema before returning.
			 *
			 * @param \Spatie\SchemaOrg\BreadcrumbList $breadcrumb_list The BreadcrumbList schema object.
			 * @param int                              $post_id         The post ID.
			 * @param array                            $seo_data        The SEO metadata.
			 */
			return apply_filters( 'prc_schema_seo_breadcrumb_schema', $breadcrumb_list, $post_id, $seo_data );
		}

		return null;
	}

	/**
	 * Generate BreadcrumbList schema for a term archive.
	 *
	 * @param \WP_Term $term Term object.
	 * @return \Spatie\SchemaOrg\BreadcrumbList|null BreadcrumbList schema object.
	 */
	private function generate_term_breadcrumb_schema( $term ) {
		$breadcrumbs = array();
		$position   = 1;

		$breadcrumbs[] = Schema::listItem()
			->position( $position++ )
			->name( 'Home' )
			->item( home_url( '/' ) );

		// Filterable per-taxonomy intermediate crumb.
		$intermediate_crumbs = apply_filters( 'prc_schema_seo_term_breadcrumb_intermediate', array(
			'category' => array(
				'name' => 'Research Topics',
				'url'  => home_url( '/topics' ),
			),
		), $term );

		if ( ! empty( $intermediate_crumbs[ $term->taxonomy ] ) ) {
			$crumb = $intermediate_crumbs[ $term->taxonomy ];
			$breadcrumbs[] = Schema::listItem()
				->position( $position++ )
				->name( $crumb['name'] )
				->item( $crumb['url'] );
		}

		// Walk ancestors top-down.
		$ancestor_ids = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
		foreach ( $ancestor_ids as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$breadcrumbs[] = Schema::listItem()
					->position( $position++ )
					->name( $ancestor->name )
					->item( get_term_link( $ancestor ) );
			}
		}

		// Current term.
		$breadcrumbs[] = Schema::listItem()
			->position( $position++ )
			->name( $term->name )
			->item( get_term_link( $term ) );

		$list = Schema::breadcrumbList()->itemListElement( $breadcrumbs );

		/**
		 * Filter the term breadcrumb schema before returning.
		 *
		 * @param \Spatie\SchemaOrg\BreadcrumbList $list The BreadcrumbList schema object.
		 * @param \WP_Term                         $term The term object.
		 */
		return apply_filters( 'prc_schema_seo_term_breadcrumb_schema', $list, $term );
	}

	/**
	 * Convert schema objects to JSON-LD markup.
	 *
	 * @param array $schemas Array of schema objects.
	 * @return string JSON-LD markup.
	 */
	/**
	 * Convert array of schema objects into JSON-LD script wrapper.
	 *
	 * @param array $schemas Schema objects / arrays.
	 * @return string Script tag or empty string.
	 */
	private function schemas_to_json_ld( $schemas ) {
		if ( empty( $schemas ) ) {
			return '';
		}

		// Build @graph array
		$graph = array();
		foreach ( $schemas as $schema ) {
			if ( is_object( $schema ) && method_exists( $schema, 'toArray' ) ) {
				$graph[] = $schema->toArray();
			} elseif ( is_array( $schema ) ) {
				$graph[] = $schema;
			}
		}

		// Build JSON-LD structure
		$json_ld = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		// Convert to JSON (minify removes pretty-printing for smaller output).
		$json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		if ( ! self::should_minify() ) {
			$json_flags |= JSON_PRETTY_PRINT;
		}
		$json = wp_json_encode( $json_ld, $json_flags );

		// Wrap in script tag
		return sprintf(
			'<script type="application/ld+json">%s</script>',
			$json
		);
	}

	/**
	 * Validate schema output.
	 *
	 * @param string $json_ld JSON-LD markup.
	 * @return bool True if valid.
	 */
	/**
	 * Basic validation routine for generated schema output.
	 *
	 * @param string $json_ld JSON-LD markup.
	 * @return bool True if structure appears valid.
	 */
	public function validate_schema( $json_ld ) {
		// Check if output is not empty
		if ( empty( $json_ld ) ) {
			return false;
		}

		// Check if contains script tag
		if ( false === strpos( $json_ld, '<script type="application/ld+json">' ) ) {
			return false;
		}

		// Extract JSON
		preg_match( '/<script[^>]*>(.*?)<\/script>/s', $json_ld, $matches );
		if ( empty( $matches[1] ) ) {
			return false;
		}

		// Validate JSON
		$json = json_decode( $matches[1], true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		// Check for required properties
		if ( ! isset( $json['@context'] ) || ! isset( $json['@graph'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Prefetch terms for common taxonomies to reduce query count.
	 * Uses object cache (which will populate from single query per taxonomy).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function prefetch_post_terms( $post_id ) {
		// Warm object cache for standard taxonomies likely used in schema generation.
		// wp_get_object_terms will cache results, subsequent calls are cache hits.
		wp_get_object_terms( $post_id, array( 'category', 'formats', 'bylines' ), array( 'fields' => 'all' ) );
	}

	/**
	 * Clear cache for a post, term, post type archive, or home page.
	 *
	 * @param int|string $id   Post ID, term ID, post type slug, or 'home' for home page.
	 * @param string     $type Type: 'post', 'term', 'post_type_archive', or 'home'. Default 'post'.
	 * @return void
	 */
	public function clear_cache( $id, $type = 'post' ) {
		if ( 'term' === $type ) {
			$cache_key = Cache_Keys::term_schema( (int) $id );
		} elseif ( 'post_type_archive' === $type ) {
			$cache_key = Cache_Keys::post_type_archive_schema( (string) $id );
		} elseif ( 'home' === $type ) {
			// Must match generate_publications_page_schema() key (not a legacy home_schema key).
			$cache_key = Cache_Keys::publications_page_schema();
		} else {
			$cache_key = Cache_Keys::schema( (int) $id );
		}
		wp_cache_delete( $cache_key, self::CACHE_GROUP );
		Cache_Keys::forget( array( $cache_key ), self::CACHE_GROUP );
		do_action( 'prc_schema_seo_generator_cache_cleared', $id, $type );
	}
}
