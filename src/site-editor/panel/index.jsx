/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import { Notice, Spinner, Card, CardBody } from '@wordpress/components';

/**
 * External Dependencies
 */
import styled from '@emotion/styled';

/**
 * Internal Dependencies
 */
import useTemplateContext from '../use-template-context';
import useTemplateSettings from '../use-template-settings';
import Search from './search';
import SiteDefaults from './site-defaults';
import Social from './social';
import Images from './images';

/**
 * Styled Components
 */
const LoadingContainer = styled.div`
	padding: 24px;
	text-align: center;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12px;
`;

const LoadingText = styled.span`
	color: #757575;
	font-size: 13px;
`;

const NoticeCard = styled(Card)`
	margin: 16px;
`;

const ContextCard = styled.div`
	margin: 16px;
`;

const ContextContent = styled.div`
	display: flex;
	align-items: center;
	gap: 8px;
`;

const ContextLabel = styled.span`
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #757575;
	font-weight: 600;
`;

const ContextValue = styled.span`
	font-size: 13px;
	font-weight: 600;
	color: #1e1e1e;
`;

const StyledNotice = styled(Notice)`
	&.is-warning {
		background: #fcf9e8;
		border-left-color: #dba617;
	}

	&.is-info {
		background: #f0f6fc;
		border-left-color: #2271b1;
	}
`;

/**
 * getAvailableTokens - Returns list of available tokens based on template context.
 *
 * @param {{type: string}} context Template context object.
 * @return {string[]} Array of token placeholder strings.
 */
function getAvailableTokens(context) {
	if (!context) {
		return [];
	}

	const baseTokens = ['%site_name%', '%site_tagline%', '%year%', '%sep%'];

	if (context.type === 'single_post_type') {
		return [
			...baseTokens,
			'%object_title%',
			'%object_description%',
			'%post_title%',
			'%post_excerpt%',
			'%post_date%',
			'%post_url%',
			'%post_type%',
			'%primary_category%',
			'%author%',
			'%categories%',
			'%tags%',
			'%primary_term:taxonomy%',
			'%terms:taxonomy%',
		];
	}

	if (context.type === 'post_type_archive') {
		return [
			...baseTokens,
			'%object_title%',
			'%object_type%',
			'%post_type%',
			'%archive_title%',
			'%page%',
			'%page_number%',
			'%page_total%',
		];
	}

	if (context.type === 'taxonomy') {
		return [
			...baseTokens,
			'%object_title%',
			'%object_description%',
			'%taxonomy%',
			'%term_name%',
			'%term_description%',
		];
	}

	if (context.type === 'front_page') {
		return [...baseTokens, '%tagline%'];
	}

	if (context.type === 'blog') {
		return [...baseTokens, '%page%', '%page_number%', '%page_total%'];
	}

	if (context.type === 'search') {
		return [...baseTokens, '%search_query%', '%object_title%'];
	}

	return baseTokens;
}

/**
 * SiteEditorPanel
 * Context-aware Site Editor sidebar for managing per-template SEO defaults.
 * Detects which template is being edited and shows relevant settings.
 *
 * @return {JSX.Element} The site editor panel.
 */
export default function SiteEditorPanel() {
	const { context, isLoading } = useTemplateContext();
	const { templateData, update } = useTemplateSettings(context);

	const availableTokens = useMemo(
		() => getAvailableTokens(context),
		[context]
	);

	if (isLoading) {
		return (
			<LoadingContainer>
				<Spinner />
				<LoadingText>
					{__('Loading template context…', 'prc-schema-seo')}
				</LoadingText>
			</LoadingContainer>
		);
	}

	if (!context) {
		return (
			<NoticeCard>
				<CardBody>
					<StyledNotice status="warning" isDismissible={false}>
						{__(
							'No template detected. Open a template in the Site Editor to configure SEO settings.',
							'prc-schema-seo'
						)}
					</StyledNotice>
				</CardBody>
			</NoticeCard>
		);
	}

	if (context.type === 'unknown') {
		return (
			<NoticeCard>
				<CardBody>
					<StyledNotice status="info" isDismissible={false}>
						{__(
							'SEO settings are not available for this template type.',
							'prc-schema-seo'
						)}
					</StyledNotice>
				</CardBody>
			</NoticeCard>
		);
	}

	return (
		<>
			<ContextCard>
				<ContextContent>
					<ContextLabel>
						{__('Editing', 'prc-schema-seo')}
					</ContextLabel>
					<ContextValue>{context.label}</ContextValue>
				</ContextContent>
			</ContextCard>

			<Search
				availableTokens={availableTokens}
				templateData={templateData}
				update={update}
			/>

			<SiteDefaults />

			{/* <Social
				availableTokens={availableTokens}
				templateData={templateData}
				update={update}
			/>

			<Images templateData={templateData} update={update} /> */}
		</>
	);
}
