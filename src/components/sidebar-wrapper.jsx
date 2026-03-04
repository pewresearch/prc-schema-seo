/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';


/**
 * Internal Dependencies
 */
import Icon from './icon';

export default function SidebarWrapper({ children, id }) {
	return (
		<>
			<PluginSidebarMoreMenuItem
				target={id}
				icon={null}
			>
				{__('Search and Social', 'prc-schema-seo')}
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name={id}
				title={__('Search and Social', 'prc-schema-seo')}
				icon={<Icon />}
			>
				{children}
			</PluginSidebar>
		</>
	);
}
