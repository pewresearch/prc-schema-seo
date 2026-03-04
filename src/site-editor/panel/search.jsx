/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	ToggleControl,
	CardDivider,
	PanelBody,
} from '@wordpress/components';

/**
 * External Dependencies
 */
import styled from '@emotion/styled';

/**
 * Internal Dependencies
 */
import { TokenInput } from '../components';

/**
 * Styled Components
 */
const PanelFields = styled.div`
	display: flex;
	flex-direction: column;
	gap: 16px;
`;

const StyledSelectControl = styled(SelectControl)`
	.components-select-control__input {
		min-height: 36px;
	}
`;

const StyledCardDivider = styled(CardDivider)`
	margin: 16px 0;
`;

const StyledToggleControl = styled(ToggleControl)`
	.components-toggle-control__label {
		font-weight: 500;
	}
`;

/**
 * @typedef  {Object}       SEOData
 * @property {string}      [title]          - Custom SEO title
 * @property {string}      [description]    - Meta description
 * @property {string}      [og_title]       - Open Graph title
 * @property {string}      [og_description] - Open Graph description
 * @property {number|null} [og_image]       - Open Graph image attachment ID
 * @property {number|null} [twitter_image]  - Twitter Card image attachment ID
 * @property {string}      [schema_type]    - Schema.org type
 * @property {boolean}     [noindex]        - Prevent search engine indexing
 * @property {string}      [canonical_url]  - Custom canonical URL
 */

/**
 * Get schema type options from localized data or use defaults.
 *
 * @return {Array} Array of options for SelectControl.
 */
function getSchemaTypeOptions() {
	const types = (window.PRCSchemaSEO &&
		window.PRCSchemaSEO.allowedSchemaTypes) || [
		'Article',
		'NewsArticle',
		'BlogPosting',
		'Report',
		'WebPage',
		'Person',
		'Event',
		'Course',
		'Dataset',
		'Quiz',
		'CollectionPage',
	];

	return [
		{ label: __('Select a schema type…', 'prc-schema-seo'), value: '' },
		...types.map((type) => ({ label: type, value: type })),
	];
}

/**
 * Search
 * Renders the search/SEO input form for template defaults.
 * Uses TokenInput for pattern fields and SelectControl for schema type.
 *
 * @param {Object}   props                 Component props.
 * @param {string[]} props.availableTokens Array of available token strings.
 * @param {Object}   props.templateData    Current template SEO data.
 * @param {Function} props.update          Callback to update a field.
 * @return {JSX.Element} Search panel fields.
 */
export default function Search({ availableTokens, templateData, update }) {
	const schemaTypeOptions = getSchemaTypeOptions();

	return (
		<>
			<PanelBody title={__('Template Defaults', 'prc-schema-seo')}>
				<PanelFields>
					<TokenInput
						label={__('Title Pattern', 'prc-schema-seo')}
						help={__(
							'Use variables to create dynamic titles. Click tokens to remove them.',
							'prc-schema-seo'
						)}
						value={templateData.title_pattern}
						onChange={(v) => update('title_pattern', v)}
						availableTokens={availableTokens}
					/>

					<TokenInput
						label={__('Description Pattern', 'prc-schema-seo')}
						help={__(
							'Create a dynamic meta description using variables.',
							'prc-schema-seo'
						)}
						value={templateData.description_pattern}
						onChange={(v) => update('description_pattern', v)}
						availableTokens={availableTokens}
						multiline
						rows={3}
					/>
				</PanelFields>
			</PanelBody>

			<PanelBody
				title={__('Template Advanced', 'prc-schema-seo')}
				initialOpen={false}
			>
				<PanelFields>
					<StyledSelectControl
						label={__('Schema Type', 'prc-schema-seo')}
						value={templateData.schema_type || ''}
						onChange={(v) => update('schema_type', v)}
						options={schemaTypeOptions}
						help={__(
							'Select the structured data type for this template. This affects how search engines understand and display your content.',
							'prc-schema-seo'
						)}
					/>

					<StyledCardDivider />

					<StyledToggleControl
						label={__(
							'Hide from search engines (noindex)',
							'prc-schema-seo'
						)}
						help={__(
							'Instructs search engines not to index pages using this template. They will be excluded from search results and sitemaps.',
							'prc-schema-seo'
						)}
						checked={!!templateData.noindex}
						onChange={(v) => update('noindex', v)}
					/>
				</PanelFields>
			</PanelBody>
		</>
	);
}
