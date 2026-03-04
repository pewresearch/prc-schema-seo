/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useCommand } from '@wordpress/commands';
import { useDispatch } from '@wordpress/data';
import { store as editSiteStore } from '@wordpress/edit-site';
import { globe } from '@wordpress/icons';
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal Dependencies
 */
import { SidebarWrapper } from '../components';
import Panel from './panel';

const PLUGIN_NAME = 'prc-schema-seo-template-defaults';

function PRCSchemaSEOPlugin() {
	const { openGeneralSidebar } = useDispatch(editSiteStore);

	useCommand({
		name: 'prc/show-search-and-social-template-defaults',
		label: __('Show Search and Social Template Defaults', 'prc-schema-seo'),
		icon: globe,
		category: 'view',
		keywords: ['seo', 'meta', 'search', 'social', 'template', 'defaults'],
		callback: ({ close }) => {
			openGeneralSidebar(`${PLUGIN_NAME}/${PLUGIN_NAME}`);
			close();
		},
	});

	return (
		<>
			<SidebarWrapper id={PLUGIN_NAME}>
				<Panel />
			</SidebarWrapper>
		</>
	);
}

registerPlugin(PLUGIN_NAME, { render: PRCSchemaSEOPlugin });
