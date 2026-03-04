/**
 * External Dependencies
 */

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useCommand } from '@wordpress/commands';
import { useDispatch } from '@wordpress/data';
import { store as editPostStore } from '@wordpress/edit-post';
import { globe } from '@wordpress/icons';
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal Dependencies
 */
import './previews.css';
import { SidebarWrapper } from '../components';
import registerPrimaryTermInject from './primary-term-inject';
import registerReadingScore from './reading-score';
import Panel from './panel';
import initRedirectNotice from './redirect-notice';

const PLUGIN_NAME = 'prc-schema-seo-settings';

function PRCSchemaSEOPlugin() {
	const { openGeneralSidebar } = useDispatch(editPostStore);

	useCommand({
		name: 'prc/show-search-and-social',
		label: __('Show Search and Social', 'prc-schema-seo'),
		icon: globe,
		category: 'view',
		keywords: ['seo', 'meta', 'search', 'social'],
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

registerReadingScore();

registerPrimaryTermInject();

// Initialize redirect notice system (subscribes to save events).
initRedirectNotice();
