/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import {
	TextControl,
	TextareaControl,
	SelectControl,
	ToggleControl,
	CardDivider,
	PanelBody,
	Button,
	Spinner,
	ExternalLink,
} from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal Dependencies
 */
const isAIEnabled =
	typeof window !== 'undefined' &&
	typeof window.PRCSchemaSEOAI !== 'undefined' &&
	window.PRCSchemaSEOAI.enabled;
const AISuggestSEO = isAIEnabled ? require('./ai-suggest-seo').default : null;

const isIndexNowEnabled =
	typeof window !== 'undefined' && window.PRCSchemaSEO?.indexnowEnabled;

const isGSCEnabled =
	typeof window !== 'undefined' && window.PRCSchemaSEO?.gscEnabled;

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

function IndexNowStatus({ submittedAt }) {
	const submitted = !!submittedAt;
	return (
		<span
			style={{
				display: 'flex',
				alignItems: 'center',
				marginBottom: 12,
			}}
		>
			<span
				style={{
					display: 'inline-block',
					width: 10,
					height: 10,
					borderRadius: '50%',
					backgroundColor: submitted ? '#00a32a' : '#d63638',
					marginRight: 6,
					flexShrink: 0,
					verticalAlign: 'middle',
				}}
				aria-hidden="true"
			/>
			<span style={{ fontSize: 12 }}>
				{submitted
					? __('IndexNow: Submitted', 'prc-schema-seo')
					: __('IndexNow: Not submitted', 'prc-schema-seo')}
			</span>
		</span>
	);
}

const VERDICT_COLORS = {
	PASS: '#00a32a',
	PARTIAL: '#dba617',
	FAIL: '#d63638',
	NEUTRAL: '#ccc',
};

const VERDICT_LABELS = {
	PASS: __('Google: Indexed', 'prc-schema-seo'),
	PARTIAL: __('Google: Partially indexed', 'prc-schema-seo'),
	FAIL: __('Google: Not indexed', 'prc-schema-seo'),
	NEUTRAL: __('Google: Unknown', 'prc-schema-seo'),
};

function formatCrawlDate(iso) {
	if (!iso) return null;
	try {
		const d = new Date(iso);
		return d.toLocaleDateString(undefined, {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
		});
	} catch {
		return null;
	}
}

function GoogleIndexStatus({ gscData, postId, onRefresh }) {
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);

	const handleRefresh = async () => {
		setLoading(true);
		setError(null);
		try {
			const result = await apiFetch({
				path: `/prc-schema-seo/v1/gsc-inspect/${postId}`,
				method: 'POST',
			});
			onRefresh(result);
		} catch (err) {
			setError(err.message || __('Refresh failed', 'prc-schema-seo'));
		} finally {
			setLoading(false);
		}
	};

	const verdict = gscData?.verdict || 'NEUTRAL';
	const dotColor = VERDICT_COLORS[verdict] || VERDICT_COLORS.NEUTRAL;
	const label = VERDICT_LABELS[verdict] || VERDICT_LABELS.NEUTRAL;
	const crawlDate = formatCrawlDate(gscData?.last_crawl_time);
	const coverageState = gscData?.coverage_state;
	const fetchedAt = gscData?.fetched_at;
	const inspectionLink = gscData?.inspection_link;

	const allIssues = [
		...(gscData?.mobile_issues || []),
		...(gscData?.rich_results_issues || []),
	].filter(Boolean);

	return (
		<div style={{ marginBottom: 12 }}>
			<span
				style={{
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'space-between',
				}}
			>
				<span style={{ display: 'flex', alignItems: 'center' }}>
					<span
						style={{
							display: 'inline-block',
							width: 10,
							height: 10,
							borderRadius: '50%',
							backgroundColor: dotColor,
							marginRight: 6,
							flexShrink: 0,
							verticalAlign: 'middle',
						}}
						aria-hidden="true"
					/>
					<span style={{ fontSize: 12 }}>
						{gscData
							? label
							: __('Google: Not checked', 'prc-schema-seo')}
					</span>
				</span>
				<Button
					variant="link"
					onClick={handleRefresh}
					disabled={loading}
					style={{ fontSize: 11, padding: 0, height: 'auto' }}
				>
					{loading ? <Spinner /> : __('Refresh', 'prc-schema-seo')}
				</Button>
			</span>

			{coverageState && (
				<span
					style={{
						display: 'block',
						fontSize: 11,
						color: '#757575',
						marginLeft: 16,
						marginTop: 2,
					}}
				>
					{coverageState}
				</span>
			)}

			{crawlDate && (
				<span
					style={{
						display: 'block',
						fontSize: 11,
						color: '#757575',
						marginLeft: 16,
					}}
				>
					{__('Last crawled:', 'prc-schema-seo')} {crawlDate}
				</span>
			)}

			{fetchedAt && (
				<span
					style={{
						display: 'block',
						fontSize: 11,
						color: '#757575',
						marginLeft: 16,
					}}
				>
					{__('Checked:', 'prc-schema-seo')}{' '}
					{formatCrawlDate(new Date(fetchedAt * 1000).toISOString())}
				</span>
			)}

			{allIssues.length > 0 && (
				<div
					style={{
						marginTop: 6,
						marginLeft: 16,
						padding: '6px 8px',
						backgroundColor: '#fcf0f1',
						borderRadius: 2,
						fontSize: 11,
					}}
				>
					<strong>{__('Issues:', 'prc-schema-seo')}</strong>
					<ul
						style={{
							margin: '4px 0 0',
							paddingLeft: 16,
							listStyleType: 'disc',
						}}
					>
						{allIssues.map((issue, i) => (
							<li key={i}>{issue}</li>
						))}
					</ul>
				</div>
			)}

			{inspectionLink && (
				<span
					style={{
						display: 'block',
						fontSize: 11,
						marginLeft: 16,
						marginTop: 4,
					}}
				>
					<ExternalLink href={inspectionLink}>
						{__('View in Search Console', 'prc-schema-seo')}
					</ExternalLink>
				</span>
			)}

			{error && (
				<span
					style={{
						display: 'block',
						fontSize: 11,
						color: '#d63638',
						marginLeft: 16,
						marginTop: 4,
					}}
				>
					{error}
				</span>
			)}
		</div>
	);
}

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
	const postId = useSelect(
		(select) => select('core/editor').getCurrentPostId(),
		[]
	);

	const [localGscData, setLocalGscData] = useState(null);
	const gscData = localGscData || seoData?.gsc_index_status;

	return (
		<>
			<PanelBody title={__('Search', 'prc-schema-seo')}>
				{isIndexNowEnabled && (
					<IndexNowStatus
						submittedAt={seoData?.indexnow_submitted_at}
					/>
				)}
				{isGSCEnabled && (
					<GoogleIndexStatus
						gscData={gscData}
						postId={postId}
						onRefresh={setLocalGscData}
					/>
				)}
				{AISuggestSEO && (
					<AISuggestSEO
						fields={['title', 'description']}
						label={__(
							'Suggest Title & Description',
							'prc-schema-seo'
						)}
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
