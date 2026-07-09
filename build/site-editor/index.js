(()=>{"use strict";var e={n:t=>{var a=t&&t.__esModule?()=>t.default:()=>t;return e.d(a,{a}),a},d:(t,a)=>{for(var n in a)e.o(a,n)&&!e.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:a[n]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.wp.i18n,a=window.wp.commands,n=window.wp.data,o=window.wp.editSite,i=window.wp.primitives,s=window.ReactJSXRuntime;var r=(0,s.jsx)(i.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,s.jsx)(i.Path,{d:"M12 4c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8Zm6.5 8c0 .6 0 1.2-.2 1.8h-2.7c0-.6.2-1.1.2-1.8s0-1.2-.2-1.8h2.7c.2.6.2 1.1.2 1.8Zm-.9-3.2h-2.4c-.3-.9-.7-1.8-1.1-2.4-.1-.2-.2-.4-.3-.5 1.6.5 3 1.6 3.8 3ZM12.8 17c-.3.5-.6 1-.8 1.3-.2-.3-.5-.8-.8-1.3-.3-.5-.6-1.1-.8-1.7h3.3c-.2.6-.5 1.2-.8 1.7Zm-2.9-3.2c-.1-.6-.2-1.1-.2-1.8s0-1.2.2-1.8H14c.1.6.2 1.1.2 1.8s0 1.2-.2 1.8H9.9ZM11.2 7c.3-.5.6-1 .8-1.3.2.3.5.8.8 1.3.3.5.6 1.1.8 1.7h-3.3c.2-.6.5-1.2.8-1.7Zm-1-1.2c-.1.2-.2.3-.3.5-.4.7-.8 1.5-1.1 2.4H6.4c.8-1.4 2.2-2.5 3.8-3Zm-1.8 8H5.7c-.2-.6-.2-1.1-.2-1.8s0-1.2.2-1.8h2.7c0 .6-.2 1.1-.2 1.8s0 1.2.2 1.8Zm-2 1.4h2.4c.3.9.7 1.8 1.1 2.4.1.2.2.4.3.5-1.6-.5-3-1.6-3.8-3Zm7.4 3c.1-.2.2-.3.3-.5.4-.7.8-1.5 1.1-2.4h2.4c-.8 1.4-2.2 2.5-3.8 3Z"})});const l=window.wp.plugins,c=window.wp.editor,p=window.prcIcons;function d(){return(0,s.jsx)(p.Icon,{icon:"globe-pointer",library:"regular",size:1.1})}function h({children:e,id:a}){return(0,s.jsxs)(s.Fragment,{children:[(0,s.jsx)(c.PluginSidebarMoreMenuItem,{target:a,icon:null,children:(0,t.__)("Search and Social","prc-schema-seo")}),(0,s.jsx)(c.PluginSidebar,{name:a,title:(0,t.__)("Search and Social","prc-schema-seo"),icon:(0,s.jsx)(d,{}),children:e})]})}Object.defineProperty(d,"name",{value:"default",configurable:!0});const _=window.wp.element,m=window.wp.components,u=window.emotionStyled;var g=e.n(u);function x(e){if("404"===e)return{type:"404",key:"404",label:(0,t.__)("404 Error Page","prc-schema-seo"),post_type:"",taxonomy:""};if("search"===e)return{type:"search",key:"search",label:(0,t.__)("Search Results","prc-schema-seo"),post_type:"",taxonomy:""};if("front-page"===e||"frontpage"===e)return{type:"front_page",key:"front_page",label:(0,t.__)("Front Page","prc-schema-seo"),post_type:"",taxonomy:""};if("home"===e||"index"===e)return{type:"blog",key:"blog",label:(0,t.__)("Blog","prc-schema-seo"),post_type:"post",taxonomy:""};if("attachment"===e)return{type:"attachment",key:"attachment",label:(0,t.__)("Attachment","prc-schema-seo"),post_type:"attachment",taxonomy:""};if("author"===e)return{type:"author",key:"author",label:(0,t.__)("Author Archive","prc-schema-seo"),post_type:"",taxonomy:""};if("date"===e)return{type:"date",key:"date",label:(0,t.__)("Date Archive","prc-schema-seo"),post_type:"",taxonomy:""};if("archive"===e)return{type:"post_type_archive",key:"post_type_archive_post",label:(0,t.__)("Archive","prc-schema-seo"),post_type:"post",taxonomy:""};const a=e.match(/^archive-(.+)$/);if(a){const e=a[1];return{type:"post_type_archive",key:`post_type_archive_${e}`,label:(0,t.sprintf)("%s Archive",e),post_type:e,taxonomy:""}}const n=e.match(/^single-(.+)$/);if(n){const e=n[1];return{type:"single_post_type",key:`single_${e}`,label:(0,t.sprintf)("Single %s",e),post_type:e,taxonomy:""}}if("single"===e)return{type:"single_post_type",key:"single_post",label:(0,t.__)("Single Post","prc-schema-seo"),post_type:"post",taxonomy:""};if("page"===e)return{type:"single_post_type",key:"single_page",label:(0,t.__)("Single Page","prc-schema-seo"),post_type:"page",taxonomy:""};const o=e.match(/^taxonomy-(.+)$/);if(o){const e=o[1];return{type:"taxonomy",key:`taxonomy_${e}`,label:(0,t.sprintf)("%s Archive",e),post_type:"",taxonomy:e}}return"category"===e?{type:"taxonomy",key:"taxonomy_category",label:(0,t.__)("Category Archive","prc-schema-seo"),post_type:"",taxonomy:"category"}:"tag"===e?{type:"taxonomy",key:"taxonomy_post_tag",label:(0,t.__)("Tag Archive","prc-schema-seo"),post_type:"",taxonomy:"post_tag"}:{type:"unknown",key:null,label:(0,t.sprintf)("Template: %s",e),post_type:"",taxonomy:""}}const f=g().div`
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px;
	padding: 8px 10px;
	background: #f0f0f0;
	border: 1px solid #ddd;
	border-radius: 4px;
	min-height: 32px;
	line-height: 1.4;
`,y=g().span`
	color: #1e1e1e;
	font-size: 13px;
	white-space: pre-wrap;
	word-break: break-word;
`,b=g().span`
	display: inline-flex;
	align-items: center;
	padding: 2px 8px;
	background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
	color: white;
	font-size: 12px;
	font-weight: 500;
	border-radius: 12px;
	cursor: pointer;
	transition: all 0.15s ease;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);

	&:hover {
		background: linear-gradient(135deg, #1a5a93 0%, #0e4a75 100%);
		transform: scale(1.02);
	}

	&:focus {
		outline: 2px solid #2271b1;
		outline-offset: 2px;
	}
`,v=g().div`
	width: 100%;
`,w=g().input`
	width: 100%;
	padding: 8px 10px;
	font-size: 13px;
	line-height: 1.4;
	border: 1px solid #8c8f94;
	border-radius: 4px;
	font-family: inherit;

	&:focus {
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
		outline: none;
	}
`,j=g().textarea`
	width: 100%;
	padding: 8px 10px;
	font-size: 13px;
	line-height: 1.4;
	border: 1px solid #8c8f94;
	border-radius: 4px;
	font-family: inherit;
	resize: vertical;
	min-height: 80px;

	&:focus {
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
		outline: none;
	}
`,k=g().div`
	display: flex;
	gap: 8px;
`,S=g().div`
	padding: 12px;
	min-width: 280px;
	max-width: 400px;
`,C=g().div`
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #757575;
	margin-bottom: 10px;
	padding-bottom: 8px;
	border-bottom: 1px solid #e0e0e0;
`,T=g().div`
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
`,D=g()(m.Button)`
	&.components-button {
		padding: 4px 10px !important;
		height: auto !important;
		font-size: 12px !important;
		font-weight: 500 !important;
		border-radius: 14px !important;
		background: #f0f0f0 !important;
		border: 1px solid #ddd !important;
		color: #1e1e1e !important;
		transition: all 0.15s ease !important;

		&:hover {
			background: #2271b1 !important;
			border-color: #2271b1 !important;
			color: white !important;
			transform: translateY(-1px);
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
		}

		&:focus {
			box-shadow: 0 0 0 2px #fff, 0 0 0 4px #2271b1 !important;
		}
	}
`,P=g()(m.BaseControl)`
	margin-bottom: 16px;

	.components-base-control__label {
		display: block;
		margin-bottom: 8px;
		font-weight: 500;
	}
`;function E({token:e,onClick:t}){return(0,s.jsx)(D,{variant:"secondary",size:"small",onClick:()=>t(e),"aria-label":`Insert ${e}`,children:e})}function A({label:e,help:a,value:n="",onChange:o,availableTokens:i=[],multiline:r=!1,rows:l=3}){const[c,p]=(0,_.useState)(!1),d=(0,_.useRef)(null),h=(0,_.useRef)(null),u=(0,_.useMemo)(()=>function(e){if(!e)return[];const t=[],a=/%[a-z_:]+%/gi;let n,o=0;for(;null!==(n=a.exec(e));)n.index>o&&t.push({type:"text",value:e.slice(o,n.index)}),t.push({type:"token",value:n[0]}),o=a.lastIndex;return o<e.length&&t.push({type:"text",value:e.slice(o)}),t}(n),[n]),g=(0,_.useCallback)(e=>{const t=d.current;if(t){const a=t.selectionStart||n.length,i=t.selectionEnd||n.length,s=n.slice(0,a)+e+n.slice(i);o(s),setTimeout(()=>{const n=a+e.length;t.setSelectionRange(n,n),t.focus()},0)}else o(n+e);p(!1)},[n,o]),x=(0,_.useCallback)(e=>{const t=n.replace(e,"");o(t)},[n,o]),D=r?j:w;return(0,s.jsx)(P,{label:e,help:a,children:(0,s.jsxs)(m.__experimentalVStack,{spacing:2,children:[u.length>0&&(0,s.jsx)(f,{children:u.map((e,a)=>"token"===e.type?(0,s.jsx)(b,{onClick:()=>x(e.value),onKeyDown:t=>{"Enter"!==t.key&&" "!==t.key||x(e.value)},role:"button",tabIndex:0,title:(0,t.__)("Click to remove","prc-schema-seo"),children:e.value},a):(0,s.jsx)(y,{children:e.value},a))}),(0,s.jsx)(v,{children:(0,s.jsx)(D,{ref:d,value:n,onChange:e=>o(e.target.value),rows:r?l:void 0})}),i.length>0&&(0,s.jsxs)(k,{children:[(0,s.jsx)(m.Button,{ref:h,variant:"secondary",size:"small",onClick:()=>p(!c),"aria-expanded":c,children:(0,t.__)("Insert variable","prc-schema-seo")}),c&&(0,s.jsx)(m.Popover,{anchor:h.current,placement:"bottom-start",onClose:()=>p(!1),children:(0,s.jsxs)(S,{children:[(0,s.jsx)(C,{children:(0,t.__)("Available variables","prc-schema-seo")}),(0,s.jsx)(T,{children:i.map(e=>(0,s.jsx)(E,{token:e,onClick:g},e))})]})})]})]})})}const R=g().div`
	display: flex;
	flex-direction: column;
	gap: 16px;
`,O=g()(m.SelectControl)`
	.components-select-control__input {
		min-height: 36px;
	}
`,z=g()(m.CardDivider)`
	margin: 16px 0;
`,B=g()(m.ToggleControl)`
	.components-toggle-control__label {
		font-weight: 500;
	}
`;function I({availableTokens:e,templateData:a,update:n}){const o=function(){const e=window.PRCSchemaSEO&&window.PRCSchemaSEO.allowedSchemaTypes||["Article","NewsArticle","BlogPosting","Report","WebPage","Person","Event","Course","Dataset","Quiz","CollectionPage"];return[{label:(0,t.__)("Select a schema type…","prc-schema-seo"),value:""},...e.map(e=>({label:e,value:e}))]}();return(0,s.jsxs)(s.Fragment,{children:[(0,s.jsx)(m.PanelBody,{title:(0,t.__)("Template Defaults","prc-schema-seo"),children:(0,s.jsxs)(R,{children:[(0,s.jsx)(A,{label:(0,t.__)("Title Pattern","prc-schema-seo"),help:(0,t.__)("Use variables to create dynamic titles. Click tokens to remove them.","prc-schema-seo"),value:a.title_pattern,onChange:e=>n("title_pattern",e),availableTokens:e}),(0,s.jsx)(A,{label:(0,t.__)("Description Pattern","prc-schema-seo"),help:(0,t.__)("Create a dynamic meta description using variables.","prc-schema-seo"),value:a.description_pattern,onChange:e=>n("description_pattern",e),availableTokens:e,multiline:!0,rows:3})]})}),(0,s.jsx)(m.PanelBody,{title:(0,t.__)("Template Advanced","prc-schema-seo"),initialOpen:!1,children:(0,s.jsxs)(R,{children:[(0,s.jsx)(O,{label:(0,t.__)("Schema Type","prc-schema-seo"),value:a.schema_type||"",onChange:e=>n("schema_type",e),options:o,help:(0,t.__)("Select the structured data type for this template. This affects how search engines understand and display your content.","prc-schema-seo")}),(0,s.jsx)(z,{}),(0,s.jsx)(B,{label:(0,t.__)("Hide from search engines (noindex)","prc-schema-seo"),help:(0,t.__)("Instructs search engines not to index pages using this template. They will be excluded from search results and sitemaps.","prc-schema-seo"),checked:!!a.noindex,onChange:e=>n("noindex",e)})]})})]})}const M=g().div`
	display: flex;
	flex-direction: column;
	gap: 16px;
`;function $(){const{defaultData:e,update:a}=function(){const{editEntityRecord:e,saveEditedEntityRecord:t}=(0,n.useDispatch)("core"),a=(0,n.useSelect)(e=>e("core").getEditedEntityRecord("root","site")||e("core").getEntityRecord("root","site"),[]),o=(0,_.useMemo)(()=>{if(!a)return{defaultSite:{title_pattern:"",description_pattern:""},defaultSingular:{title_pattern:"",description_pattern:""},defaultArchive:{title_pattern:"",description_pattern:""}};const e=a.prc_schema_seo_templates||{},t=t=>{const a=e[t];return a&&"object"==typeof a?{title_pattern:a.title_pattern||"",description_pattern:a.description_pattern||""}:{title_pattern:"",description_pattern:""}};return{defaultSite:t("default_site"),defaultSingular:t("default_singular"),defaultArchive:t("default_archive")}},[a]);return{defaultData:o,update:(t,n,i)=>{const s=`default_${t}`,r={...o[`default${t.charAt(0).toUpperCase()+t.slice(1)}`],[n]:i},l={...a.prc_schema_seo_templates||{},[s]:r};e("root","site",void 0,{prc_schema_seo_templates:l})},save:async()=>{await t("root","site")}}}();return(0,s.jsxs)(s.Fragment,{children:[(0,s.jsx)(m.PanelBody,{title:(0,t.__)("Site-wide Defaults","prc-schema-seo"),initialOpen:!1,children:(0,s.jsxs)(M,{children:[(0,s.jsx)(A,{label:(0,t.__)("Default Title Pattern","prc-schema-seo"),help:(0,t.__)("Use variables to create a default title pattern. This will be used as the ultimate fallback when no template-specific or context-specific patterns are configured.","prc-schema-seo"),value:e.defaultSite.title_pattern,onChange:e=>a("site","title_pattern",e),availableTokens:["%site_name%","%site_tagline%","%year%","%sep%"]}),(0,s.jsx)(A,{label:(0,t.__)("Default Description Pattern","prc-schema-seo"),help:(0,t.__)("Create a default meta description pattern using variables. This will be used as the ultimate fallback when no template-specific or context-specific patterns are configured.","prc-schema-seo"),value:e.defaultSite.description_pattern,onChange:e=>a("site","description_pattern",e),availableTokens:["%site_name%","%site_tagline%","%year%","%sep%"],multiline:!0,rows:3})]})}),(0,s.jsx)(m.PanelBody,{title:(0,t.__)("Singular Defaults","prc-schema-seo"),initialOpen:!1,children:(0,s.jsxs)(M,{children:[(0,s.jsx)(A,{label:(0,t.__)("Default Title Pattern","prc-schema-seo"),help:(0,t.__)("Use variables to create a default title pattern for single posts and single terms. This will be used as a fallback when no template-specific pattern is configured for singular contexts.","prc-schema-seo"),value:e.defaultSingular.title_pattern,onChange:e=>a("singular","title_pattern",e),availableTokens:["%site_name%","%site_tagline%","%year%","%sep%","%object_title%","%object_description%","%object_type%","%post_title%","%term_name%","%post_date%","%author%"]}),(0,s.jsx)(A,{label:(0,t.__)("Default Description Pattern","prc-schema-seo"),help:(0,t.__)("Create a default meta description pattern for single posts and single terms using variables. This will be used as a fallback when no template-specific pattern is configured for singular contexts.","prc-schema-seo"),value:e.defaultSingular.description_pattern,onChange:e=>a("singular","description_pattern",e),availableTokens:["%site_name%","%site_tagline%","%year%","%sep%","%object_title%","%object_description%","%object_type%","%post_title%","%term_name%","%post_date%","%author%"],multiline:!0,rows:3})]})}),(0,s.jsx)(m.PanelBody,{title:(0,t.__)("Archive Defaults","prc-schema-seo"),initialOpen:!1,children:(0,s.jsxs)(M,{children:[(0,s.jsx)(A,{label:(0,t.__)("Default Title Pattern","prc-schema-seo"),help:(0,t.__)("Use variables to create a default title pattern for post type archives and taxonomy archives. This will be used as a fallback when no template-specific pattern is configured for archive contexts.","prc-schema-seo"),value:e.defaultArchive.title_pattern,onChange:e=>a("archive","title_pattern",e),availableTokens:["%site_name%","%site_tagline%","%year%","%sep%","%object_title%","%object_description%","%object_type%","%post_type%","%taxonomy%"]}),(0,s.jsx)(A,{label:(0,t.__)("Default Description Pattern","prc-schema-seo"),help:(0,t.__)("Create a default meta description pattern for post type archives and taxonomy archives using variables. This will be used as a fallback when no template-specific pattern is configured for archive contexts.","prc-schema-seo"),value:e.defaultArchive.description_pattern,onChange:e=>a("archive","description_pattern",e),availableTokens:["%site_name%","%site_tagline%","%year%","%sep%","%object_title%","%object_description%","%object_type%","%post_type%","%taxonomy%"],multiline:!0,rows:3})]})})]})}function Z({availableTokens:e,templateData:a,update:n}){return(0,s.jsx)(s.Fragment,{children:(0,s.jsx)(m.PanelBody,{title:(0,t.__)("Social","prc-schema-seo"),initialOpen:!1,children:(0,s.jsxs)("div",{className:"prc-schema-seo-panel-fields",children:[(0,s.jsx)(m.TextControl,{label:(0,t.__)("OG Title","prc-schema-seo"),value:seoData&&seoData.og_title||"",onChange:e=>n("og_title",e.slice(0,255))}),(0,s.jsx)(m.TextareaControl,{label:(0,t.__)("OG Description","prc-schema-seo"),value:seoData&&seoData.og_description||"",onChange:e=>n("og_description",e.slice(0,500))})]})})})}(0,m.withFilters)("prc-platform.seo.ui.site-editor.social")(e=>(0,s.jsx)(Z,{...e})),window.wp.blockEditor;const F=g().div`
	padding: 24px;
	text-align: center;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12px;
`,L=g().span`
	color: #757575;
	font-size: 13px;
`,H=g()(m.Card)`
	margin: 16px;
`,U=g().div`
	margin: 16px;
`,G=g().div`
	display: flex;
	align-items: center;
	gap: 8px;
`,N=g().span`
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #757575;
	font-weight: 600;
`,V=g().span`
	font-size: 13px;
	font-weight: 600;
	color: #1e1e1e;
`,q=g()(m.Notice)`
	&.is-warning {
		background: #fcf9e8;
		border-left-color: #dba617;
	}

	&.is-info {
		background: #f0f6fc;
		border-left-color: #2271b1;
	}
`;function J(){const{context:e,isLoading:a}=function(){const{currentTemplateId:e,currentPostType:t}=(0,n.useSelect)(e=>{const t=e("core/editor");return{currentTemplateId:t?.getCurrentPostId?.(),currentPostType:t?.getCurrentPostType?.()}},[]),{context:a,isLoading:o}=(0,_.useMemo)(()=>e&&"wp_template"===t?"string"!=typeof e||0===e.length?{context:null,isLoading:!1}:{context:x(e.split("//")[1]||e),isLoading:!1}:{context:null,isLoading:!1},[e,t]);return{context:a,isLoading:o}}(),{templateData:o,update:i}=function(e){const{editEntityRecord:t,saveEditedEntityRecord:a}=(0,n.useDispatch)("core"),o=(0,n.useSelect)(t=>e?.key?t("core").getEditedEntityRecord("root","site")||t("core").getEntityRecord("root","site"):null,[e]),i=(0,_.useMemo)(()=>{if(!e?.key||!o)return{title_pattern:"",description_pattern:"",schema_type:"",noindex:!1,og_image:0,twitter_image:0};const t=(o.prc_schema_seo_templates||{})[e.key];return t&&"object"==typeof t?{title_pattern:t.title_pattern||"",description_pattern:t.description_pattern||"",schema_type:t.schema_type||"",noindex:t.noindex||!1,og_image:t.og_image||0,twitter_image:t.twitter_image||0}:{title_pattern:"",description_pattern:"",schema_type:"",noindex:!1,og_image:0,twitter_image:0}},[e,o]);return{templateData:i,update:(a,n)=>{if(!e?.key)return;const s={...i,[a]:n},r={...o.prc_schema_seo_templates||{},[e.key]:s};t("root","site",void 0,{prc_schema_seo_templates:r})},save:async()=>{await a("root","site")}}}(e),r=(0,_.useMemo)(()=>function(e){if(!e)return[];const t=["%site_name%","%site_tagline%","%year%","%sep%"];return"single_post_type"===e.type?[...t,"%object_title%","%object_description%","%post_title%","%post_excerpt%","%post_date%","%post_url%","%post_type%","%primary_category%","%author%","%categories%","%tags%","%primary_term:taxonomy%","%terms:taxonomy%"]:"post_type_archive"===e.type?[...t,"%object_title%","%object_type%","%post_type%","%archive_title%","%page%","%page_number%","%page_total%"]:"taxonomy"===e.type?[...t,"%object_title%","%object_description%","%taxonomy%","%term_name%","%term_description%"]:"front_page"===e.type?[...t,"%tagline%"]:"blog"===e.type?[...t,"%page%","%page_number%","%page_total%"]:"search"===e.type?[...t,"%search_query%","%object_title%"]:t}(e),[e]);return a?(0,s.jsxs)(F,{children:[(0,s.jsx)(m.Spinner,{}),(0,s.jsx)(L,{children:(0,t.__)("Loading template context…","prc-schema-seo")})]}):e?"unknown"===e.type?(0,s.jsx)(H,{children:(0,s.jsx)(m.CardBody,{children:(0,s.jsx)(q,{status:"info",isDismissible:!1,children:(0,t.__)("SEO settings are not available for this template type.","prc-schema-seo")})})}):(0,s.jsxs)(s.Fragment,{children:[(0,s.jsx)(U,{children:(0,s.jsxs)(G,{children:[(0,s.jsx)(N,{children:(0,t.__)("Editing","prc-schema-seo")}),(0,s.jsx)(V,{children:e.label})]})}),(0,s.jsx)(I,{availableTokens:r,templateData:o,update:i}),(0,s.jsx)($,{})]}):(0,s.jsx)(H,{children:(0,s.jsx)(m.CardBody,{children:(0,s.jsx)(q,{status:"warning",isDismissible:!1,children:(0,t.__)("No template detected. Open a template in the Site Editor to configure SEO settings.","prc-schema-seo")})})})}const K="prc-schema-seo-template-defaults";(0,l.registerPlugin)(K,{render:function(){const{openGeneralSidebar:e}=(0,n.useDispatch)(o.store);return(0,a.useCommand)({name:"prc/show-search-and-social-template-defaults",label:(0,t.__)("Show Search and Social Template Defaults","prc-schema-seo"),icon:r,category:"view",keywords:["seo","meta","search","social","template","defaults"],callback:({close:t})=>{e(`${K}/${K}`),t()}}),(0,s.jsx)(s.Fragment,{children:(0,s.jsx)(h,{id:K,children:(0,s.jsx)(J,{})})})}})})();