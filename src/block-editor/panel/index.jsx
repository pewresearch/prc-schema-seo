/**
 * WordPress Dependencies
 */
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';

/**
 * Internal Dependencies
 */
import Preview from './preview';
import Search from './search';
import ShortlinkQr from './shortlink-qr';
import Social from './social';

/**
 * @typedef  {Object}      SEOData
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

/**
 * Panel
 * Renders the main SEO input form within the Document Settings panel.
 * Handles per-post metadata editing with live character counts and preview tabs.
 * Conditional render based on enabled post types localized from PHP.
 *
 * @return {JSX.Element|null} Editor panel fields or null if post type is not enabled.
 */
export default function Panel() {
	const postType = useSelect(
		(select) => select('core/editor').getCurrentPostType(),
		[]
	);
	const [seoData, setSeoData] = useEntityProp(
		'postType',
		postType,
		'prc_seo_data'
	);
	const post = useSelect(
		(select) => select('core/editor').getCurrentPost(),
		[]
	);
	const enabledPostTypes =
		(window.PRCSchemaSEO && window.PRCSchemaSEO.enabledPostTypes) || [];

	if (!enabledPostTypes.includes(postType)) {
		return null;
	}

	/**
	 * Update SEO fields while preserving existing values.
	 * Accepts either (key, value) for a single field or a plain object to
	 * bulk-merge multiple fields in one state update (avoids stale-closure
	 * issues when iterating over AI suggestions).
	 *
	 * @param {string|Object} keyOrObject Meta field key or object of key→value pairs.
	 * @param {*}             [value]     New value when keyOrObject is a string.
	 */
	const update = (keyOrObject, value) => {
		if (keyOrObject !== null && typeof keyOrObject === 'object') {
			setSeoData({ ...(seoData || {}), ...keyOrObject });
		} else {
			setSeoData({ ...(seoData || {}), [keyOrObject]: value });
		}
	};

	return (
		<>
			<Search seoData={seoData} update={update} />
			<Social seoData={seoData} update={update} />
			<ShortlinkQr />
			<Preview seoData={seoData} post={post} />
		</>
	);
}
