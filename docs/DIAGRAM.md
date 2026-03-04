# PRC Schema SEO - Mermaid Diagrams

Visual diagrams of the data and rendering flow for the `prc-schema-seo` plugin.

## High-Level Architecture

```mermaid
flowchart TB
    subgraph DataSources["Data Sources"]
        PM[("Post Meta<br/>_prc_seo_data")]
        TM[("Term Meta<br/>_prc_seo_term_data")]
        TD[("Template Defaults<br/>wp_options")]
        AD[("Art Direction<br/>artDirection meta")]
    end

    subgraph Processing["Data Processing Layer"]
        TC[Template_Context]
        MD[Metadata]
        TDF[Template_Defaults]
        RFD[resolve_for_display]
    end

    subgraph Output["Output Layer"]
        MT[Meta_Tags]
        GEN[Generator]
        JO[JSON_Output]
    end

    subgraph HTML["HTML &lt;head&gt;"]
        META["Meta Tags<br/>(OG, Twitter, robots)"]
        JSONLD["JSON-LD Schema"]
        CANON["Canonical Link"]
    end

    PM --> MD
    TM --> MD
    TD --> TDF
    AD --> MD

    TC --> MD
    MD --> TDF
    TDF --> RFD

    RFD --> MT
    RFD --> GEN
    GEN --> JO

    MT --> META
    MT --> CANON
    JO --> JSONLD
```

## Plugin Bootstrap

```mermaid
flowchart LR
    subgraph Plugin["Plugin Class"]
        LD[Loader]
    end

    subgraph Components["Initialized Components"]
        MD[Metadata]
        REST[REST_API]
        GEN[Generator]
        JO[JSON_Output]
        MT[Meta_Tags]
        TD[Template_Defaults]
        CI[Cache_Invalidator]
        EU[Editor_UI]
        TU[Taxonomy_UI]
    end

    LD --> MD
    LD --> REST
    LD --> GEN
    LD --> JO
    LD --> MT
    LD --> TD
    LD --> CI
    LD --> EU
    LD --> TU
```

## Block Editor Data Flow

```mermaid
sequenceDiagram
    participant UI as React Panel<br/>(panel/index.jsx)
    participant CD as @wordpress/core-data<br/>useEntityProp
    participant WP as WordPress REST API
    participant REST as REST_API Class
    participant META as Metadata Class
    participant CACHE as Object Cache

    UI->>CD: Update SEO field
    CD->>WP: POST /wp-json/wp/v2/{type}/{id}
    WP->>REST: update_seo_data()

    REST->>REST: validate_seo_data()
    REST->>REST: Check permissions
    REST->>REST: Validate primary terms

    REST->>META: update_seo_data()
    META->>META: sanitize_seo_data()
    META->>WP: update_post_meta()
    META->>CACHE: clear_cache()

    CACHE-->>CACHE: Delete seo_data_{id}
    CACHE-->>CACHE: Delete schema_{id}
    CACHE-->>CACHE: Delete meta_tags_{id}

    META-->>REST: Success
    REST-->>WP: true
    WP-->>CD: Updated response
    CD-->>UI: Re-render
```

## Site Editor Template Defaults Flow

```mermaid
flowchart TB
    subgraph SiteEditor["Site Editor"]
        Panel[Site Editor Panel]
        Hook[useEntityProp<br/>'root', 'site']
    end

    subgraph Detection["Context Detection"]
        TC[Template_Context]
        Parse[parse_template_id]
        GetCtx[get_site_editor_context]
    end

    subgraph Storage["Storage"]
        Settings[Settings API<br/>register_setting]
        Option[("wp_options<br/>prc_schema_seo_templates")]
    end

    subgraph Structure["Stored Structure"]
        SP["single_post: {...}"]
        SPG["single_page: {...}"]
        TC2["taxonomy_category: {...}"]
        DS["default_singular: {...}"]
        DA["default_archive: {...}"]
    end

    Panel --> Hook
    Hook --> Settings
    Settings --> Option

    TC --> Parse
    Parse --> GetCtx
    GetCtx --> |"Returns context key"| Option

    Option --> SP
    Option --> SPG
    Option --> TC2
    Option --> DS
    Option --> DA
```

## Frontend Rendering Flow

```mermaid
flowchart TB
    REQ[WordPress Request] --> TL[Template Loaded]

    TL --> CHECK{Context Type?}

    CHECK --> |is_singular| SING[Singular Post]
    CHECK --> |is_category/tag/tax| TERM[Term Archive]
    CHECK --> |is_home| HOME[Home/Publications]
    CHECK --> |is_post_type_archive| PTA[Post Type Archive]

    subgraph WPHead["wp_head Output"]
        JO["JSON_Output<br/>(priority 1)"]
        MT["Meta_Tags<br/>(priority 2)"]
    end

    SING --> JO
    SING --> MT
    TERM --> JO
    TERM --> MT
    HOME --> JO
    PTA --> JO

    subgraph Generator["Generator Methods"]
        GS[generate_schema]
        GTS[generate_term_schema]
        GPS[generate_publications_page_schema]
        GPTA[generate_post_type_archive_schema]
    end

    JO --> |singular| GS
    JO --> |term| GTS
    JO --> |home| GPS
    JO --> |archive| GPTA

    subgraph Schema["Schema @graph"]
        WS[WebSite]
        ORG[Organization]
        ART[Article/Person/WebPage]
        COL[CollectionPage]
    end

    GS --> WS
    GS --> ORG
    GS --> ART

    GTS --> WS
    GTS --> ORG
    GTS --> COL

    GPS --> WS
    GPS --> ORG
    GPS --> COL

    GPTA --> WS
    GPTA --> ORG
    GPTA --> COL

    subgraph MetaTags["Meta Tags Output"]
        DESC["&lt;meta name='description'&gt;"]
        OGT["&lt;meta property='og:title'&gt;"]
        OGD["&lt;meta property='og:description'&gt;"]
        OGI["&lt;meta property='og:image'&gt;"]
        ROB["&lt;meta name='robots'&gt;"]
        CAN["&lt;link rel='canonical'&gt;"]
    end

    MT --> DESC
    MT --> OGT
    MT --> OGD
    MT --> OGI
    MT --> ROB
    MT --> CAN
```

## SEO Data Resolution Chain

```mermaid
flowchart TB
    subgraph L1["Layer 1: Explicit Values"]
        PM["Post/Term Meta<br/>(_prc_seo_data)"]
    end

    subgraph L2["Layer 2: Template Patterns"]
        TP["Template Defaults<br/>with token resolution"]
        T1["%post_title%"]
        T2["%site_name%"]
        T3["%primary_category%"]
        T4["%author%"]
    end

    subgraph L3["Layer 3: Three-Tier Defaults"]
        DS["default_singular"]
        DA["default_archive"]
        DST["default_site"]
        DL["default (legacy)"]
    end

    subgraph L4["Layer 4: WordPress Defaults"]
        WPT["get_the_title()"]
        WPE["excerpt or wp_trim_words()"]
        WPI["Art Direction 'social'<br/>or featured image"]
        WPC["get_permalink()"]
    end

    PM --> |empty?| TP
    TP --> T1
    TP --> T2
    TP --> T3
    TP --> T4

    TP --> |empty pattern?| L3
    L3 --> DS
    L3 --> DA
    L3 --> DST
    DS --> DL
    DA --> DL
    DST --> DL

    L3 --> |still empty?| L4
    L4 --> WPT
    L4 --> WPE
    L4 --> WPI
    L4 --> WPC
```

## OG Image Fallback Chain

```mermaid
flowchart TB
    START[OG Image Resolution] --> C1{Custom og_image<br/>in _prc_seo_data?}

    C1 --> |Yes| USE1[Use custom image]
    C1 --> |No| C2{Art Direction<br/>'social' slot?}

    C2 --> |Yes| USE2[Use social art image]
    C2 --> |No| C3{Featured Image<br/>(post_thumbnail)?}

    C3 --> |Yes| USE3[Use featured image]
    C3 --> |No| C4{Template Default<br/>og_image?}

    C4 --> |Yes| USE4[Use template default]
    C4 --> |No| NONE["No image<br/>twitter:card = 'summary'"]

    USE1 --> OUTPUT[Output og:image]
    USE2 --> OUTPUT
    USE3 --> OUTPUT
    USE4 --> OUTPUT
```

## Caching Architecture

```mermaid
flowchart TB
    subgraph CacheGroups["Object Cache Groups"]
        subgraph G1["prc_schema_seo_data_v1.4.3"]
            SD["seo_data_{post_id}"]
        end

        subgraph G2["prc_schema_seo_output_v1.4.4"]
            SCH["schema_{post_id}"]
            TS["term_schema_{term_id}"]
            PTA["post_type_archive_schema_{type}"]
            PPS["publications_page_schema"]
        end

        subgraph G3["prc_schema_seo_output"]
            MT["meta_tags_{post_id}"]
            MTT["meta_tags_term_{term_id}"]
        end
    end

    subgraph Triggers["Cache Invalidation Triggers"]
        SP[save_post]
        DP[deleted_post]
        TP[wp_trash_post]
        UPM["updated_post_meta<br/>(artDirection)"]
    end

    subgraph Invalidator["Cache_Invalidator"]
        CI[maybe_clear_cache]
        COD[clear_cache_on_delete]
        CAD[maybe_clear_cache_on_art_direction_update]
    end

    SP --> CI
    DP --> COD
    TP --> COD
    UPM --> CAD

    CI --> |clear| SD
    CI --> |clear| SCH
    CI --> |clear| MT

    COD --> |clear| SD
    COD --> |clear| SCH
    COD --> |clear| MT

    CAD --> |clear| SD
    CAD --> |clear| SCH
    CAD --> |clear| MT
```

## Schema Type Mapping

```mermaid
flowchart LR
    subgraph PostTypes["Post Types"]
        post[post]
        page[page]
        staff[staff]
        shortread[short-read]
        factsheet[fact-sheet]
        decoded[decoded]
        report[report]
    end

    subgraph SchemaTypes["Schema.org Types"]
        Article[Article]
        WebPage[WebPage]
        Person[Person]
        Report[Report]
    end

    subgraph Methods["Generator Methods"]
        GAS[generate_article_schema]
        GWS[generate_webpage_schema]
        GPS[generate_person_schema]
    end

    post --> Article
    shortread --> Article
    factsheet --> Article
    decoded --> Article
    report --> Report

    page --> WebPage
    staff --> Person

    Article --> GAS
    Report --> GAS
    WebPage --> GWS
    Person --> GPS
```

## Archive Schema Types

```mermaid
flowchart LR
    subgraph ArchiveTypes["Archive Types"]
        term[Term Archive]
        byline[Bylines Term]
        pta[Post Type Archive]
        home[Home/Blog]
    end

    subgraph SchemaTypes["Schema.org Types"]
        CP[CollectionPage]
        P[Person]
    end

    subgraph Methods["Generator Methods"]
        GTS[generate_term_schema]
        GPS[generate_person_schema]
        GGPS[generate_guest_person_schema]
        GPTA[generate_post_type_archive_schema]
        GPPS[generate_publications_page_schema]
    end

    term --> CP
    byline --> P
    pta --> CP
    home --> CP

    CP --> GTS
    CP --> GPTA
    CP --> GPPS
    P --> GPS
    P --> GGPS
```

## REST API Flow

```mermaid
flowchart TB
    subgraph Request["REST API Request"]
        GET["GET /wp-json/wp/v2/{type}/{id}"]
        POST["POST /wp-json/wp/v2/{type}/{id}"]
    end

    subgraph RESTClass["REST_API Class"]
        GSD[get_seo_data]
        USD[update_seo_data]
        VSD[validate_seo_data]
    end

    subgraph Response["Response Field: prc_seo_data"]
        title["title: string"]
        desc["description: string"]
        ogt["og_title: string"]
        ogd["og_description: string"]
        ogi["og_image: integer"]
        st["schema_type: string"]
        ni["noindex: boolean"]
        cu["canonical_url: string"]
        pt["primary_terms: object"]
        cs["custom_schema: object"]
    end

    GET --> GSD
    GSD --> Response

    POST --> USD
    USD --> VSD
    VSD --> |valid| USD
    USD --> Response
```

## Complete Data Flow Overview

```mermaid
flowchart TB
    subgraph Editors["Editor Interfaces"]
        BE[Block Editor<br/>Panel]
        SE[Site Editor<br/>Panel]
        TE[Taxonomy<br/>Edit Screen]
    end

    subgraph REST["REST Layer"]
        RESTAPI[REST_API]
        SETTINGS[Settings API]
    end

    subgraph Storage["Data Storage"]
        POSTMETA[("Post Meta<br/>_prc_seo_data")]
        TERMMETA[("Term Meta<br/>_prc_seo_term_data")]
        OPTIONS[("Options<br/>prc_schema_seo_templates")]
    end

    subgraph Processing["Processing"]
        TC[Template_Context]
        META[Metadata]
        TD[Template_Defaults]
    end

    subgraph Cache["Cache Layer"]
        OC[(Object Cache)]
    end

    subgraph Output["Frontend Output"]
        MT[Meta_Tags]
        GEN[Generator]
        JO[JSON_Output]
    end

    subgraph Head["HTML Head"]
        METATAGS["Meta Tags"]
        JSONLD["JSON-LD"]
    end

    BE --> RESTAPI
    SE --> SETTINGS
    TE --> TERMMETA

    RESTAPI --> POSTMETA
    SETTINGS --> OPTIONS

    POSTMETA --> META
    TERMMETA --> META
    OPTIONS --> TD

    TC --> META
    META --> TD
    TD --> |resolve patterns| META

    META --> OC
    OC --> MT
    OC --> GEN

    GEN --> JO
    MT --> METATAGS
    JO --> JSONLD
```
