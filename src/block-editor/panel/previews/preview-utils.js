/* eslint-disable */
/**
 * WordPress Dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';

/**
 * derivePreviewData
 * Builds a unified preview data object applying client-side fallbacks and truncation
 * to approximate server meta resolution: title, description, OG, and Twitter fields.
 *
 * @param {import('..').SEOData|undefined} seoData Raw per-post SEO meta.
 * @param {import('..').PostRecord|undefined} post Current post record from editor store.
 * @returns {{title:string,description:string,ogTitle:string,ogDescription:string,twitterTitle:string,twitterDescription:string,slug:string,images:{facebook:string|null,twitter:string|null}}} Derived preview data.
 */
export function derivePreviewData(seoData, post) {
	const title = decodeEntities(seoData?.title || post?.title?.raw || '');
	let description = seoData?.description;
	if (!description) {
		const rawContent = post?.content?.raw || '';
		const excerpt = post?.excerpt?.raw || '';
		description = excerpt || rawContent.replace(/<[^>]+>/g, '');
		// Trim to ~160 chars for Google preview (~30 words)
		description = description.split(/\s+/).slice(0, 30).join(' ');
	}
	const rawPostTitle = post?.title?.raw || '';
	const postExcerpt = post?.excerpt?.raw || '';
	const ogTitle = decodeEntities(seoData?.og_title || rawPostTitle);
	const ogDescription = decodeEntities(
		seoData?.og_description || postExcerpt || ''
	);

	// Get social images from art_direction REST field
	const artDirection = post?.art_direction || {};
	const socialImage = artDirection?.social?.url || null;

	return {
		title,
		description,
		ogTitle,
		ogDescription,
		slug: post?.slug || '',
		ogImage: socialImage,
	};
}
