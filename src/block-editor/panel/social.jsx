/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import {
	TextControl,
	TextareaControl,
	PanelBody,
	withFilters,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * External Dependencies
 */
import { CharacterCounter } from '@prc/components';

/**
 * Internal Dependencies
 */
const isAIEnabled =
	typeof window !== 'undefined' &&
	typeof window.PRCSchemaSEOAI !== 'undefined' &&
	window.PRCSchemaSEOAI.enabled;
const AISuggestSEO = isAIEnabled ? require('./ai-suggest-seo').default : null;

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

// Twitter/X is the most restrictive network: 70-char title, 200-char description
const MAX_SOCIAL_TITLE = 70;
const MAX_SOCIAL_DESC = 200;
const HOOK_NAME = 'prc-platform.seo.ui.social';

/**
 * Social
 * Renders social metadata fields for Open Graph and Twitter Card.
 * Images are managed via the Art Direction plugin (facebook/twitter slots).
 *
 * @param  root0
 * @param  root0.seoData
 * @param  root0.update
 * @return {JSX.Element|null} Social panel fields.
 */
function Social({ seoData, update }) {
	const excerpt = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('excerpt') || '',
		[]
	);

	const postTitle = useSelect(
		(select) =>
			decodeEntities(
				select('core/editor').getEditedPostAttribute('title') || ''
			),
		[]
	);

	const resolvedTitle = postTitle;
	const resolvedDescription = excerpt;

	return (
		<>
			<PanelBody
				title={__('Social', 'prc-schema-seo')}
				initialOpen={false}
			>
				{AISuggestSEO && (
					<AISuggestSEO
						fields={['og_title', 'og_description']}
						label={__('Suggest Social Metadata', 'prc-schema-seo')}
						update={update}
						seoData={seoData}
					/>
				)}
				<VStack spacing="2">
					<TextControl
						label={__('Social Title', 'prc-schema-seo')}
						placeholder={resolvedTitle}
						value={decodeEntities(
							(seoData && seoData.og_title) || ''
						)}
						onChange={(v) =>
							update('og_title', v.slice(0, MAX_SOCIAL_TITLE))
						}
						help={
							<CharacterCounter
								current={
									decodeEntities(
										(seoData && seoData.og_title) || ''
									).length
								}
								limit={MAX_SOCIAL_TITLE}
							/>
						}
					/>
					<TextareaControl
						label={__('Social Description', 'prc-schema-seo')}
						placeholder={resolvedDescription}
						value={decodeEntities(
							(seoData && seoData.og_description) || ''
						)}
						onChange={(v) =>
							update(
								'og_description',
								v.slice(0, MAX_SOCIAL_DESC)
							)
						}
						help={
							<CharacterCounter
								current={
									decodeEntities(
										(seoData && seoData.og_description) ||
											''
									).length
								}
								limit={MAX_SOCIAL_DESC}
							/>
						}
					/>
				</VStack>
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
