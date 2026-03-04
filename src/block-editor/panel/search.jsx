/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	TextControl,
	TextareaControl,
	SelectControl,
	ToggleControl,
	CardDivider,
	PanelBody,
} from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal Dependencies
 */
const isAIEnabled =
	typeof window !== 'undefined' &&
	typeof window.PRCSchemaSEOAI !== 'undefined' &&
	window.PRCSchemaSEOAI.enabled;
const AISuggestSEO = isAIEnabled
	? require('./ai-suggest-seo').default
	: null;

/**
 * @typedef  {Object}       SEOData
 * @property {string}      [title]          - Custom SEO title
 * @property {string}      [description]    - Meta description
 * @property {string}      [og_title]       - Open Graph title
 * @property {string}      [og_description] - Open Graph description
 * @property {number|null} [og_image]       - Open Graph image attachment ID
 * @property {string}      [schema_type]    - Schema.org type
 * @property {boolean}     [noindex]        - Prevent search engine indexing
 * @property {string}      [canonical_url]  - Custom canonical URL
 */

/**
 * @typedef  {Object} PostRecord
 * @property {Object} [title]       - Post title object
 * @property {string} [title.raw]   - Raw post title
 * @property {Object} [content]     - Post content object
 * @property {string} [content.raw] - Raw post content
 * @property {Object} [excerpt]     - Post excerpt object
 * @property {string} [excerpt.raw] - Raw post excerpt
 * @property {string} [slug]        - Post slug
 */

const MAX_TITLE = 255;
const MAX_DESC = 500;

const schemaTypes = (
	(window.PRCSchemaSEO && window.PRCSchemaSEO.allowedSchemaTypes) || [
		'Article',
		'WebPage',
	]
).map((type) => ({ label: type, value: type }));

/**
 * Panel
 * Renders the main SEO input form within the Document Settings panel.
 * Handles per-post metadata editing with live character counts and preview tabs.
 * Conditional render based on enabled post types localized from PHP.
 *
 * @param  root0
 * @param  root0.seoData
 * @param  root0.update
 * @return {JSX.Element|null} Editor panel fields or null if post type is not enabled.
 */
export default function Search({ seoData, update }) {
	return (
		<>
			<PanelBody title={__('Search', 'prc-schema-seo')}>
				{AISuggestSEO && (
					<AISuggestSEO
						fields={['title', 'description']}
						label={__('Suggest Title & Description', 'prc-schema-seo')}
						update={update}
						seoData={seoData}
					/>
				)}
				<div className="prc-schema-seo-panel-fields">
					<TextControl
						label={__('SEO Title', 'prc-schema-seo')}
						value={decodeEntities((seoData && seoData.title) || '')}
						onChange={(v) => update('title', v.slice(0, MAX_TITLE))}
						help={`${decodeEntities((seoData && seoData.title) || '').length} / ${MAX_TITLE}`}
					/>
					<TextareaControl
						label={__('SEO Description', 'prc-schema-seo')}
						value={decodeEntities(
							(seoData && seoData.description) || ''
						)}
						onChange={(v) =>
							update('description', v.slice(0, MAX_DESC))
						}
						help={`${decodeEntities((seoData && seoData.description) || '').length} / ${MAX_DESC}`}
					/>
				</div>
			</PanelBody>
			<PanelBody
				title={__('Search Advanced', 'prc-schema-seo')}
				initialOpen={false}
			>
				<div>
					<SelectControl
						label={__('Schema Type', 'prc-schema-seo')}
						value={decodeEntities(
							(seoData && seoData.schema_type) ||
								schemaTypes[0]?.value ||
								''
						)}
						onChange={(v) => update('schema_type', v)}
						options={schemaTypes}
						help={__(
							'Select the structured data type. The schema.json type you choose can influence eligibility for rich results (enhanced snippets, badges) in Google Search and helps the Google Knowledge Graph better understand and relate your content to entities.',
							'prc-schema-seo'
						)}
					/>
					<CardDivider />
					<ToggleControl
						label={__(
							'Hide page from search engines',
							'prc-schema-seo'
						)}
						help={__(
							"If selected, a 'noindex' tag will help instruct search engines and crawlers to NOT include this page in search results and remove it from the sitemap.",
							'prc-schema-seo'
						)}
						checked={!!(seoData && seoData.noindex)}
						onChange={(v) => update('noindex', v)}
					/>
					<CardDivider />
					<TextControl
						label={__('Canonical URL', 'prc-schema-seo')}
						value={decodeEntities(
							(seoData && seoData.canonical_url) || ''
						)}
						onChange={(v) => update('canonical_url', v)}
						help={__(
							'Override the default canonical URL. Leave empty to use the default permalink. Use this to indicate the preferred URL when content exists at multiple URLs.',
							'prc-schema-seo'
						)}
						type="url"
						placeholder="https://example.com/preferred-url"
					/>
				</div>
			</PanelBody>
		</>
	);
}
