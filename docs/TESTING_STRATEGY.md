# PRC Schema SEO Testing Strategy

This document outlines the comprehensive testing strategy for validating the `prc-schema-seo` plugin before it replaces Yoast SEO in production. The goal is to ensure SEO parity, prevent traffic loss, and maintain search engine ranking stability.

---

## Table of Contents

1. [Overview](#overview)
2. [Available CLI Tools](#available-cli-tools)
3. [Pre-Launch Testing Phases](#pre-launch-testing-phases)
4. [Phase 1: Data Migration Validation](#phase-1-data-migration-validation)
5. [Phase 2: Meta Tag Parity Testing](#phase-2-meta-tag-parity-testing)
6. [Phase 3: Schema.org Validation](#phase-3-schemaorg-validation)
7. [Phase 4: Performance Benchmarking](#phase-4-performance-benchmarking)
8. [Phase 5: Integration Testing](#phase-5-integration-testing)
9. [Phase 6: Visual/Manual Testing](#phase-6-visualmanual-testing)
10. [Automated Test Suite](#automated-test-suite)
11. [Production Rollout Strategy](#production-rollout-strategy)
12. [Monitoring & Rollback Plan](#monitoring--rollback-plan)
13. [Testing Checklists](#testing-checklists)

---

## Overview

### Critical SEO Elements to Validate

| Element            | Impact                         | Priority |
| ------------------ | ------------------------------ | -------- |
| Title tags         | Direct ranking factor          | Critical |
| Meta descriptions  | Click-through rate             | Critical |
| Canonical URLs     | Duplicate content prevention   | Critical |
| Robots directives  | Indexation control             | Critical |
| Open Graph tags    | Social sharing appearance      | High     |
| Twitter Card tags  | Social sharing appearance      | High     |
| JSON-LD Schema     | Rich snippets, knowledge graph | High     |
| Primary terms      | URL structure, breadcrumbs     | Medium   |
| Sitemap exclusions | Crawl efficiency               | Medium   |

### Risk Assessment

| Risk                     | Mitigation                                        |
| ------------------------ | ------------------------------------------------- |
| Missing meta tags        | Compare tool validation against production        |
| Different schema output  | Schema comparison and Google Rich Results testing |
| Broken canonical URLs    | Automated URL validation                          |
| Noindex/nofollow changes | Robots directive comparison                       |
| Performance degradation  | Benchmark testing with cold/warm cache            |
| Yoast data loss          | Dry-run migration + verification                  |

---

## Available CLI Tools

The plugin provides several WP-CLI commands for testing and validation:

### Comparison Commands

```bash
# Compare single post SEO output against production
wp prc-seo compare <post_id>
wp prc-seo compare <post_id> --format=json
wp prc-seo compare <post_id> --diff-only
wp prc-seo compare <post_id> --format=full

# Batch compare multiple posts
wp prc-seo compare-batch
wp prc-seo compare-batch --post-type=post --limit=100
wp prc-seo compare-batch --format=json > comparison-results.json
```

### Migration Commands

```bash
# Check migration status
wp prc-seo migration-status

# Preview Yoast data without migrating
wp prc-seo preview-yoast <post_id>

# Migrate with dry-run (no database changes)
wp prc-seo migrate-post <post_id> --dry-run
wp prc-seo migrate-posts --dry-run --limit=100

# Execute migration
wp prc-seo migrate-post <post_id>
wp prc-seo migrate-posts --post-type=post --batch-size=100
wp prc-seo migrate-term <term_id> --taxonomy=category
wp prc-seo migrate-terms --taxonomy=category
```

### Performance Commands

```bash
# Benchmark schema/meta generation
wp prc-seo benchmark --post=<post_id>
wp prc-seo benchmark --post=<post_id> --iterations=5
wp prc-seo benchmark --term=<term_id> --taxonomy=category
```

---

## Pre-Launch Testing Phases

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        TESTING TIMELINE                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Phase 1: Migration     Phase 2: Meta Tags    Phase 3: Schema               │
│  ─────────────────      ───────────────────   ─────────────                  │
│  • Dry-run migration    • Compare vs prod     • JSON-LD validation          │
│  • Data verification    • All meta types      • Rich Results Test           │
│  • Edge cases           • 404/redirects       • Type coverage               │
│                                                                              │
│  Phase 4: Performance   Phase 5: Integration  Phase 6: Visual               │
│  ───────────────────    ───────────────────   ─────────────                  │
│  • Benchmark cold/warm  • Sitemap check       • SERP preview                │
│  • Memory profiling     • Caching layers      • Social preview              │
│  • Load testing         • REST API            • Editor UI                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Data Migration Validation

### Objectives

- Ensure all Yoast SEO data migrates correctly to PRC Schema SEO format
- Validate no data loss occurs during migration
- Identify edge cases requiring manual attention

### Test Procedures

#### 1.1 Migration Status Assessment

```bash
# Get overview of migration scope
wp prc-seo migration-status

# Expected output shows counts of:
# - Posts with Yoast meta
# - Posts with PRC meta (already migrated)
# - Posts pending migration
```

#### 1.2 Dry-Run Migration Testing

```bash
# Test migration on sample posts without saving
wp prc-seo migrate-posts --dry-run --limit=50

# Review specific high-traffic posts
wp prc-seo preview-yoast <high-traffic-post-id>
wp prc-seo migrate-post <high-traffic-post-id> --dry-run
```

#### 1.3 Data Field Mapping Verification

| Yoast Field                          | PRC Field                | Validation                        |
| ------------------------------------ | ------------------------ | --------------------------------- |
| `_yoast_wpseo_title`                 | `title`                  | Exact match or pattern equivalent |
| `_yoast_wpseo_metadesc`              | `description`            | Exact match                       |
| `_yoast_wpseo_canonical`             | `canonical_url`          | URL validation                    |
| `_yoast_wpseo_meta-robots-noindex`   | `noindex`                | Boolean conversion                |
| `_yoast_wpseo_opengraph-title`       | `og_title`               | Exact match                       |
| `_yoast_wpseo_opengraph-description` | `og_description`         | Exact match                       |
| `_yoast_wpseo_opengraph-image`       | `og_image`               | Attachment ID match               |
| `_yoast_wpseo_primary_category`      | `primary_terms.category` | Term ID match                     |

#### 1.4 Edge Case Testing

Test these specific scenarios:

```bash
# Posts with custom canonical URLs
wp db query "SELECT post_id FROM wp_postmeta WHERE meta_key = '_yoast_wpseo_canonical' AND meta_value != ''" --skip-column-names | while read id; do
  wp prc-seo migrate-post $id --dry-run
done

# Posts marked as noindex
wp db query "SELECT post_id FROM wp_postmeta WHERE meta_key = '_yoast_wpseo_meta-robots-noindex' AND meta_value = '1'" --skip-column-names | head -10 | while read id; do
  wp prc-seo compare $id --diff-only
done

# Posts with custom OG images
wp db query "SELECT post_id FROM wp_postmeta WHERE meta_key = '_yoast_wpseo_opengraph-image-id'" --skip-column-names | head -10 | while read id; do
  wp prc-seo preview-yoast $id
done
```

### Success Criteria

- [ ] 100% of posts with Yoast data show successful dry-run migration
- [ ] Primary category mappings verified for sample posts
- [ ] Custom canonical URLs preserved
- [ ] Noindex flags correctly converted
- [ ] No migration errors logged

---

## Phase 2: Meta Tag Parity Testing

### Objectives

- Verify all meta tags match production Yoast output
- Identify any missing or changed meta values
- Validate robots directives are consistent

### Test Procedures

#### 2.1 Single Post Comparison

```bash
# Compare high-traffic posts
wp prc-seo compare <post_id> --format=full

# Review comparison summary
wp prc-seo compare <post_id>
# Look for:
# - Matching: should be high
# - Different: should be zero or explained
# - Local only: new fields we're adding
# - Production only: fields we might be missing
```

#### 2.2 Batch Comparison

```bash
# Compare recent posts
wp prc-seo compare-batch --post-type=post --limit=100 --format=json > comparison-posts.json

# Compare pages
wp prc-seo compare-batch --post-type=page --limit=50 --format=json > comparison-pages.json

# Analyze results
cat comparison-posts.json | jq '.summary'
cat comparison-posts.json | jq '.results[] | select(.status == "partial")'
```

#### 2.3 Meta Tag Checklist

For each compared post, verify:

| Meta Tag                           | Check                                             |
| ---------------------------------- | ------------------------------------------------- |
| `<title>`                          | Matches or follows acceptable pattern             |
| `<meta name="description">`        | Content and length match                          |
| `<link rel="canonical">`           | URL exactly matches                               |
| `<meta name="robots">`             | Directives match (index,follow or noindex,follow) |
| `<meta property="og:title">`       | Matches or falls back correctly                   |
| `<meta property="og:description">` | Matches or falls back correctly                   |
| `<meta property="og:image">`       | Same image or valid fallback                      |
| `<meta property="og:url">`         | Matches canonical                                 |
| `<meta property="og:type">`        | article for posts, website otherwise              |
| `<meta name="twitter:card">`       | summary_large_image or summary                    |

#### 2.4 Special Page Testing

Test these critical pages manually:

```bash
# Homepage
wp prc-seo compare <homepage-id> --format=full

# Category archives (use term-specific commands if available)
# These require manual front-end verification

# Author/staff pages
wp prc-seo compare <staff-page-id> --format=full

# Search results page (manual verification needed)
```

### Success Criteria

- [ ] 95%+ meta tag match rate across batch comparison
- [ ] All critical pages show matching or acceptable meta tags
- [ ] No unexpected noindex changes
- [ ] Canonical URLs 100% match for all compared posts

---

## Phase 3: Schema.org Validation

### Objectives

- Verify JSON-LD schema output matches expected structure
- Validate schema with Google Rich Results Test
- Ensure all required schema types are present

### Test Procedures

#### 3.1 Schema Type Coverage

Compare schema types between local and production:

```bash
# Full comparison shows schema types
wp prc-seo compare <post_id> --format=full

# Look for schema types in output:
# Local types: Article, Organization, WebSite, WebPage, BreadcrumbList
# Production types: (same set expected)
```

#### 3.2 Google Rich Results Testing

For sample posts, test schema validity:

1. Generate local schema output:

```bash
wp prc-seo compare <post_id> --format=json | jq '.local.schema' > local-schema.json
```

2. Test in Google Rich Results Test: https://search.google.com/test/rich-results

3. Compare with production URL results

#### 3.3 Schema Element Verification

| Schema Type    | Required Properties                           | Validation                |
| -------------- | --------------------------------------------- | ------------------------- |
| Organization   | name, url, logo, sameAs                       | All present               |
| WebSite        | name, url, potentialAction                    | SearchAction configured   |
| Article        | headline, author, datePublished, dateModified | All populated             |
| NewsArticle    | Same as Article + publisher                   | Publisher is Organization |
| Person         | name, url, image (for staff)                  | Linked correctly          |
| BreadcrumbList | itemListElement                               | Valid hierarchy           |
| WebPage        | name, url                                     | Present for pages         |
| CollectionPage | name, url                                     | Present for archives      |

#### 3.4 Schema Comparison Script

```bash
#!/bin/bash
# schema-compare.sh - Compare schema output for multiple posts

POST_IDS=(123 456 789 1011)  # Replace with actual high-traffic post IDs

for id in "${POST_IDS[@]}"; do
  echo "=== Post ID: $id ==="
  wp prc-seo compare $id --format=json | jq '{
    post_id: .post_id,
    local_schema_types: .local.schema["@graph"] | map(.["@type"]) | sort,
    production_schema_types: .production.schema["@graph"] | map(.["@type"]) | sort,
    types_match: .comparison.schema.types_match
  }'
  echo ""
done
```

### Success Criteria

- [ ] Schema types match between local and production for 95%+ of posts
- [ ] All schema passes Google Rich Results Test validation
- [ ] Organization schema includes all social profiles
- [ ] Article schema includes proper author linking
- [ ] BreadcrumbList uses primary term correctly

---

## Phase 4: Performance Benchmarking

### Objectives

- Ensure schema/meta generation doesn't degrade page load
- Validate caching effectiveness
- Establish performance baseline for monitoring

### Test Procedures

#### 4.1 Cold vs Warm Cache Testing

```bash
# Benchmark typical post
wp prc-seo benchmark --post=<post_id> --iterations=5

# Expected output (JSON):
# {
#   "metrics": {
#     "post": {
#       "schema": {
#         "cold_ms": ~130,
#         "warm_avg_ms": <1,
#         "cache_ratio": >100
#       },
#       "meta": {
#         "cold_ms": ~10,
#         "warm_avg_ms": <1
#       }
#     }
#   }
# }
```

#### 4.2 Term Page Benchmarking

```bash
# Benchmark category page
wp prc-seo benchmark --term=<category_id> --taxonomy=category --iterations=5
```

#### 4.3 Heavy Content Testing

Test posts with many terms to validate worst-case performance:

```bash
# Find posts with many categories
wp db query "SELECT object_id, COUNT(*) as cnt FROM wp_term_relationships tr JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy = 'category' GROUP BY object_id ORDER BY cnt DESC LIMIT 5"

# Benchmark heavy posts
wp prc-seo benchmark --post=<heavy_post_id> --iterations=3
```

#### 4.4 Performance Acceptance Thresholds

| Metric                 | Threshold | Action if Exceeded        |
| ---------------------- | --------- | ------------------------- |
| Schema cold generation | < 200ms   | Investigate optimization  |
| Schema warm retrieval  | < 1ms     | Check cache configuration |
| Meta tags cold         | < 20ms    | Acceptable                |
| Meta tags warm         | < 1ms     | Check cache configuration |
| Memory (cold schema)   | < 3MB     | Profile memory usage      |
| Cache ratio            | > 50x     | Validate cache TTL        |

### Success Criteria

- [ ] Warm cache retrieval < 1ms for schema and meta
- [ ] Cold generation acceptable for amortized performance
- [ ] No memory leaks detected across iterations
- [ ] Cache invalidation works correctly on post save

---

## Phase 5: Integration Testing

### Objectives

- Verify integration with other platform components
- Test REST API functionality
- Validate sitemap exclusion logic

### Test Procedures

#### 5.1 REST API Testing

```bash
# Verify SEO data in REST response
wp post list --post_type=post --field=ID --format=csv | head -5 | while read id; do
  echo "Post $id:"
  wp eval "echo json_encode(get_post_meta($id, '_prc_seo_data', true), JSON_PRETTY_PRINT);"
  echo ""
done

# Test REST endpoint directly
curl -s "https://localhost/wp-json/wp/v2/posts/<id>" | jq '.prc_seo_data'
```

#### 5.2 Sitemap Integration

```bash
# Verify noindex posts are excluded from sitemap
wp db query "SELECT post_id FROM wp_postmeta WHERE meta_key = '_prc_seo_data' AND meta_value LIKE '%\"noindex\":true%'" --skip-column-names | while read id; do
  echo "Checking noindex post $id in sitemap..."
  # Verify post is not in sitemap
done
```

#### 5.3 Cache Invalidation Testing

```bash
# Update a post and verify cache clears
wp post update <post_id> --post_title="Updated Title $(date +%s)"

# Immediately check if cached values are invalidated
wp cache get schema_<post_id> prc_schema_seo_output
# Should return empty/error indicating cache was cleared
```

#### 5.4 Primary Term Integration

```bash
# Verify primary term in breadcrumbs
wp eval "
  \$post_id = <post_id>;
  \$seo_data = get_post_meta(\$post_id, '_prc_seo_data', true);
  print_r(\$seo_data['primary_terms'] ?? 'No primary terms set');
"
```

### Success Criteria

- [ ] REST API returns complete SEO data
- [ ] Noindex posts excluded from sitemaps
- [ ] Cache invalidates on content updates
- [ ] Primary terms used in breadcrumb schema

---

## Phase 6: Visual/Manual Testing

### Objectives

- Verify editor UI functionality
- Test social sharing previews
- Validate rendered output in browser

### Test Procedures

#### 6.1 Block Editor Testing

| Test                   | Steps                         | Expected Result                  |
| ---------------------- | ----------------------------- | -------------------------------- |
| SEO Panel Opens        | Edit post → Click "SEO" panel | Panel displays with all fields   |
| Title Field            | Enter custom title            | Title saves and shows in preview |
| Description Field      | Enter description > 160 chars | Character count updates          |
| Schema Type Selection  | Change schema type            | Saves correctly                  |
| Noindex Toggle         | Enable noindex                | Robots meta updates              |
| Primary Term Selection | Select primary category       | Breadcrumb updates               |
| OG Image Selection     | Choose OG image               | Image URL in meta tags           |

#### 6.2 Site Editor Testing

| Test                    | Steps                       | Expected Result        |
| ----------------------- | --------------------------- | ---------------------- |
| Template Defaults Panel | Open Site Editor → Settings | SEO panel visible      |
| Title Pattern           | Set pattern with tokens     | Pattern saves          |
| Default Schema Type     | Select per-template type    | Applied to new posts   |
| Noindex List            | Add URL patterns            | Matched URLs noindexed |

#### 6.3 Front-End Verification

For sample posts, verify in browser:

1. **View Page Source**
    - Search for `<title>` tag
    - Search for `application/ld+json`
    - Verify meta tags in `<head>`

2. **Browser Dev Tools**
    - Network tab: Verify no SEO-related errors
    - Console: Check for JavaScript errors

3. **Social Sharing Debug Tools**
    - Facebook Sharing Debugger: https://developers.facebook.com/tools/debug/
    - Twitter Card Validator: https://cards-dev.twitter.com/validator
    - LinkedIn Post Inspector: https://www.linkedin.com/post-inspector/

4. **Google Tools**
    - Rich Results Test: https://search.google.com/test/rich-results
    - Mobile-Friendly Test: https://search.google.com/test/mobile-friendly
    - URL Inspection (Search Console): Verify rendering

### Success Criteria

- [ ] All editor UI elements functional
- [ ] Meta tags visible in page source
- [ ] Social previews render correctly
- [ ] Schema validates in Rich Results Test
- [ ] No console errors related to SEO

---

## Automated Test Suite

### Playwright E2E Tests

The plugin includes Playwright tests that can be extended:

```bash
# Run existing tests
npm run test -w @prc/schema-seo

# Run with specific test file
npm run test -w @prc/schema-seo -- tests/test-template.spec.ts
```

### Recommended Additional Tests

Create these test files in `tests/`:

#### `tests/seo-meta-tags.spec.ts`

```typescript
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('SEO Meta Tags', () => {
	test('Post has correct meta tags', async ({ page, requestUtils }) => {
		// Create test post with known SEO data
		const post = await requestUtils.createPost({
			title: 'SEO Test Post',
			status: 'publish',
			meta: {
				_prc_seo_data: JSON.stringify({
					title: 'Custom SEO Title',
					description: 'Custom meta description for testing',
				}),
			},
		});

		// Visit the post
		await page.goto(`/?p=${post.id}`);

		// Verify meta tags
		const title = await page.locator('title').textContent();
		expect(title).toContain('Custom SEO Title');

		const description = await page
			.locator('meta[name="description"]')
			.getAttribute('content');
		expect(description).toBe('Custom meta description for testing');
	});

	test('Noindex post has robots noindex', async ({ page, requestUtils }) => {
		const post = await requestUtils.createPost({
			title: 'Noindex Test Post',
			status: 'publish',
			meta: {
				_prc_seo_data: JSON.stringify({
					noindex: true,
				}),
			},
		});

		await page.goto(`/?p=${post.id}`);

		const robots = await page
			.locator('meta[name="robots"]')
			.getAttribute('content');
		expect(robots).toContain('noindex');
	});
});
```

#### `tests/schema-output.spec.ts`

```typescript
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('Schema Output', () => {
	test('Post has valid JSON-LD schema', async ({ page, requestUtils }) => {
		const post = await requestUtils.createPost({
			title: 'Schema Test Post',
			content: 'Test content for schema validation',
			status: 'publish',
		});

		await page.goto(`/?p=${post.id}`);

		// Get JSON-LD script content
		const schemaScript = await page
			.locator('script[type="application/ld+json"]')
			.textContent();
		const schema = JSON.parse(schemaScript);

		// Verify schema structure
		expect(schema['@context']).toBe('https://schema.org');
		expect(schema['@graph']).toBeInstanceOf(Array);

		// Verify Article schema present
		const article = schema['@graph'].find(
			(item: any) => item['@type'] === 'Article'
		);
		expect(article).toBeDefined();
		expect(article.headline).toBe('Schema Test Post');
	});
});
```

### Running Full Test Suite

```bash
# Start test environment
npm run playground:start

# Run all SEO tests
npm run test -w @prc/schema-seo

# Generate test report
# Reports saved to tests/artifacts/reports/
```

---

## Production Rollout Strategy

### Recommended Approach: Gradual Rollout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        ROLLOUT PHASES                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Week 1: Staging           Week 2: Canary           Week 3: Full Rollout    │
│  ─────────────────         ─────────────────        ──────────────────      │
│  • Deploy to staging       • Enable on 5% traffic   • Enable 100%           │
│  • Full test suite         • Monitor Search Console • Disable Yoast         │
│  • Team review             • Compare rankings       • Document learnings    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Pre-Deployment Checklist

- [ ] All migration dry-runs successful
- [ ] Batch comparison shows 95%+ match rate
- [ ] Performance benchmarks within thresholds
- [ ] E2E tests passing
- [ ] Manual testing completed for critical pages
- [ ] Rollback plan documented and tested

### Deployment Steps

1. **Pre-deployment**

    ```bash
    # Final migration status check
    wp prc-seo migration-status

    # Run final batch comparison
    wp prc-seo compare-batch --limit=500 --format=json > pre-deploy-comparison.json
    ```

2. **Deploy**
    - Activate `prc-schema-seo` plugin
    - Deactivate Yoast SEO (but don't delete yet)

3. **Immediate Validation**

    ```bash
    # Verify homepage
    wp prc-seo compare <homepage_id>

    # Verify recent posts
    wp prc-seo compare-batch --limit=20
    ```

4. **Monitoring Period**
    - Monitor for 48-72 hours
    - Check Search Console for crawl errors
    - Review any user-reported issues

---

## Monitoring & Rollback Plan

### Key Metrics to Monitor

| Metric         | Source                | Alert Threshold         |
| -------------- | --------------------- | ----------------------- |
| Crawl errors   | Google Search Console | >10 new errors          |
| Index coverage | Google Search Console | >5% drop                |
| Page load time | VIP monitoring        | >500ms increase         |
| 404 errors     | Server logs           | Unusual spike           |
| Schema errors  | Rich Results Test     | Any validation failures |

### Rollback Procedure

If critical issues are discovered:

1. **Immediate Actions**

    ```bash
    # Deactivate PRC Schema SEO
    wp plugin deactivate prc-schema-seo

    # Reactivate Yoast SEO
    wp plugin activate wordpress-seo
    ```

2. **Cache Clear**

    ```bash
    # Clear object cache
    wp cache flush

    # Clear edge cache (VIP-specific)
    # Follow VIP cache purge procedures
    ```

3. **Verification**
    - Verify Yoast meta tags appearing
    - Check sample pages for correct output
    - Monitor for immediate error reduction

### Post-Rollback Analysis

Document and investigate:

- Which specific posts/pages had issues
- What was different about the output
- Root cause analysis
- Fixes needed before retry

---

## Testing Checklists

### Pre-Migration Checklist

- [ ] Backup database
- [ ] Document current Yoast settings
- [ ] Run `wp prc-seo migration-status`
- [ ] Identify high-traffic posts for priority testing
- [ ] Create comparison baseline with `compare-batch`

### Post-Migration Checklist

- [ ] Verify migration statistics
- [ ] Spot-check 10 random posts
- [ ] Test all post types (post, page, staff, etc.)
- [ ] Verify primary term mappings
- [ ] Check noindex posts maintained status

### Pre-Launch Checklist

- [ ] All automated tests passing
- [ ] Batch comparison 95%+ match rate
- [ ] Performance benchmarks acceptable
- [ ] Manual testing completed
- [ ] Stakeholder sign-off obtained
- [ ] Rollback plan documented
- [ ] Monitoring alerts configured

### Post-Launch Checklist (Day 1)

- [ ] Homepage meta tags verified
- [ ] Recent posts verified
- [ ] Schema validates in Rich Results Test
- [ ] No new crawl errors in Search Console
- [ ] Page load times unchanged
- [ ] No user-reported issues

### Post-Launch Checklist (Week 1)

- [ ] Search Console index coverage stable
- [ ] Click-through rates stable
- [ ] Rankings stable for key terms
- [ ] Social sharing previews correct
- [ ] No ongoing issues reported

---

## Appendix: Quick Reference Commands

### Daily Validation Commands

```bash
# Quick health check
wp prc-seo compare-batch --limit=10

# Check migration status
wp prc-seo migration-status

# Benchmark performance
wp prc-seo benchmark --post=$(wp post list --post_type=post --posts_per_page=1 --field=ID)
```

### Troubleshooting Commands

```bash
# Debug specific post
wp prc-seo compare <post_id> --format=full

# Check raw SEO data
wp post meta get <post_id> _prc_seo_data

# Preview what Yoast had
wp prc-seo preview-yoast <post_id>

# Force cache clear
wp cache delete schema_<post_id> prc_schema_seo_output
wp cache delete meta_tags_<post_id> prc_schema_seo_output
```

### Bulk Operations

```bash
# Compare all published posts of a type
wp post list --post_type=post --post_status=publish --field=ID | while read id; do
  wp prc-seo compare $id --diff-only 2>/dev/null
done

# Find posts with differences
wp prc-seo compare-batch --limit=1000 --format=json | jq '.results[] | select(.status == "partial") | {id: .post_id, title: .post_title}'
```

---

## Document History

| Version | Date       | Changes                  |
| ------- | ---------- | ------------------------ |
| 1.0     | 2024-XX-XX | Initial testing strategy |

---

## Contact

For questions about this testing strategy:

- Plugin maintainer: See plugin README
- PRC Platform team: Internal channels
