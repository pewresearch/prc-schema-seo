/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { PanelBody, Button } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import PreviewsModal from './previews';

/**
 * @typedef {Object} SEOData
 * @property {string}      [title]          SEO title.
 * @property {string}      [description]    Meta description.
 * @property {string}      [og_title]       Social sharing title.
 * @property {string}      [og_description] Social sharing description.
 * @property {number|null} [og_image]       Social sharing image attachment ID.
 * @property {string}      [schema_type]    Schema.org type.
 * @property {boolean}     [noindex]        Whether to prevent indexing.
 */

/**
 * @typedef {Object} PostRecord
 * @property {Object} [title]       Post title object.
 * @property {string} [title.raw]   Raw post title text.
 * @property {Object} [content]     Post content object.
 * @property {string} [content.raw] Raw post content text.
 * @property {Object} [excerpt]     Post excerpt object.
 * @property {string} [excerpt.raw] Raw post excerpt text.
 * @property {string} [slug]        Post slug.
 */

/**
 * Panel
 * Renders the main SEO input form within the Document Settings panel.
 * Handles per-post metadata editing with live character counts and preview tabs.
 * Conditional render based on enabled post types localized from PHP.
 *
 * @param {Object}     root0
 * @param {SEOData}    root0.seoData SEO metadata.
 * @param {PostRecord} root0.post    Post record data.
 * @return {JSX.Element|null} Editor panel fields or null if post type is not enabled.
 */
export default function Preview({ seoData, post }) {
	const [isPreviewOpen, setIsPreviewOpen] = useState(false);

	return (
		<>
			<PanelBody title={__('Previews', 'prc-schema-seo')}>
				<p
					style={{
						marginBottom: '12px',
						color: '#757575',
						fontSize: '13px',
					}}
				>
					{__(
						'Preview how your content will appear when shared on social networks and search engines.',
						'prc-schema-seo'
					)}
				</p>
				<Button
					variant="secondary"
					onClick={() => setIsPreviewOpen(true)}
					style={{ width: '100%', justifyContent: 'center' }}
				>
					{__('Preview', 'prc-schema-seo')}
				</Button>
				{isPreviewOpen && (
					<PreviewsModal
						seoData={seoData}
						post={post}
						onClose={() => setIsPreviewOpen(false)}
					/>
				)}
			</PanelBody>
		</>
	);
}
