# PRC Schema SEO - Data & Rendering Flow

This document describes the data flow and rendering pipeline for the `prc-schema-seo` plugin.

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                    DATA SOURCES                                          │
├─────────────────────┬─────────────────────┬────────────────────┬────────────────────────┤
│   Post Meta         │   Term Meta         │  Template Defaults │   Art Direction        │
│   _prc_seo_data     │ _prc_seo_term_data  │ (wp_options)       │   (artDirection meta)  │
└─────────┬───────────┴─────────┬───────────┴──────────┬─────────┴───────────┬────────────┘
          │                     │                      │                     │
          ▼                     ▼                      ▼                     ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                               DATA PROCESSING LAYER                                      │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│  Template_Context → Detects page context (singular/archive/taxonomy/front-page)         │
│         ↓                                                                                │
│  Metadata         → get_seo_data() retrieves & merges post meta with defaults           │
│         ↓                                                                                │
│  Template_Defaults → Applies pattern tokens (%post_title%, %site_name%, etc.)           │
│         ↓                                                                                │
│  resolve_for_display() → Final resolution with all fallbacks applied                    │
└─────────────────────────────────────────────────────────────────────────────────────────┘
                                         │
          ┌──────────────────────────────┼──────────────────────────────┐
          ▼                              ▼                              ▼
┌──────────────────────┐  ┌─────────────────────────────┐  ┌─────────────────────────┐
│     Meta_Tags        │  │      Generator              │  │      JSON_Output        │
│                      │  │                             │  │                         │
│  • <meta name>       │  │  • WebSite schema           │  │  • wp_head output       │
│  • <meta property>   │  │  • Organization schema      │  │  • Dispatches to        │
│  • <link canonical>  │  │  • Article/Person/WebPage   │  │    Generator methods    │
│  • robots directives │  │  • CollectionPage (terms)   │  │                         │
└──────────┬───────────┘  └──────────────┬──────────────┘  └────────────┬────────────┘
           │                             │                              │
           └─────────────────────────────┼──────────────────────────────┘
                                         ▼
                              ┌─────────────────────┐
                              │    HTML <head>      │
                              │                     │
                              │  • Meta tags        │
                              │  • JSON-LD schema   │
                              │  • Canonical link   │
                              └─────────────────────┘
```

### D. Parse.ly (`wp-parsely`) integration

Parse.ly analytics metadata is supplied by the **`wp-parsely`** plugin. `Parsely_Integration` bridges PRC SEO data into that plugin’s filters:

```
Singular (post types with prc-schema-seo support)
    → wp_parsely_metadata / wp_parsely_permalink
    → Merge headline, canonical url, thumbnail, keywords, articleSection, authors, dates

Front page + term archives (category / tag / tax)
    → wp_parsely_should_insert_metadata = false (avoid duplicate / wrong context)
    → wp_head @ priority 3: explicit parsely-title / parsely-link / parsely-type meta (cached)

Local dev
    → wpvip_parsely_load_mu enables VIP’s Parse.ly MU plugin when environment type is "local"
```

Implementation: `includes/class-parsely-integration.php`. Output format on singular URLs (JSON-LD vs meta tags) is controlled by **Parse.ly plugin settings**, not by `prc-schema-seo`.

## Component Responsibilities

### 1. Plugin Bootstrap (`class-plugin.php`)

```
┌──────────────────────────────────────────────────────────────────┐
│                         Plugin                                    │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  init_dependencies()                                         │ │
│  │                                                              │ │
│  │  ├── Metadata         → SEO data storage/retrieval          │ │
│  │  ├── REST_API         → Editor REST field registration      │ │
│  │  ├── Generator        → Schema.org JSON-LD generation       │ │
│  │  ├── JSON_Output      → wp_head schema output               │ │
│  │  ├── Meta_Tags        → OG/Twitter/robots meta output       │ │
│  │  ├── Template_Defaults→ Per-template SEO patterns           │ │
│  │  ├── Cache_Invalidator→ Cache lifecycle management          │ │
│  │  ├── Parsely_Integration → wp-parsely filters & archive meta │ │
│  │  ├── Editor_UI        → Block editor panel                  │ │
│  │  └── Taxonomy_UI      → Term edit screen UI                 │ │
│  └─────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────┘
```

---

## Detailed Data Flows

### A. Editor Save Flow (Block Editor)

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│                           BLOCK EDITOR DATA FLOW                                      │
└──────────────────────────────────────────────────────────────────────────────────────┘

 ┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
 │  React Panel    │       │  @wordpress/    │       │   REST API      │
 │  (panel/        │──────▶│  core-data      │──────▶│   (WordPress)   │
 │   index.jsx)    │       │  useEntityProp  │       │                 │
 └─────────────────┘       └─────────────────┘       └────────┬────────┘
                                                              │
                           User edits SEO fields              │ POST /wp-json/wp/v2/{post_type}/{id}
                           in sidebar panel                   │ Body: { prc_seo_data: {...} }
                                                              ▼
                                                   ┌─────────────────────┐
                                                   │    REST_API         │
                                                   │    (class)          │
                                                   │                     │
                                                   │  update_seo_data()  │
                                                   │  • Validate fields  │
                                                   │  • Check permissions│
                                                   │  • Validate terms   │
                                                   └──────────┬──────────┘
                                                              │
                                                              ▼
                                                   ┌─────────────────────┐
                                                   │    Metadata         │
                                                   │                     │
                                                   │  update_seo_data()  │
                                                   │  • Sanitize data    │
                                                   │  • update_post_meta │
                                                   │  • clear_cache()    │
                                                   └──────────┬──────────┘
                                                              │
                                                              ▼
                                                   ┌─────────────────────┐
                                                   │  Cache Invalidation │
                                                   │                     │
                                                   │  • seo_data_{id}    │
                                                   │  • schema_{id}      │
                                                   │  • meta_tags_{id}   │
                                                   └─────────────────────┘
```

### B. Site Editor Template Defaults Flow

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│                        SITE EDITOR TEMPLATE DEFAULTS FLOW                             │
└──────────────────────────────────────────────────────────────────────────────────────┘

 ┌─────────────────┐                              ┌─────────────────────┐
 │  Site Editor    │                              │  Settings API       │
 │  Panel          │─────────────────────────────▶│  (register_setting) │
 │  (site-editor/  │  useEntityProp('root',      │                     │
 │   panel/)       │  'site', 'prc_schema_seo_   │  option:            │
 └─────────────────┘   templates')               │  prc_schema_seo_    │
                                                  │  templates          │
        │                                         └──────────┬──────────┘
        │                                                    │
        │  Template Context Detection                        │
        ▼                                                    ▼
 ┌─────────────────┐                              ┌─────────────────────┐
 │ Template_Context│                              │   Template_Defaults │
 │                 │                              │                     │
 │ • parse_        │                              │ Stored structure:   │
 │   template_id() │                              │ {                   │
 │ • get_site_     │                              │   "single_post": {  │
 │   editor_       │                              │     title_pattern,  │
 │   context()     │                              │     description_    │
 │                 │                              │       pattern,      │
 │ Returns:        │                              │     schema_type,    │
 │ {               │                              │     noindex,        │
 │   type,         │                              │     og_image        │
 │   key,          │                              │   },                │
 │   post_type,    │                              │   "single_page": {},│
 │   taxonomy,     │                              │   "taxonomy_       │
 │   label         │                              │     category": {},  │
 │ }               │                              │   ...               │
 └─────────────────┘                              │ }                   │
                                                  └─────────────────────┘
```

### C. Frontend Rendering Flow

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│                           FRONTEND RENDERING FLOW                                     │
└──────────────────────────────────────────────────────────────────────────────────────┘

                          WordPress Request
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │    Template Loaded       │
                    │    (is_singular, etc.)   │
                    └────────────┬────────────┘
                                 │
                     ┌───────────┴───────────────────────────────┐
                     │                                           │
                     ▼                                           ▼
          ┌─────────────────────┐                    ┌─────────────────────┐
          │  wp_head priority 1 │                    │  wp_head priority 2 │
          │  JSON_Output        │                    │  Meta_Tags          │
          └──────────┬──────────┘                    └──────────┬──────────┘
                     │                                          │
                     ▼                                          ▼
          ┌─────────────────────────────────────────────────────────────────┐
          │                     CONTEXT DETECTION                            │
          │  ┌──────────────────────────────────────────────────────────┐   │
          │  │  is_singular() → generate_schema(post_id)                │   │
          │  │  is_category/tag/tax() → generate_term_schema(term_id)   │   │
          │  │  is_home() → generate_publications_page_schema()         │   │
          │  │  is_post_type_archive() → generate_post_type_archive_    │   │
          │  │                           schema(post_type)              │   │
          │  └──────────────────────────────────────────────────────────┘   │
          └───────────────────────────┬─────────────────────────────────────┘
                                      │
              ┌───────────────────────┴───────────────────────┐
              │                                               │
              ▼                                               ▼
   ┌─────────────────────────┐                     ┌─────────────────────────┐
   │      Generator          │                     │      Meta_Tags          │
   │                         │                     │                         │
   │  1. Check cache         │                     │  1. Check cache         │
   │  2. get_seo_data()      │                     │  2. get_seo_data()      │
   │  3. Build @graph array: │                     │  3. resolve_for_        │
   │     • WebSite           │                     │     display()           │
   │     • Organization      │                     │  4. Build meta array:   │
   │     • Article/Person/   │                     │     • description       │
   │       WebPage/          │                     │     • og:title          │
   │       CollectionPage    │                     │     • og:description    │
   │  4. Apply filters       │                     │     • og:image          │
   │  5. Cache result        │                     │     • twitter:card      │
   │  6. Output JSON-LD      │                     │     • robots            │
   │                         │                     │     • canonical         │
   └───────────┬─────────────┘                     │  5. Cache result        │
               │                                   │  6. Output HTML         │
               │                                   └───────────┬─────────────┘
               │                                               │
               ▼                                               ▼
   ┌─────────────────────────────────────────────────────────────────────────┐
   │                           HTML <head>                                    │
   │                                                                          │
   │  <script type="application/ld+json">                                     │
   │  {                                                                       │
   │    "@context": "https://schema.org",                                     │
   │    "@graph": [                                                           │
   │      { "@type": "WebSite", ... },                                        │
   │      { "@type": "Organization", ... },                                   │
   │      { "@type": "Article", ... }                                         │
   │    ]                                                                     │
   │  }                                                                       │
   │  </script>                                                               │
   │                                                                          │
   │  <meta name="description" content="..." />                               │
   │  <meta property="og:title" content="..." />                              │
   │  <meta property="og:description" content="..." />                        │
   │  <meta property="og:image" content="..." />                              │
   │  <meta name="robots" content="index,follow" />                           │
   │  <link rel="canonical" href="..." />                                     │
   └─────────────────────────────────────────────────────────────────────────┘
```

---

## Data Resolution & Fallback Chain

### Metadata Resolution

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│                         SEO DATA RESOLUTION CHAIN                                     │
└──────────────────────────────────────────────────────────────────────────────────────┘

                    ┌─────────────────────────────────────┐
                    │  1. Post/Term Meta (_prc_seo_data)  │
                    │     Explicit user-entered values    │
                    └──────────────┬──────────────────────┘
                                   │ empty?
                                   ▼
                    ┌─────────────────────────────────────┐
                    │  2. Template Defaults               │
                    │     Pattern tokens resolved:        │
                    │     • %post_title% → get_the_title()│
                    │     • %site_name% → bloginfo()      │
                    │     • %primary_category%            │
                    │     • %author%, %year%, etc.        │
                    └──────────────┬──────────────────────┘
                                   │ empty pattern?
                                   ▼
                    ┌─────────────────────────────────────┐
                    │  3. Three-Tier Default Fallback     │
                    │                                     │
                    │  Context: single_post               │
                    │     → default_singular              │
                    │     → default (legacy)              │
                    │                                     │
                    │  Context: taxonomy_category         │
                    │     → default_archive               │
                    │     → default (legacy)              │
                    │                                     │
                    │  Context: front_page                │
                    │     → default_site                  │
                    │     → default (legacy)              │
                    └──────────────┬──────────────────────┘
                                   │ still empty?
                                   ▼
                    ┌─────────────────────────────────────┐
                    │  4. WordPress Defaults              │
                    │                                     │
                    │  title → get_the_title()            │
                    │  description → excerpt or           │
                    │    wp_trim_words(content, 30)       │
                    │  og_image → Art Direction 'social'  │
                    │    → featured image                 │
                    │  canonical → get_permalink()        │
                    └─────────────────────────────────────┘
```

### OG Image Resolution

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│                           OG IMAGE FALLBACK CHAIN                                     │
└──────────────────────────────────────────────────────────────────────────────────────┘

  ┌─────────────────────┐
  │ 1. Custom og_image  │   User-specified in SEO panel
  │    in _prc_seo_data │
  └──────────┬──────────┘
             │ empty?
             ▼
  ┌─────────────────────┐
  │ 2. Art Direction    │   \PRC\Platform\Art_Direction\get($post_id, 'social')
  │    'social' slot    │   Returns cropped social-optimized image
  └──────────┬──────────┘
             │ empty?
             ▼
  ┌─────────────────────┐
  │ 3. Featured Image   │   get_post_thumbnail_id($post_id)
  │    (post_thumbnail) │
  └──────────┬──────────┘
             │ empty?
             ▼
  ┌─────────────────────┐
  │ 4. Template Default │   og_image from template_defaults[context][og_image]
  │    og_image         │
  └──────────┬──────────┘
             │ empty?
             ▼
  ┌─────────────────────┐
  │ 5. No image         │   twitter:card = "summary" (no large image)
  └─────────────────────┘
```

---

## Caching Architecture

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│                              CACHE LAYERS                                             │
└──────────────────────────────────────────────────────────────────────────────────────┘

  ┌─────────────────────────────────────────────────────────────────────────────────┐
  │                            Object Cache (Memcached/Redis)                        │
  │                                                                                  │
  │  Cache Group: prc_schema_seo_data_v1.4.3                                         │
  │  ├── seo_data_{post_id}     → Metadata::get_seo_data() output                    │
  │                                                                                  │
  │  Cache Group: prc_schema_seo_output_v1.4.4                                       │
  │  ├── schema_{post_id}       → Generator::generate_schema() output                │
  │  ├── term_schema_{term_id}  → Generator::generate_term_schema() output           │
  │  ├── post_type_archive_     → Generator::generate_post_type_archive_schema()     │
  │  │   schema_{post_type}                                                          │
  │  ├── publications_page_     → Generator::generate_publications_page_schema()     │
  │  │   schema                                                                      │
  │                                                                                  │
  │  Cache Group: prc_schema_seo_output                                              │
  │  ├── meta_tags_{post_id}    → Meta_Tags::output_meta_tags() HTML                 │
  │  ├── meta_tags_term_{id}_v{ver}_page_{n} → Meta_Tags::output_term_meta_tags()    │
  │  ├── meta_tags_term_version_{id}          → version counter for invalidation    │
  │  └── meta_tags_home_page_{n}              → Meta_Tags::output_home_meta_tags()   │
  │                                                                                  │
  │  TTL: 3600 seconds (1 hour)                                                      │
  └─────────────────────────────────────────────────────────────────────────────────┘
                                       │
                         Cache Invalidation Triggers
                                       │
  ┌────────────────────────────────────┼────────────────────────────────────────────┐
  │                                    │                                             │
  │  ┌──────────────────┐    ┌─────────┴─────────┐    ┌──────────────────┐          │
  │  │   save_post      │    │   deleted_post    │    │ updated_post_meta│          │
  │  │   (update)       │    │   wp_trash_post   │    │ (artDirection)   │          │
  │  └────────┬─────────┘    └─────────┬─────────┘    └────────┬─────────┘          │
  │           │                        │                       │                     │
  │           └────────────────────────┼───────────────────────┘                     │
  │                                    │                                             │
  │                          Cache_Invalidator                                       │
  │                                    │                                             │
  │                      Metadata::clear_cache($post_id)                             │
  │                                    │                                             │
  │           ┌────────────────────────┼────────────────────────┐                    │
  │           │                        │                        │                    │
  │           ▼                        ▼                        ▼                    │
  │  wp_cache_delete          wp_cache_delete          wp_cache_delete               │
  │  ('seo_data_{id}')        ('schema_{id}')          ('meta_tags_{id}')            │
  └──────────────────────────────────────────────────────────────────────────────────┘
```

---

## Schema Type Mapping

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│                         SCHEMA TYPE → OUTPUT MAPPING                                  │
└──────────────────────────────────────────────────────────────────────────────────────┘

  Post Type        Default Schema Type       Generator Method
  ─────────────────────────────────────────────────────────────────────────────────────
  post             Article                   generate_article_schema()
  page             WebPage                   generate_webpage_schema()
  staff            Person                    generate_person_schema()
  short-read       Article                   generate_article_schema()
  fact-sheet       Article                   generate_article_schema()
  decoded          Article                   generate_article_schema()
  event            Event                     (custom handling needed)
  course           Course                    (custom handling needed)
  report           Report                    generate_article_schema() w/ type
  quiz             Quiz                      (custom handling needed)
  dataset          Dataset                   (custom handling needed)

  Archive Types    Schema Type               Generator Method
  ─────────────────────────────────────────────────────────────────────────────────────
  term archive     CollectionPage            generate_term_schema()
  bylines term     Person                    generate_person_schema() or
                                             generate_guest_person_schema()
  post type        CollectionPage            generate_post_type_archive_schema()
    archive
  home/blog        CollectionPage            generate_publications_page_schema()
```

---

## Filter Hooks Reference

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│                              KEY FILTER HOOKS                                         │
└──────────────────────────────────────────────────────────────────────────────────────┘

  DATA MODIFICATION
  ─────────────────────────────────────────────────────────────────────────────────────
  prc_schema_seo_schema_data          Filter final schema array before JSON output
  prc_schema_seo_meta_tags            Filter meta tags array before HTML output
  prc_schema_seo_rest_prepare         Filter SEO data before REST API response
  prc_schema_seo_pattern_tokens       Add custom pattern tokens for templates

  SCHEMA GENERATION
  ─────────────────────────────────────────────────────────────────────────────────────
  prc_schema_seo_organization_schema  Modify Organization schema object
  prc_schema_seo_article_schema       Modify Article schema before output
  prc_schema_seo_person_schema        Modify Person schema before output
  prc_schema_seo_webpage_schema       Modify WebPage schema before output
  prc_schema_seo_term_schema          Modify term CollectionPage schema

  OVERRIDES
  ─────────────────────────────────────────────────────────────────────────────────────
  prc_schema_seo_schema_type_default  Override default schema type per post type
  prc_schema_seo_noindex              Override noindex directive
  prc_schema_seo_title                Override resolved title
  prc_schema_seo_description_fallback Override resolved description
  prc_schema_seo_og_image_fallback    Override OG image fallback
  prc_schema_seo_og_image_url         Override final OG image URL
  prc_schema_seo_canonical_url        Override canonical URL
  prc_schema_seo_should_output_schema Control whether schema outputs

  CONFIGURATION
  ─────────────────────────────────────────────────────────────────────────────────────
  prc_schema_seo_allowed_schema_types Modify allowed schema types list
  prc_schema_seo_template_contexts    Add/modify available template contexts
  prc_schema_seo_primary_term_        Configure which taxonomies support
    taxonomies                        primary term selection
  prc_schema_seo_primary_term_id      Override primary term selection per taxonomy
```

---

## REST API Schema

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│                            REST API FIELD: prc_seo_data                               │
└──────────────────────────────────────────────────────────────────────────────────────┘

  Registered on: All post types with 'prc-schema-seo' support

  GET /wp-json/wp/v2/{post_type}/{id}
  ─────────────────────────────────────────────────────────────────────────────────────
  Response includes:
  {
    "prc_seo_data": {
      "title": string|null,           // Custom SEO title (max 255 chars)
      "description": string|null,     // Meta description (max 500 chars)
      "og_title": string|null,        // Open Graph title
      "og_description": string|null,  // Open Graph description
      "og_image": integer|null,       // Attachment ID
      "schema_type": string,          // Article|NewsArticle|BlogPosting|Report|
                                      // WebPage|Person|Event|Course|Dataset|Quiz
      "noindex": boolean,             // robots noindex directive
      "canonical_url": string,        // Custom canonical URL
      "primary_terms": {              // Primary term per taxonomy
        "category": integer,
        "formats": integer,
        ...
      },
      "custom_schema": object         // Additional schema.org properties
    }
  }

  POST/PUT /wp-json/wp/v2/{post_type}/{id}
  ─────────────────────────────────────────────────────────────────────────────────────
  Request body can include prc_seo_data with any of the above fields.
  Validation:
    • Title/OG title: max 255 characters
    • Description/OG description: max 500 characters
    • og_image: must be valid image attachment
    • schema_type: must be in allowed types list
    • canonical_url: must be valid URL format
    • primary_terms: terms must exist and be assigned to post
```
