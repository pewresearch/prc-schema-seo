# PRC Schema SEO Changelog

All notable changes to the PRC Schema SEO plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-20

### Added

Initial release of `prc-schema-seo` plugin.

### Features

- JSON-LD schema generation via Spatie library (Organization, Person, Article/NewsArticle variants, WebPage, CollectionPage)
- Automatic meta tag output (title, description, canonical, robots, Open Graph, Twitter card)
- Pattern token engine (static: `%post_title%`, `%site_name%`, `%categories%`; dynamic: `%primary_term:taxonomy%`, `%terms:taxonomy%`)
- Primary term management with filter overrides & fallbacks
- Editor preview panels (search, social, chat, internal)
- Site Editor sidebar for template defaults & noindex configuration
- Object caching (per post/term keys, 1h TTL, automatic invalidation)
- WP-CLI benchmarking commands: `wp prc-schema-seo benchmark` & `wp prc-schema-seo run`
- Batched taxonomy term prefetch optimization
- Stress test handling (large taxonomy cardinality)
- Internationalization (text domain, POT generation guidance)
- Filter/action API (e.g. `prc_schema_seo_meta_tags`, `prc_schema_seo_schema_data`)
- Secure bootstrap guards & sanitization/escaping patterns

### Performance

- Typical post: cold schema ~130ms / ≈2.4MB peak; warm <0.1ms
- Meta tags: cold 8–12ms; warm <1ms
- Heavy stress post (50 categories + 40 tags): cold schema ~119ms; warm ~0.01ms
- Stress meta: cold ~1ms; warm ~0.8ms; cold memory ~1.62MB
- Cache ratio often >10,000× on warm schema retrieval

### CLI

- Benchmark JSON output: cold_ms, warm_ms[], warm_avg_ms, cache_ratio, sizes, memory metrics
- Flags: `--post`, `--term`, `--taxonomy`, `--iterations`

### Documentation

- README covers tokens, filters, benchmarking, stress metrics

### Notes

- Future optimizations: `hrtime()` precision, static invariant schema node caching, optional light builder mode
