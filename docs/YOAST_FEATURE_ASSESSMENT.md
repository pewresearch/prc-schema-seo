# Yoast SEO Feature Assessment

This document assesses which Yoast SEO features are currently implemented in `prc-schema-seo`, which are missing, and recommendations for potential additions.

> **Scope Exclusions:** Sitemaps, Google News sitemaps, and redirects are handled by other plugins and are excluded from this assessment.

---

## Current Features Comparison

### Legend

- ✅ **Implemented** - Feature exists in prc-schema-seo
- 🟡 **Partial** - Feature partially implemented or could be enhanced
- ❌ **Missing** - Feature not implemented
- 🚫 **N/A** - Not applicable or not recommended for this use case

---

## Meta Tags & SEO Basics

| Feature                       | Status | Notes                                       |
| ----------------------------- | ------ | ------------------------------------------- |
| SEO Title customization       | ✅     | Per-post and template-level patterns        |
| Meta description              | ✅     | Per-post and template-level patterns        |
| Canonical URL                 | ✅     | Custom canonical URL support                |
| Robots meta (noindex)         | ✅     | Per-post and template-level                 |
| Robots meta (nofollow)        | ❌     | Only noindex is supported                   |
| Robots meta (noarchive)       | ❌     | Not implemented                             |
| Robots meta (noimageindex)    | ❌     | Not implemented                             |
| Robots meta (nosnippet)       | ❌     | Not implemented                             |
| Open Graph tags               | ✅     | Full OG support including images            |
| Twitter Card tags             | 🟡     | Basic support (summary/summary_large_image) |
| Article meta tags             | ✅     | Published/modified time, author, section    |
| Title separator customization | ❌     | Hardcoded, no UI to customize               |
| Homepage SEO settings         | ✅     | Via template defaults                       |

---

## Social Media Features

| Feature                            | Status | Notes                                  |
| ---------------------------------- | ------ | -------------------------------------- |
| Facebook OG preview                | ❌     | Visual preview in editor not available |
| Twitter preview                    | ❌     | Visual preview in editor not available |
| Separate OG title/description      | ✅     | og_title and og_description fields     |
| OG image selection                 | ✅     | Via Art Direction integration          |
| Twitter image (separate)           | ❌     | Uses same image as OG                  |
| Default social image per post type | 🟡     | Template defaults support og_image     |
| Social site URLs (Organization)    | ✅     | Hardcoded in schema, no UI             |

---

## Schema.org / Structured Data

| Feature                  | Status | Notes                                       |
| ------------------------ | ------ | ------------------------------------------- |
| Organization schema      | ✅     | Comprehensive with contact, address, social |
| WebSite schema           | ✅     | With SearchAction                           |
| Article schema           | ✅     | Article, NewsArticle, BlogPosting, Report   |
| WebPage schema           | ✅     | Full support                                |
| Person schema            | ✅     | For staff pages and bylines                 |
| BreadcrumbList schema    | ✅     | Generated for articles                      |
| CollectionPage schema    | ✅     | For archives and taxonomy pages             |
| FAQ schema               | ❌     | Not implemented                             |
| HowTo schema             | ❌     | Not implemented                             |
| LocalBusiness schema     | 🚫     | Not applicable for PRC                      |
| Product schema           | 🚫     | Not applicable for PRC                      |
| Recipe schema            | 🚫     | Not applicable for PRC                      |
| Video schema             | ❌     | Not implemented (could be useful)           |
| Event schema             | 🟡     | Type exists but no dedicated generator      |
| Course schema            | 🟡     | Type exists but no dedicated generator      |
| Dataset schema           | 🟡     | Type exists but no dedicated generator      |
| Custom schema properties | ✅     | Via custom_schema field                     |

---

## Content Analysis & Optimization

| Feature                       | Status | Notes                     |
| ----------------------------- | ------ | ------------------------- |
| Focus keyphrase field         | ❌     | **Priority: Medium-High** |
| Keyphrase analysis            | ❌     | No SEO analysis engine    |
| Keyphrase in title check      | ❌     | Not implemented           |
| Keyphrase in meta description | ❌     | Not implemented           |
| Keyphrase in URL              | ❌     | Not implemented           |
| Keyphrase in headings         | ❌     | Not implemented           |
| Keyphrase density             | ❌     | Not implemented           |
| Related keyphrases            | ❌     | Premium Yoast feature     |
| SEO score indicator           | ❌     | No visual scoring         |
| Content length analysis       | ❌     | Not implemented           |
| Image alt text analysis       | ❌     | Not implemented           |
| Internal link suggestions     | ❌     | Not implemented           |
| Outbound links check          | ❌     | Not implemented           |

---

## Readability Analysis

| Feature                   | Status | Notes                 |
| ------------------------- | ------ | --------------------- |
| Flesch Reading Ease       | ❌     | No readability engine |
| Sentence length analysis  | ❌     | Not implemented       |
| Paragraph length analysis | ❌     | Not implemented       |
| Passive voice detection   | ❌     | Not implemented       |
| Transition words check    | ❌     | Not implemented       |
| Subheading distribution   | ❌     | Not implemented       |
| Readability score         | ❌     | Not implemented       |

---

## Search Appearance & Previews

| Feature                         | Status | Notes                |
| ------------------------------- | ------ | -------------------- |
| Google SERP preview             | ❌     | **Priority: High**   |
| Mobile SERP preview             | ❌     | Not implemented      |
| Character count for title       | ✅     | 255 char limit shown |
| Character count for description | ✅     | 500 char limit shown |
| Pixel width preview             | ❌     | Not implemented      |
| Snippet preview editing         | ❌     | No live preview      |

---

## Taxonomy & Archive SEO

| Feature                   | Status | Notes                       |
| ------------------------- | ------ | --------------------------- |
| Category SEO title        | ✅     | Term meta support           |
| Category meta description | ✅     | Term meta support           |
| Tag SEO settings          | ✅     | Term meta support           |
| Custom taxonomy SEO       | ✅     | Extendable via filter       |
| Post type archive SEO     | ✅     | Template defaults           |
| Author archive settings   | 🟡     | Basic support via templates |
| Date archive settings     | 🟡     | Basic support via templates |
| Author archive disable    | ❌     | No toggle to disable        |
| Date archive disable      | ❌     | No toggle to disable        |

---

## Primary Terms & Categories

| Feature                    | Status | Notes                   |
| -------------------------- | ------ | ----------------------- |
| Primary category selection | ✅     | Full support            |
| Primary term per taxonomy  | ✅     | Configurable via filter |
| Breadcrumb integration     | ✅     | Uses primary term       |

---

## Technical SEO

| Feature                      | Status | Notes                               |
| ---------------------------- | ------ | ----------------------------------- |
| Attachment pages handling    | ❌     | No redirect/noindex for attachments |
| Duplicate content prevention | ❌     | No media page handling              |
| Clean permalinks             | 🚫     | VIP handles this                    |
| Remove replytocom            | ❌     | Not implemented                     |
| Robots.txt editing           | 🚫     | VIP restriction                     |
| .htaccess editing            | 🚫     | VIP restriction                     |

---

## Admin & Workflow Features

| Feature                       | Status | Notes                       |
| ----------------------------- | ------ | --------------------------- |
| SEO column in posts list      | ❌     | **Priority: Medium**        |
| Readability column            | ❌     | Requires readability engine |
| Focus keyphrase column        | ❌     | Requires keyphrase field    |
| Bulk title/description editor | ❌     | **Priority: Medium**        |
| Cornerstone content marking   | ❌     | Not implemented             |
| Orphaned content detection    | ❌     | Not implemented             |
| Text link counter             | ❌     | Not implemented             |

---

## Webmaster Tools Integration

| Feature                            | Status | Notes                                 |
| ---------------------------------- | ------ | ------------------------------------- |
| Google Search Console verification | ❌     | **Priority: Low** (usually done once) |
| Bing Webmaster verification        | ❌     | Usually done once                     |
| Pinterest verification             | ❌     | Usually done once                     |
| Yandex verification                | 🚫     | Not needed for PRC                    |
| Baidu verification                 | 🚫     | Not needed for PRC                    |

---

## Import/Export & Migration

| Feature                 | Status | Notes                  |
| ----------------------- | ------ | ---------------------- |
| Yoast SEO migration     | ✅     | Full migration support |
| Settings import/export  | ❌     | No settings backup     |
| Other SEO plugin import | ❌     | Only Yoast supported   |

---

## RSS & Feed Optimization

| Feature                  | Status | Notes           |
| ------------------------ | ------ | --------------- |
| Content before RSS items | ❌     | Not implemented |
| Content after RSS items  | ❌     | Not implemented |

---

## Performance & Caching

| Feature            | Status | Notes                     |
| ------------------ | ------ | ------------------------- |
| Object caching     | ✅     | Full cache implementation |
| Cache invalidation | ✅     | On content updates        |
| Benchmarking tools | ✅     | WP-CLI benchmarking       |

---

## Recommended Feature Additions

### High Priority

These features would provide the most value for content editors:

#### 1. Google SERP Preview

**Effort: Medium | Impact: High**

Add a visual preview showing how the post will appear in Google search results:

- Desktop preview
- Mobile preview
- Character/pixel width indicators
- Live update as title/description changes

```jsx
// Conceptual component structure
<SerpPreview
	title={seoData.title || post.title}
	description={seoData.description || post.excerpt}
	url={post.permalink}
	isMobile={false}
/>
```

#### 2. Social Media Previews

**Effort: Medium | Impact: High**

Visual previews for Facebook, Twitter, and LinkedIn:

- Show how content will appear when shared
- Preview OG image with proper cropping
- Character limits visualization

#### 3. Focus Keyphrase Field

**Effort: Low | Impact: Medium-High**

Add a simple focus keyphrase field that:

- Stores the target keyword for the content
- Displays in editor sidebar
- Could be used for future analysis features

```php
// Add to SEO data structure
'focus_keyphrase' => array(
    'type'        => 'string',
    'description' => 'Target keyphrase for this content.',
),
```

### Medium Priority

#### 4. SEO/Readability Score Columns

**Effort: Medium | Impact: Medium**

Add columns to post list tables showing:

- Basic SEO completeness (title, description, keyphrase)
- Content length indicator
- Quick-edit access

#### 5. Bulk Editor

**Effort: Medium-High | Impact: Medium**

Bulk editing interface for:

- SEO titles
- Meta descriptions
- Focus keyphrases
- Noindex status

#### 6. Additional Robot Meta Options

**Effort: Low | Impact: Low-Medium**

Add additional robots directives:

- nofollow (for links on page)
- noarchive (prevent cached versions)
- noimageindex (prevent image indexing)

```php
'robots_advanced' => array(
    'nofollow'      => false,
    'noarchive'     => false,
    'noimageindex'  => false,
    'nosnippet'     => false,
),
```

#### 7. Title Separator Customization

**Effort: Low | Impact: Low**

Allow customization of the title separator character:

- Default: `|`
- Options: `-`, `–`, `—`, `•`, `»`

### Lower Priority (Nice to Have)

#### 8. Video Schema Support

**Effort: Medium | Impact: Low-Medium**

Detect embedded videos and generate VideoObject schema:

- YouTube embeds
- Vimeo embeds
- Self-hosted videos
- Video duration, thumbnail, description

#### 9. FAQ Schema Block

**Effort: Medium | Impact: Low-Medium**

Create a FAQ block that:

- Generates proper FAQPage schema
- Provides structured input for Q&A pairs
- Outputs accessible HTML markup

#### 10. Basic Content Analysis

**Effort: High | Impact: Medium**

Lightweight content analysis without the full Yoast engine:

- Word count
- Heading structure
- Image count
- Link count (internal vs external)

This would require significant JavaScript work but could provide value without the full SEO scoring complexity.

---

## Features NOT Recommended

These Yoast features are not recommended for implementation:

| Feature                    | Reason                                                              |
| -------------------------- | ------------------------------------------------------------------- |
| Full readability analysis  | High complexity, marginal value for research content                |
| Full SEO scoring engine    | Maintenance burden, Pew content doesn't follow typical SEO patterns |
| LocalBusiness schema       | Not applicable to PRC                                               |
| Product/Recipe schema      | Not applicable to PRC                                               |
| Robots.txt/htaccess editor | VIP restrictions                                                    |
| Yandex/Baidu verification  | Not needed for target audience                                      |

---

## Implementation Roadmap Suggestion

### Phase 1: Visual Previews

1. Google SERP preview component
2. Facebook/Twitter share previews
3. Mobile preview toggle

### Phase 2: Enhanced Metadata

1. Focus keyphrase field
2. Additional robots directives
3. Title separator option
4. Separate Twitter image support

### Phase 3: Admin Improvements

1. SEO columns in post list
2. Bulk editor for SEO fields
3. Settings export/import

### Phase 4: Advanced Schema

1. Video schema detection
2. FAQ schema block
3. Event/Course dedicated generators

---

## Current Strengths

The `prc-schema-seo` plugin already excels in several areas:

1. **Schema.org Implementation** - Comprehensive and well-structured
2. **Template Defaults** - Powerful pattern system with token support
3. **Caching Strategy** - Production-ready with VIP optimization
4. **Primary Terms** - Full support with multiple taxonomies
5. **Migration Tools** - Complete Yoast migration path
6. **REST API** - Full integration with block editor
7. **Extensibility** - Extensive filter hooks for customization

---

## Conclusion

The `prc-schema-seo` plugin covers the core SEO functionality well. The most impactful additions would be:

1. **Visual previews** (SERP & Social) - Helps editors visualize how content appears
2. **Focus keyphrase field** - Simple addition with future expansion potential
3. **Admin columns** - Improves workflow efficiency

The full content analysis and readability scoring from Yoast represents significant complexity that may not be worth the implementation effort, especially given that research content doesn't typically follow the same SEO patterns as commercial content.
