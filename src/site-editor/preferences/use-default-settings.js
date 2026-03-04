/**
 * WordPress Dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useMemo } from '@wordpress/element';

/**
 * useDefaultSettings
 * Hook for managing three-tier default fallback SEO settings.
 * Reads/writes to prc_schema_seo_templates.default_site, default_singular, default_archive
 * @return {Object} Settings data and methods
 */
export default function useDefaultSettings() {
	const { editEntityRecord, saveEditedEntityRecord } = useDispatch('core');

	const settingsRecord = useSelect(
		(select) => {
			// IMPORTANT: Use edited entity record so we see unsaved, in-progress changes.
			// Using getEntityRecord returns the last persisted version which causes the
			// controlled inputs to reset on each keypress (only showing one character).
			// getEditedEntityRecord includes the optimistic updates produced by editEntityRecord.
			return (
				select('core').getEditedEntityRecord('root', 'site') ||
				select('core').getEntityRecord('root', 'site')
			);
		},
		[]
	);

	const defaultData = useMemo(() => {
		if (!settingsRecord) {
			return {
				defaultSite: {
					title_pattern: '',
					description_pattern: '',
				},
				defaultSingular: {
					title_pattern: '',
					description_pattern: '',
				},
				defaultArchive: {
					title_pattern: '',
					description_pattern: '',
				},
			};
		}

		// Read from nested structure: settingsRecord.prc_schema_seo_templates
		const allTemplates = settingsRecord.prc_schema_seo_templates || {};

		const getDefaultData = (key) => {
			const data = allTemplates[key];
			if (!data || typeof data !== 'object') {
				return {
					title_pattern: '',
					description_pattern: '',
				};
			}
			return {
				title_pattern: data.title_pattern || '',
				description_pattern: data.description_pattern || '',
			};
		};

		return {
			defaultSite: getDefaultData('default_site'),
			defaultSingular: getDefaultData('default_singular'),
			defaultArchive: getDefaultData('default_archive'),
		};
	}, [settingsRecord]);

	const update = (defaultType, field, value) => {
		const key = `default_${defaultType}`; // defaultType is 'site', 'singular', or 'archive'
		const currentData = defaultData[`default${defaultType.charAt(0).toUpperCase() + defaultType.slice(1)}`];
		const updatedData = { ...currentData, [field]: value };

		// Get current templates object
		const currentTemplates = settingsRecord.prc_schema_seo_templates || {};

		// Merge with updated default data
		const newTemplates = {
			...currentTemplates,
			[key]: updatedData,
		};

		editEntityRecord('root', 'site', undefined, {
			prc_schema_seo_templates: newTemplates,
		});
	};

	const save = async () => {
		await saveEditedEntityRecord('root', 'site');
	};

	return { defaultData, update, save };
}
