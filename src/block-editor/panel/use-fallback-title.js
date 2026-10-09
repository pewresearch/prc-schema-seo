/**
 * WordPress Dependencies
 */
import { useSelect } from '@wordpress/data';
import { decodeEntities } from '@wordpress/html-entities';

const CAMPAIGN_POST_TYPE = 'prc_email_campaign';
const SUBJECT_META_KEY = 'prc_email_subject';

/**
 * Remove Mailchimp (`*|TAG|*`) and `{{tag}}` merge tags from a subject line.
 * Mirrors Campaign_Title::strip_merge_tags() in prc-email-builder.
 *
 * @param {string} text Subject line.
 * @return {string} Display text with merge tags removed.
 */
export function stripMergeTags(text) {
	return (text || '')
		.replace(/\*\|[^|*]*\|\*/g, '')
		.replace(/\{\{[^{}]*\}\}/g, '')
		.replace(/\s+/g, ' ')
		.replace(/\s+([,;:.!?])/g, '$1')
		.replace(/^[\s,;:\-\u2013\u2014|\u00B7\u2022/]+/, '')
		.replace(/[\s,;:\-\u2013\u2014|\u00B7\u2022/]+$/, '')
		.trim();
}

/**
 * Title the front end falls back to when no SEO title is set.
 * Campaigns use their subject line; other posts use the post title.
 *
 * @param {string} postType  Post type slug.
 * @param {string} postTitle Post title.
 * @param {string} subject   Raw `prc_email_subject` meta value.
 * @return {string} Fallback title.
 */
export function resolveFallbackTitle(postType, postTitle, subject) {
	if (CAMPAIGN_POST_TYPE === postType) {
		const headline = stripMergeTags(subject);
		if (headline) {
			return headline;
		}
	}
	return postTitle || '';
}

/**
 * useFallbackTitle
 * Placeholder title for the SEO, Social, and preview fields. Reads the edited
 * post, so it follows the subject line as the editor types.
 *
 * @return {string} Decoded fallback title.
 */
export default function useFallbackTitle() {
	return useSelect((select) => {
		const editor = select('core/editor');
		const meta = editor.getEditedPostAttribute('meta') || {};
		return decodeEntities(
			resolveFallbackTitle(
				editor.getCurrentPostType(),
				editor.getEditedPostAttribute('title') || '',
				meta[SUBJECT_META_KEY] || ''
			)
		);
	}, []);
}
