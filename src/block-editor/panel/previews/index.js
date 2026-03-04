/* eslint-disable max-lines-per-function */
/**
 * External Dependencies
 */
import { Icon } from '@prc/icons';
import {
	GooglePreview,
	FacebookPreview,
	LinkedInPreview,
	TwitterPreview,
	ThreadsPreview,
	BlueskyPreview,
	SlackPreview,
	DiscordPreview,
	TeamsPreview,
} from '@prc/components';

/**
 * WordPress Dependencies
 */
import {
	Modal,
	TabPanel,
	Flex,
	FlexItem,
	FlexBlock,
} from '@wordpress/components';

/**
 * Internal Dependencies
 */
import { derivePreviewData } from './preview-utils';

/**
 * PreviewsModal
 * Modal dialog with tabbed platform previews that visually match real social networks.
 *
 * @param {{seoData: import('..').SEOData, post: import('..').PostRecord, onClose: Function}} props Props.
 * @return {JSX.Element} Modal with platform tabs.
 */
export default function PreviewsModal({ seoData, post, onClose }) {
	const siteUrl = window?.wp?.siteUrl || window.location.origin;
	const data = derivePreviewData(seoData, post);
	const previewUrl = seoData?.canonical_url || post?.link || siteUrl;

	const tabs = [
		{
			name: 'google',
			title: (
				<Flex>
					<FlexItem>
						<Icon library="brands" icon="google" size="1em" />
					</FlexItem>
					<FlexBlock>
						<span>Google</span>
					</FlexBlock>
				</Flex>
			),
		},
		{
			name: 'facebook',
			title: (
				<Flex>
					<FlexItem>
						<Icon
							library="brands"
							icon="facebook"
							size="1em"
							color="#1877F2"
						/>
					</FlexItem>
					<FlexBlock>
						<span>Facebook</span>
					</FlexBlock>
				</Flex>
			),
		},
		{
			name: 'linkedin',
			title: (
				<Flex>
					<FlexItem>
						<Icon
							library="brands"
							icon="linkedin"
							size="1em"
							color="#0A66C2"
						/>
					</FlexItem>
					<FlexBlock>
						<span>LinkedIn</span>
					</FlexBlock>
				</Flex>
			),
		},
		{
			name: 'twitter',
			title: (
				<Flex>
					<FlexItem>
						<Icon library="brands" icon="x-twitter" size="1em" />
					</FlexItem>
					<FlexBlock>
						<span>Twitter</span>
					</FlexBlock>
				</Flex>
			),
		},
		{
			name: 'threads',
			title: (
				<Flex>
					<FlexItem>
						<Icon library="brands" icon="threads" size="1em" />
					</FlexItem>
					<FlexBlock>
						<span>Threads</span>
					</FlexBlock>
				</Flex>
			),
		},
		{
			name: 'bluesky',
			title: (
				<Flex>
					<FlexItem>
						<Icon library="brands" icon="bluesky" size="1em" />
					</FlexItem>
					<FlexBlock>
						<span>Bluesky</span>
					</FlexBlock>
				</Flex>
			),
		},
		{
			name: 'slack',
			title: (
				<Flex>
					<FlexItem>
						<Icon library="brands" icon="slack" size="1em" />
					</FlexItem>
					<FlexBlock>
						<span>Slack</span>
					</FlexBlock>
				</Flex>
			),
		},
		{
			name: 'teams',
			title: (
				<Flex>
					<FlexItem>
						<Icon library="brands" icon="microsoft" size="1em" />
					</FlexItem>
					<FlexBlock>
						<span>Teams</span>
					</FlexBlock>
				</Flex>
			),
		},
		{
			name: 'discord',
			title: (
				<Flex>
					<FlexItem>
						<Icon library="brands" icon="discord" size="1em" />
					</FlexItem>
					<FlexBlock>
						<span>Discord</span>
					</FlexBlock>
				</Flex>
			),
		},
	];

	return (
		<Modal
			title="Social & Search Previews"
			onRequestClose={onClose}
			className="prc-schema-seo-preview-modal"
			style={{ maxWidth: '1100px', width: '100%' }}
		>
			<TabPanel
				className="prc-preview-modal-tabs"
				orientation="vertical"
				tabs={tabs}
			>
				{(tab) => {
					if (tab.name === 'google')
						return (
							<GooglePreview
								title={data.title}
								description={data.description}
								url={previewUrl}
								image={data.ogImage}
								siteName="Pew Research Center"
							/>
						);
					if (tab.name === 'facebook')
						return (
							<FacebookPreview
								title={data.ogTitle}
								description={data.ogDescription}
								url={previewUrl}
								image={data.ogImage}
								displayName="Pew Research Center"
								postText="Lorem ipsum dolor sit amet, consectetur adipiscing elit."
								timestamp="10m"
								verified={true}
								reactions={100}
								comments={10}
								shares={10}
							/>
						);
					if (tab.name === 'linkedin')
						return (
							<LinkedInPreview
								title={data.ogTitle}
								description={data.ogDescription}
								url={previewUrl}
								image={data.ogImage}
								siteName="Pew Research Center"
								displayName="Pew Research Center"
								followers="100,000 followers"
								postText="Lorem ipsum dolor sit amet, consectetur adipiscing elit."
								timestamp="10m"
								shortUrl={previewUrl}
								reactions={100}
								comments={10}
								reposts={10}
							/>
						);
					if (tab.name === 'twitter')
						return (
							<TwitterPreview
								title={data.ogTitle}
								description={data.ogDescription}
								url={previewUrl}
								image={data.ogImage}
								siteName="Pew Research Center"
								displayName="Pew Research Center"
								username="pewresearch"
								tweetText="Lorem ipsum dolor sit amet, consectetur adipiscing elit."
								verified={true}
								timestamp="10m"
								likes={100}
								replies={10}
							/>
						);
					if (tab.name === 'threads')
						return (
							<ThreadsPreview
								title={data.ogTitle}
								description={data.ogDescription}
								url={previewUrl}
								image={data.ogImage}
								siteName="Pew Research Center"
							/>
						);
					if (tab.name === 'bluesky')
						return (
							<BlueskyPreview
								title={data.ogTitle}
								description={data.ogDescription}
								url={previewUrl}
								image={data.ogImage}
								siteName="Pew Research Center"
								displayName="Pew Research Center"
								handle="pewresearch.org"
								postText="Lorem ipsum dolor sit amet, consectetur adipiscing elit."
								verified={true}
								timestamp="10m"
								likes={100}
								reposts={10}
								quotes={10}
								replies={10}
								saves={10}
							/>
						);
					if (tab.name === 'slack')
						return (
							<SlackPreview
								title={data.ogTitle}
								description={data.ogDescription}
								url={previewUrl}
								image={data.ogImage}
								siteName="Pew Research Center"
								displayName="Pew Research Center"
								messageText="Lorem ipsum dolor sit amet, consectetur adipiscing elit."
								timestamp="10m"
								readingTime="5 minutes"
								author="John Doe"
							/>
						);
					if (tab.name === 'discord')
						return (
							<DiscordPreview
								title={data.ogTitle}
								description={data.ogDescription}
								url={previewUrl}
								image={data.ogImage}
								siteName="Pew Research Center"
							/>
						);
					if (tab.name === 'teams')
						return (
							<TeamsPreview
								title={data.ogTitle}
								description={data.ogDescription}
								url={previewUrl}
								image={data.ogImage}
								favicon={data.ogImage}
								siteName="Pew Research Center"
							/>
						);
					return null;
				}}
			</TabPanel>
		</Modal>
	);
}
