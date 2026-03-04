/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	TextControl,
	TextareaControl,
	PanelBody,
	withFilters,
} from '@wordpress/components';

/**
 * @typedef {Object} SEOData
 * @property {string}  [title]
 * @property {string}  [description]
 * @property {string}  [og_title]
 * @property {string}  [og_description]
 * @property {string}  [schema_type]
 * @property {boolean} [noindex]
 */

/**
 * @typedef {Object} PostRecord
 * @property {Object} [title]
 * @property {string} [title.raw]
 * @property {Object} [content]
 * @property {string} [content.raw]
 * @property {Object} [excerpt]
 * @property {string} [excerpt.raw]
 * @property {string} [slug]
 */

const MAX_TITLE = 255;
const MAX_DESC = 500;
const HOOK_NAME = 'prc-platform.seo.ui.site-editor.social';

/**
 * Social
 * Renders social metadata fields for Open Graph and Twitter Card.
 * Images are managed via the Art Direction plugin (facebook/twitter slots).
 *
 * @param  root0
 * @param  root0.seoData
 * @param  root0.update
 * @param  root0.availableTokens
 * @param  root0.templateData
 * @return {JSX.Element|null} Social panel fields.
 */
function Social({ availableTokens, templateData, update }) {
	return (
		<>
			<PanelBody
				title={__('Social', 'prc-schema-seo')}
				initialOpen={false}
			>
				<div className="prc-schema-seo-panel-fields">
					<TextControl
						label={__('OG Title', 'prc-schema-seo')}
						value={(seoData && seoData.og_title) || ''}
						onChange={(v) =>
							update('og_title', v.slice(0, MAX_TITLE))
						}
					/>
					<TextareaControl
						label={__('OG Description', 'prc-schema-seo')}
						value={(seoData && seoData.og_description) || ''}
						onChange={(v) =>
							update('og_description', v.slice(0, MAX_DESC))
						}
					/>
				</div>
			</PanelBody>
		</>
	);
}

/**
 * FilterableSocial
 * Wrapper component that applies filters and ensures props are passed through.
 *
 * @param {Object} props Component props
 * @return {JSX.Element} Filtered Social component
 */
const FilterableSocial = withFilters(HOOK_NAME)((props) => (
	<Social {...props} />
));

/**
 * Higher-order component that allows filtering the Social panel.
 * Other plugins can use the 'prc-platform.seo.ui.social' filter to extend or modify this component.
 *
 * @example
 * // In another plugin:
 * import { addFilter } from '@wordpress/hooks';
 *
 * addFilter(
 *   'prc-platform.seo.ui.social',
 *   'my-plugin/extend-social-panel',
 *   (SocialComponent) => (props) => (
 *     <>
 *       <SocialComponent {...props} />
 *       <PanelBody title="My Custom Fields">
 *         {/* Additional fields *\/}
 *       </PanelBody>
 *     </>
 *   )
 * );
 */
export default FilterableSocial;
