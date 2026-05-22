# PRC Schema SEO

Provides schema generation, SEO meta tags, primary term management, preview panels, and site-level template defaults for titles & descriptions on Pew Research Center platform sites.

## Features

- JSON-LD schema generation with post-type level overrides
- Meta tag output (title, description, canonical, robots, Open Graph, Twitter)
- Primary term selection per taxonomy
- Editor previews (search, social, chat, internal)
- Site Editor sidebar for global pattern configuration and noindex lists
- Extensible pattern token engine
- AI-powered SEO suggestions (title, description, social text) via the `prc-schema-seo/suggest` WP AI ability
- Real-Time Collaboration (RTC) compatible editor UI (WP 7.0+)
- Auto-redirects on slug/term changes via Safe Redirect Manager integration
- Redirect CSV import UI
- IndexNow search engine notification on publish
- Google Search Console URL Inspection integration
- Reading score WP Ability
- Parse.ly metadata via the official `wp-parsely` plugin (see below)

## Requirements

**Required plugins** (declared in the plugin header `Requires Plugins` field):

- `prc-scripts` — provides `@prc/components`, `@prc/icons`, and shared webpack configuration
- `prc-post-publish-pipeline` — post lifecycle hooks used for cache invalidation and IndexNow notifications

The plugin no longer depends on `prc-platform-core`. All shared JS components and scripts are sourced from `prc-scripts`.

## Parse.ly (`wp-parsely`)

The platform uses Automattic’s [wp-parsely](https://github.com/Parsely/wp-parsely) on VIP. `prc-schema-seo` implements `Parsely_Integration` (`includes/class-parsely-integration.php`) so Pew SEO data (canonical URL, titles, authors, sections, keywords) is merged into Parse.ly’s metadata pipeline instead of maintaining a parallel tag system.

### How it works

| Context                                             | Behavior                                                                                                                                                                                                                                                                                                                                                                     |
| --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Singular posts/pages (supported post types)         | `wp_parsely_metadata` and `wp_parsely_permalink` enrich Parse.ly’s array with PRC SEO fields from `Metadata::get_seo_data()` / `resolve_for_display()`. Whether the plugin emits JSON-LD or repeated `<meta name="parsely-*">` tags follows **Parse.ly’s own settings** in WP Admin (`meta_type` and related options).                                                       |
| Static front page, category/tag/custom tax archives | PRC emits its own **`parsely-title` / `parsely-link` / `parsely-type`** meta tags from `Parsely_Integration` at `wp_head` priority 3, and suppresses `wp-parsely`'s renderer by returning an empty array from `wp_parsely_metadata` (the renderer bails at its `! isset( $metadata['headline'] )` guard). This works for both `json_ld` and `repeated_metas` output formats. |
| RLS template post type                              | PRC suppresses `wp-parsely` output for this post type by returning an empty array from `wp_parsely_metadata`; PRC does not merge Parse.ly metadata for it.                                                                                                                                                                                                                   |

### WordPress hooks used

| Hook                    | Purpose                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `wpvip_parsely_load_mu` | On `local` environment, return `true` so VIP’s Parse.ly MU integration loads (matches production behavior in dev).                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| `wp_parsely_metadata`   | Two roles: (1) on singular tracked posts, map PRC fields into Parse.ly’s metadata array (headline, `url`, thumbnail, `keywords`, `articleSection`, author/creator, dates); (2) on the front page, term archives, and RLS template singular, return an empty array to suppress `wp-parsely`'s own renderer (which bails when `headline` is unset). The `wp_parsely_should_insert_metadata` filter is intentionally **not** used because `wp-parsely` evaluates it once at plugin init (before `wp` has fired), making conditional-tag-based gating unreliable. |
| `wp_parsely_permalink`  | Align Parse.ly link meta with the PRC canonical URL (`prc_schema_seo_canonical_url` filter applies).                                                                                                                                                                                                                                                                                                                                                                                                                                                          |

### Caching

Home and term Parse.ly HTML fragments are cached with `wp_cache_*` in group `prc_schema_seo_parsely_03112026`, TTL **1 hour**, keys such as `parsely_tags_home` and `parsely_tags_term_{term_id}`. The WP-CLI migration command can warm term cache via the same `Parsely_Integration::warm_term_cache()` path.

### Operational notes

- **Canonical**: Changes that affect `prc_schema_seo_canonical_url` or stored SEO data affect Parse.ly link and `url` fields after cache invalidation or TTL expiry.
- **Keywords / section**: Built from category, formats, research-teams, and internal markers (`get_parsely_tag_tokens`, `get_parsely_section`); empty section is omitted so `articleSection` is not sent when there is no primary category name.
- **Troubleshooting**: If Parse.ly tags are missing locally, confirm environment type is `local` (for MU loader), that `wp-parsely` is active, and that the current template is not one where insertion is intentionally disabled (see table above).

## Pattern Tokens

Patterns can be set for title & description via Site Editor sidebar fields. The following tokens are available:

Static tokens:

```text
%post_title%
%site_name%
%primary_category%
%post_type%
%year%
%author%
%categories%      (comma-separated list of category names)
%tags%            (comma-separated list of tag names)
```

Dynamic tokens:

```text
%primary_term:taxonomy%   (chosen primary term for the taxonomy, falls back to first term)
%terms:taxonomy%          (comma-separated list of all term names for taxonomy)
```

Whitespace is normalized after replacement. Empty tokens resolve to an empty string.

## Extending Tokens

Use the `prc_schema_seo_pattern_tokens` filter to register additional simple tokens (key/value pairs). Dynamic patterns should be handled separately (e.g. by also parsing custom placeholders inside a secondary filter if needed).

```php
add_filter( 'prc_schema_seo_pattern_tokens', function( $tokens, $post_id ) {
    $modified = get_post( $post_id ) ? get_post_modified_time( 'Y-m-d', false, $post_id ) : '';
    $tokens['%modified_date%'] = $modified;
    return $tokens;
}, 10, 2 );
```

## Filters Reference

All hooks use the `prc_schema_seo_` prefix. Return values must match the documented type; invalid types are ignored and logged.

### Schema Output

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_should_output_schema` | `(bool $should, int $post_id)` | Gate schema emission for a post. Return `false` to suppress. |
| `prc_schema_seo_minify_json` | `(bool $minify)` | Control whether JSON-LD output is minified. Default `false`. |
| `prc_schema_seo_schema_type_default` | `(string $type, string $post_type, int $post_id)` | Override the default schema type for a post type. |
| `prc_schema_seo_allowed_schema_types` | `(array $types, string $post_type)` | Whitelist schema types available in the editor UI. Must return a flat array of strings. |
| `prc_schema_seo_schema_data` | `(array $schemas, int $post_id, array $seo_data)` | Modify the full schema graph array just before JSON-LD serialization. |

```php
// Example: mark a custom post type as SoftwareApplication
add_filter( 'prc_schema_seo_schema_type_default', function( $type, $post_type, $post_id ) {
    if ( 'tool' === $post_type ) {
        return 'SoftwareApplication';
    }
    return $type;
}, 10, 3 );
```

### Schema Objects — Article / Person / WebPage

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_article_schema` | `(Article $article, int $post_id, array $seo_data, string $schema_type)` | Adjust Article / NewsArticle / BlogPosting schema object before graph merge. |
| `prc_schema_seo_person_schema` | `(Person $person, int $post_id, array $seo_data)` | Adjust Person schema (used for `staff` post type and author term pages). |
| `prc_schema_seo_webpage_schema` | `(WebPage $webpage, int $post_id, array $seo_data)` | Adjust the final WebPage schema object. |
| `prc_schema_seo_webpage_graph_item` | `(WebPage $webpage, int $post_id, array $seo_data)` | Adjust the WebPage item specifically as a graph item reference before the full schema merge. |
| `prc_schema_seo_breadcrumb_schema` | `(BreadcrumbList $list, int $post_id, array $seo_data)` | Adjust the BreadcrumbList schema for a post. |

### Schema Objects — Organization

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_organization_schema` | `(Organization $org, int $post_id)` | Adjust the fully assembled Organization schema object. |
| `prc_schema_seo_organization_config` | `(array $config)` | Override the Organization config array (name, URL, logo, social profiles). Replaces the full config; merge carefully. |
| `prc_schema_seo_organization_name` | `(string $name)` | Override the organization name string. Used across schema, AI prompts, and Yoast migration. |
| `prc_schema_seo_organization_address` | `(array $address)` | Override the postal address array (`streetAddress`, `addressLocality`, `postalCode`, `addressCountry`). |
| `prc_schema_seo_same_as` | `(array $urls)` | Override the `sameAs` URL array on the Organization schema. |
| `prc_schema_seo_parent_organization` | `(array $config)` | Override the parent organization config (`name`, `url`). Defaults to The Pew Charitable Trusts. |

### Schema Objects — Terms / Archives

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_term_schema` | `(CollectionPage $collection, WP_Term $term)` | Adjust the schema collection for a term archive page. |
| `prc_schema_seo_defined_term_schema` | `(DefinedTerm $defined_term, WP_Term $term, array $term_meta)` | Adjust the DefinedTerm schema object for a taxonomy term. |
| `prc_schema_seo_about_taxonomies` | `(array $taxonomies)` | Taxonomies whose terms are added as `about` references in Article schema. Default: `['category', 'post_tag', 'areas-of-expertise']`. |
| `prc_schema_seo_about_terms` | `(array $about_terms, int $post_id, array $seo_data)` | Adjust the assembled `about` term references array before schema merge. |
| `prc_schema_seo_post_type_archive_schema` | `(CollectionPage $collection, WP_Post_Type $post_type_object)` | Adjust the schema collection for a post type archive page. |
| `prc_schema_seo_post_type_archive_schema_data` | `(array $schemas, string $post_type)` | Modify the full schema data array for a post type archive (includes Website + Organization). |
| `prc_schema_seo_publications_page_schema` | `(CollectionPage $collection)` | Adjust the schema for the publications index page. |
| `prc_schema_seo_cache_post_type_archive_schema` | `(bool $should_cache, string $post_type)` | Enable or disable schema caching for a specific post type archive. |

### Schema Objects — Breadcrumbs

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_term_breadcrumb_schema` | `(BreadcrumbList $list, WP_Term $term)` | Adjust the breadcrumb list for a term archive. |
| `prc_schema_seo_term_breadcrumb_intermediate` | `(array $crumbs)` | Override the intermediate crumb definitions per taxonomy (e.g., add a "Research Topics" node before category crumbs). Keyed by taxonomy slug. |

### Meta Tags

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_meta_tags` | `(array $meta, int $post_id, array $seo_data)` | Modify the full meta tag array before rendering. Applies to posts, terms, home, and archives. `$post_id` is `0` for non-singular contexts. |
| `prc_schema_seo_canonical_url` | `(string $url, int $post_id, array $seo_data)` | Override the canonical URL. Also used by the Parse.ly integration. |
| `prc_schema_seo_noindex` | `(bool $noindex, int $post_id, array $seo_data)` | Override the robots noindex flag. Applies to posts, terms, and archive contexts. |
| `prc_schema_seo_og_image_url` | `(string $url, int $post_id, array $seo_data)` | Override the Open Graph image URL. Used for both `og:image` and `twitter:image`. |
| `prc_schema_seo_og_image_fallback` | `(int\|null $attachment_id, int $post_id)` | Provide a fallback OG image attachment ID when no image is set. |
| `prc_schema_seo_twitter_site` | `(string $handle)` | Override the `twitter:site` handle. Default: `@pewresearch`. |
| `prc_schema_seo_article_publisher_url` | `(string $url)` | Override the `article:publisher` Open Graph URL. Default: the organization's Facebook URL. |
| `prc_schema_seo_title` | `(string $title, int $post_id)` | Final title string after all pattern and token resolution. Applied at output time. |
| `prc_schema_seo_title_separator` | `(string $sep)` | Override the title separator string. Default: `' | '`. Used in token resolution and meta tag title construction. |
| `prc_schema_seo_description_fallback` | `(string $desc, int $post_id)` | Override the resolved description string at output time. |
| `prc_schema_seo_cache_post_type_archive_meta_tags` | `(bool $should_cache, string $post_type)` | Enable or disable meta tag caching for a specific post type archive. |

```php
// Example: append site name suffix to all titles
add_filter( 'prc_schema_seo_title', function( $title, $post_id ) {
    return $title . ' | Pew Research Center';
}, 10, 2 );

// Example: suppress schema on a specific post
add_filter( 'prc_schema_seo_should_output_schema', function( $should, $post_id ) {
    if ( 12345 === $post_id ) {
        return false;
    }
    return $should;
}, 10, 2 );
```

### Tokens & Patterns

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_pattern_tokens` | `(array $tokens, int $post_id)` | Add or modify the token map used in title/description pattern substitution. |
| `prc_schema_seo_all_template_defaults` | `(array $defaults)` | Override the full array of site-level template defaults (title and description patterns per context type). |
| `prc_schema_seo_template_contexts` | `(array $contexts)` | Filter the available template context type definitions. |

### Primary Terms

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_primary_term_taxonomies` | `(array $taxonomies)` | Taxonomies registered for primary term selection in the editor. Default: `['category', 'post_tag']`. |
| `prc_schema_seo_primary_term_id` | `(int $term_id, string $taxonomy, int $post_id)` | Override the selected primary term ID for a given taxonomy and post. |
| `prc_schema_seo_sanitized_primary_terms` | `(array $terms, array $raw_data)` | Adjust the sanitized primary terms map after sanitization. |

### REST API

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_rest_prepare` | `(array $seo_data, int $post_id)` | Modify the SEO data array before it is returned from the REST API endpoint. |

### Editor UI

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_branding` | `(array $branding)` | Override the branding config passed to the block editor UI (keys: `siteName`, `displayName`, `twitterUsername`, `logoUrl`). |
| `prc_schema_seo_qr_logo_url` | `(string $url)` | Override the logo URL embedded in QR code images. |

### Contact Resolution

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_resolved_contact` | `(array $contact, int $post_id)` | Adjust the resolved contact record for a post (used in schema author field). |
| `prc_schema_seo_default_contact` | `(array $default)` | Override the default fallback contact info when no author is resolved. |

### Redirects

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_auto_redirect_enabled` | `(bool $enabled)` | Enable or disable automatic 301 redirect creation on slug changes. Default: `true`. |
| `prc_schema_seo_auto_redirect_status_code` | `(int $code)` | Override the HTTP status code for auto-redirects. Default: `301`. |
| `prc_schema_seo_auto_redirect_post_types` | `(bool $eligible, string $post_type)` | Control whether a specific post type gets auto-redirects on slug change. |
| `prc_schema_seo_enabled_taxonomies_for_term_meta` | `(array $taxonomies)` | Taxonomies that get SEO term meta UI fields and auto-redirect support. Default: `['category', 'post_tag', 'areas-of-expertise']`. |

### Redirect CSV Import

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_csv_import_max_size` | `(int $bytes)` | Maximum file size allowed for redirect CSV uploads. Default: `5 * MB_IN_BYTES`. |
| `prc_schema_seo_csv_import_column_mapping` | `(array $mapping)` | Override column name-to-SRM field mapping for CSV imports. |

### AI Features

These filters require the WP AI plugin to be active.

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_ai_request_timeout` | `(int $seconds)` | Override the HTTP timeout for AI API requests. Default: `60`. |
| `prc_schema_seo_ai_brand_context` | `(string $instructions)` | Override the full brand context instruction string passed to the AI model. |
| `prc_schema_seo_ai_brand_rules` | `(string $rules)` | Override the brand-specific writing rules injected into AI prompts (e.g., "key findings" phrasing guidance). |

```php
// Example: customize the AI system prompt context for your organization
add_filter( 'prc_schema_seo_ai_brand_context', function( $instructions ) {
    return str_replace( 'nonpartisan research organization', 'global news outlet', $instructions );
} );
```

### Search Console & IndexNow

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_gsc_production_url` | `(string $url)` | Override the production base URL used to map local URLs for GSC inspection. Default: `https://www.pewresearch.org`. |
| `prc_schema_seo_indexnow_enabled` | `(bool $enabled)` | Enable or disable IndexNow search engine notifications. Default: `true`. |

### Utility

| Filter | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_category_expertise_id` | `(int $expertise_id, int $category_term_id)` | Override the resolved areas-of-expertise term ID for a category term. |
| `prc_schema_seo_yoast_primary_term_taxonomies` | `(array $taxonomies)` | Override the taxonomies scanned when migrating primary terms from Yoast SEO. |

### Actions

| Action | Signature | Purpose |
| ------ | --------- | ------- |
| `prc_schema_seo_cache_cleared` | `(int $post_id)` | Fires after all SEO caches are invalidated for a post. |
| `prc_schema_seo_schema_output` | `(mixed $id, string $json_ld)` | Fires after schema JSON-LD markup is printed. `$id` is the post ID, term ID, post type slug, or `'publications'`. |
| `prc_schema_seo_after_meta_tags` | `(int $post_id, string $html)` | Fires after meta tags HTML is output or served from cache. `$post_id` is `0` for non-singular contexts. |
| `prc_schema_seo_term_meta_updated` | `(int $term_id, array $meta)` | Fires after term SEO meta is saved via the taxonomy UI. |
| `prc_schema_seo_generator_cache_cleared` | `(mixed $id, string $type)` | Fires after the schema generator cache is cleared for a specific ID and type. |

## JavaScript Filters

The block editor UI exposes three `@wordpress/hooks` filter points for extending the SEO sidebar panels. Use `addFilter` from `@wordpress/hooks`:

| Filter | Panel | Purpose |
| ------ | ----- | ------- |
| `prc-platform.seo.ui.search` | Block editor — Search tab | Append additional `PanelBody` sections after the Search and Search Advanced panels. |
| `prc-platform.seo.ui.social` | Block editor — Social tab | Wrap or extend the Social metadata panel. |
| `prc-platform.seo.ui.site-editor.social` | Site Editor — Social tab | Wrap or extend the Social panel in the Site Editor context. |

```js
import { addFilter } from '@wordpress/hooks';

addFilter(
    'prc-platform.seo.ui.search',
    'my-plugin/extend-search-panel',
    ( SearchComponent ) => ( props ) => (
        <>
            <SearchComponent { ...props } />
            { /* Additional panels */ }
        </>
    )
);
```

## Building

From monorepo root (uses Turbo for cache-aware builds):

```bash
npx turbo build --filter=@prc/schema-seo
```

To also rebuild downstream consumers:

```bash
npx turbo build --filter=@prc/schema-seo...
```

## Internationalization

All user-facing strings are wrapped with `__()`, `_e()`, or `esc_html_e()` using the `prc-schema-seo` text domain. A POT file is maintained in `languages/prc-schema-seo.pot`.

To regenerate the POT file (requires WP-CLI):

```bash
wp i18n make-pot . languages/prc-schema-seo.pot --domain=prc-schema-seo
```

Or use the npm script for a reminder:

```bash
npm run i18n:pot -w @prc/schema-seo
```

Translators can use the POT file to create PO/MO files for specific locales (e.g., `prc-schema-seo-es_ES.po`).

## Performance

The plugin uses WordPress object caching extensively to ensure fast page loads:

### Benchmarked Metrics (Typical Post)

- **Schema Generation**:
    - Cold (cache miss): ~130ms + 2.4 MB memory
    - Warm (cached): <0.1ms (13,000x faster)
    - Cache TTL: 1 hour
- **Meta Tags**:
    - Cold: ~8-12ms
    - Warm (cached): <1ms
    - Cache TTL: 1 hour

### Cache Strategy

- All schema and meta tag output is cached using `wp_cache_*` functions
- Cache keys are post/term-specific: `schema_{post_id}`, `meta_tags_{post_id}`
- Cache automatically invalidates on post/term updates
- Memory overhead is minimal on warm requests (~0 KB incremental)

### Performance Characteristics

- **Normal operation**: Warm cache ensures <1ms overhead per request
- **Cache miss scenarios**: Post update, cache flush, or first view after TTL expiry
- **Cold timing acceptable**: 130ms amortized over 1 hour (3600 requests) = 0.036ms average per request
- **VIP-optimized**: Uses memcached object cache on WordPress VIP for distributed caching
- **Optimization applied**: Batched taxonomy term queries reduce cold generation time

### Benchmarking

Use the included WP-CLI command to measure performance on your content:

```bash
wp prc-schema-seo benchmark --post=123
wp prc-schema-seo benchmark --post=123 --iterations=5
wp prc-schema-seo benchmark --term=55 --taxonomy=category
```

Output includes cold/warm timings, memory usage, cache ratios, and output sizes in JSON format.

### Stress Test (Heavy Taxonomy Load)

Benchmark on a deliberately heavy post (≈50 categories + 40 tags) to validate worst-case performance:

| Scenario   | Schema Cold | Schema Warm | Meta Cold | Meta Warm Avg | Cold Memory (Schema) | Cold Memory (Meta) | Output Size (Schema) | Output Size (Meta) |
| ---------- | ----------- | ----------- | --------- | ------------- | -------------------- | ------------------ | -------------------- | ------------------ |
| Heavy Post | 119.25ms    | 0.01ms      | 1.01ms    | 0.80ms        | ~1.62MB              | ~1.5KB             | 1400 bytes           | 1436 bytes         |

Observations:

- Warm cache retrieval remains near-zero even under large taxonomy cardinality.
- One-time cold schema cost (≈120ms) is acceptable given 1h TTL and amortization.
- Memory footprint for cold build reflects Spatie object graph construction; warm delta is effectively 0KB.
- Meta tag generation remains sub-millisecond warm even when schema includes many term references.

Planned refinements (optional): switch to `hrtime()` for higher precision warm timings and static caching of invariant Organization/Publisher nodes to reduce cold build time further.

## Notes

- Title/description patterns only apply when explicit values are missing.
- All SEO data is stored in the `_prc_seo_data` post meta key as a serialized array.
- Primary term data is part of the `_prc_seo_data` structure under the `primary_terms` key (not a separate meta).
- Caching uses dedicated cache groups: `prc_schema_seo_data_03112026` (metadata), plus per-class groups in `Generator` and `Meta_Tags`.
- Use `prc_schema_seo_allowed_schema_types` to restrict UI options; invalid filter returns (non-array or non-string entries) are logged and ignored.
- Pattern engine supports both static tokens (e.g., `%post_title%`) and dynamic tokens (e.g., `%primary_term:category%`). Use `prc_schema_seo_pattern_tokens` for simple key/value additions.
- The `prc_schema_seo_noindex` filter fires in singular, term, home, and archive contexts. When `$post_id` is `0`, the `$seo_data` argument contains template-level defaults rather than post-level data.
- `prc_schema_seo_ai_brand_context` and `prc_schema_seo_ai_brand_rules` require the WP AI plugin (`wordpress/ai`) to be active. They are no-ops if the AI plugin is not loaded.
- The block editor UI is RTC (Real-Time Collaboration) compatible as of v1.1.0. All editor state goes through `editPost()` / `edit_post` REST and is tracked in the collaborative session.
