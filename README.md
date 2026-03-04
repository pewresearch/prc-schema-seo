# PRC Schema SEO

Provides schema generation, SEO meta tags, primary term management, preview panels, and site-level template defaults for titles & descriptions on Pew Research Center platform sites.

## Features

- JSON-LD schema generation with post-type level overrides
- Meta tag output (title, description, canonical, robots, Open Graph, Twitter)
- Primary term selection per taxonomy
- Editor previews (search, social, chat, internal)
- Site Editor sidebar for global pattern configuration and noindex lists
- Extensible pattern token engine

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

## Filters Overview

Key filters & actions (with typical signatures):

| Hook                                     | Type   | Signature                                                                            | Purpose                                                     |
| ---------------------------------------- | ------ | ------------------------------------------------------------------------------------ | ----------------------------------------------------------- |
| `prc_schema_seo_allowed_schema_types`    | filter | `array types = apply_filters( hook, default, post_type )`                            | Whitelist schema types shown in UI                          |
| `prc_schema_seo_schema_type_default`     | filter | `string type = apply_filters( hook, defaultType, post_type )`                        | Override default schema type per post type                  |
| `prc_schema_seo_schema_data`             | filter | `array graph = apply_filters( hook, graphArray, post_id, seo_data )`                 | Modify array of schema objects before JSON-LD serialization |
| `prc_schema_seo_person_schema`           | filter | `Person obj = apply_filters( hook, personSchema, post_id, seo_data )`                | Adjust Person schema                                        |
| `prc_schema_seo_article_schema`          | filter | `Article obj = apply_filters( hook, articleSchema, post_id, seo_data, schema_type )` | Adjust Article/NewsArticle/etc schema                       |
| `prc_schema_seo_webpage_schema`          | filter | `WebPage obj = apply_filters( hook, webpageSchema, post_id, seo_data )`              | Adjust WebPage schema                                       |
| `prc_schema_seo_organization_schema`     | filter | `Organization obj = apply_filters( hook, orgSchema )`                                | Adjust Organization schema                                  |
| `prc_schema_seo_should_output_schema`    | filter | `bool should = apply_filters( hook, true, post_id )`                                 | Conditionally disable schema output                         |
| `prc_schema_seo_meta_tags`               | filter | `array tags = apply_filters( hook, metaArray, post_id, seo_data )`                   | Modify meta tag array pre-render                            |
| `prc_schema_seo_canonical_url`           | filter | `string url = apply_filters( hook, permalink, post_id, seo_data )`                   | Override canonical URL                                      |
| `prc_schema_seo_noindex`                 | filter | `bool noindex = apply_filters( hook, isNoindex, post_id, seo_data )`                 | Override robots noindex flag                                |
| `prc_schema_seo_og_image_url`            | filter | `string url = apply_filters( hook, ogUrl, post_id, seo_data )`                       | Override Open Graph image URL                               |
| `prc_schema_seo_twitter_image_url`       | filter | `string url = apply_filters( hook, twitterUrl, post_id, seo_data )`                  | Override Twitter image URL                                  |
| `prc_schema_seo_title`                   | filter | `string title = apply_filters( hook, title, post_id )`                               | Final title after pattern resolution                        |
| `prc_schema_seo_title_pattern`           | filter | `string pattern = apply_filters( hook, rawPattern, post_id )`                        | Adjust raw title pattern before token substitution          |
| `prc_schema_seo_description_pattern`     | filter | `string pattern = apply_filters( hook, rawPattern, post_id )`                        | Adjust raw description pattern before token substitution    |
| `prc_schema_seo_description_fallback`    | filter | `string desc = apply_filters( hook, fallbackDesc, post_id )`                         | Provide fallback description text                           |
| `prc_schema_seo_pattern_tokens`          | filter | `array tokens = apply_filters( hook, tokenMap, post_id )`                            | Add/modify pattern tokens                                   |
| `prc_schema_seo_primary_term_id`         | filter | `int termId = apply_filters( hook, termId, taxonomy, post_id )`                      | Override selected primary term per taxonomy                 |
| `prc_schema_seo_template_defaults`       | filter | `array defaults = apply_filters( hook, defaultsArray )`                              | Override site-level template defaults set via settings      |
| `prc_schema_seo_rest_prepare`            | filter | `array data = apply_filters( hook, seoData, post_id )`                               | Modify REST response for seo data                           |
| `prc_schema_seo_sanitized_primary_terms` | filter | `array terms = apply_filters( hook, sanitizedTerms, rawData )`                       | Adjust sanitized primary terms map                          |
| `prc_schema_seo_cache_cleared`           | action | `do_action( hook, post_id )`                                                         | Fires after SEO cache is invalidated                        |
| `prc_schema_seo_schema_output`           | action | `do_action( hook, post_id, jsonLdScript )`                                           | Fires after schema markup is printed                        |
| `prc_schema_seo_after_meta_tags`         | action | `do_action( hook, post_id, tagsHtmlOrArray )`                                        | Fires after meta tags output/cached                         |

Return types should match expected types; invalid types may be ignored and logged (see validation in assets loader for schema types).

## Building

From monorepo root:

```bash
npm run build -w @prc/schema-seo
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
- Primary term taxonomy map stored in `_prc_seo_data` meta key.
- Caching uses dedicated cache group for schema & meta tags.
- Use `prc_schema_seo_allowed_schema_types` to restrict UI options; invalid filter return (non-array or non-string entries) will be logged and ignored.
- Pattern engine supports both static and dynamic tokens; use `prc_schema_seo_pattern_tokens` for simple replacements and dynamic hooks for custom complex parsing.
