/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelBody } from '@wordpress/components';

/**
 * External Dependencies
 */
import styled from '@emotion/styled';

/**
 * Internal Dependencies
 */
import { TokenInput } from '../components';
import useDefaultSettings from '../preferences/use-default-settings';

/**
 * Styled Components
 */
const PanelFields = styled.div`
	display: flex;
	flex-direction: column;
	gap: 16px;
`;

/**
 * Get available tokens for site-wide defaults.
 * These are the base tokens that work across all contexts.
 *
 * @return {string[]} Array of token placeholder strings.
 */
function getSiteTokens() {
	return ['%site_name%', '%site_tagline%', '%year%', '%sep%'];
}

/**
 * Get available tokens for singular defaults.
 * These work for single posts and single terms.
 *
 * @return {string[]} Array of token placeholder strings.
 */
function getSingularTokens() {
	return [
		'%site_name%',
		'%site_tagline%',
		'%year%',
		'%sep%',
		'%object_title%',
		'%object_description%',
		'%object_type%',
		'%post_title%',
		'%term_name%',
		'%post_date%',
		'%author%',
	];
}

/**
 * Get available tokens for archive defaults.
 * These work for post type archives and taxonomy archives.
 *
 * @return {string[]} Array of token placeholder strings.
 */
function getArchiveTokens() {
	return [
		'%site_name%',
		'%site_tagline%',
		'%year%',
		'%sep%',
		'%object_title%',
		'%object_description%',
		'%object_type%',
		'%post_type%',
		'%taxonomy%',
	];
}

/**
 * SiteDefaults
 * Renders three PanelBody sections for managing default fallback SEO patterns:
 * 1. Site-wide Defaults - Ultimate fallback for any context
 * 2. Singular Defaults - For single posts and single terms
 * 3. Archive Defaults - For post type archives and taxonomy archives
 *
 * @return {JSX.Element} Site defaults panel sections.
 */
export default function SiteDefaults() {
	const { defaultData, update } = useDefaultSettings();

	return (
		<>
			<PanelBody
				title={__('Site-wide Defaults', 'prc-schema-seo')}
				initialOpen={false}
			>
				<PanelFields>
					<TokenInput
						label={__('Default Title Pattern', 'prc-schema-seo')}
						help={__(
							'Use variables to create a default title pattern. This will be used as the ultimate fallback when no template-specific or context-specific patterns are configured.',
							'prc-schema-seo'
						)}
						value={defaultData.defaultSite.title_pattern}
						onChange={(v) => update('site', 'title_pattern', v)}
						availableTokens={getSiteTokens()}
					/>

					<TokenInput
						label={__(
							'Default Description Pattern',
							'prc-schema-seo'
						)}
						help={__(
							'Create a default meta description pattern using variables. This will be used as the ultimate fallback when no template-specific or context-specific patterns are configured.',
							'prc-schema-seo'
						)}
						value={defaultData.defaultSite.description_pattern}
						onChange={(v) =>
							update('site', 'description_pattern', v)
						}
						availableTokens={getSiteTokens()}
						multiline
						rows={3}
					/>
				</PanelFields>
			</PanelBody>

			<PanelBody
				title={__('Singular Defaults', 'prc-schema-seo')}
				initialOpen={false}
			>
				<PanelFields>
					<TokenInput
						label={__('Default Title Pattern', 'prc-schema-seo')}
						help={__(
							'Use variables to create a default title pattern for single posts and single terms. This will be used as a fallback when no template-specific pattern is configured for singular contexts.',
							'prc-schema-seo'
						)}
						value={defaultData.defaultSingular.title_pattern}
						onChange={(v) => update('singular', 'title_pattern', v)}
						availableTokens={getSingularTokens()}
					/>

					<TokenInput
						label={__(
							'Default Description Pattern',
							'prc-schema-seo'
						)}
						help={__(
							'Create a default meta description pattern for single posts and single terms using variables. This will be used as a fallback when no template-specific pattern is configured for singular contexts.',
							'prc-schema-seo'
						)}
						value={defaultData.defaultSingular.description_pattern}
						onChange={(v) =>
							update('singular', 'description_pattern', v)
						}
						availableTokens={getSingularTokens()}
						multiline
						rows={3}
					/>
				</PanelFields>
			</PanelBody>

			<PanelBody
				title={__('Archive Defaults', 'prc-schema-seo')}
				initialOpen={false}
			>
				<PanelFields>
					<TokenInput
						label={__('Default Title Pattern', 'prc-schema-seo')}
						help={__(
							'Use variables to create a default title pattern for post type archives and taxonomy archives. This will be used as a fallback when no template-specific pattern is configured for archive contexts.',
							'prc-schema-seo'
						)}
						value={defaultData.defaultArchive.title_pattern}
						onChange={(v) => update('archive', 'title_pattern', v)}
						availableTokens={getArchiveTokens()}
					/>

					<TokenInput
						label={__(
							'Default Description Pattern',
							'prc-schema-seo'
						)}
						help={__(
							'Create a default meta description pattern for post type archives and taxonomy archives using variables. This will be used as a fallback when no template-specific pattern is configured for archive contexts.',
							'prc-schema-seo'
						)}
						value={defaultData.defaultArchive.description_pattern}
						onChange={(v) =>
							update('archive', 'description_pattern', v)
						}
						availableTokens={getArchiveTokens()}
						multiline
						rows={3}
					/>
				</PanelFields>
			</PanelBody>
		</>
	);
}
