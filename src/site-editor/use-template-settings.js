/**
 * WordPress Dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useMemo } from '@wordpress/element';

/**
 * useTemplateSettings
 * Hook for managing template-specific SEO settings.
 * @param {Object} context Template context
 * @return {Object} Settings data and methods
 */
export default function useTemplateSettings(context) {
	const { editEntityRecord, saveEditedEntityRecord } = useDispatch('core');

	const settingsRecord = useSelect(
		(select) => {
			if (!context?.key) return null;
			// IMPORTANT: Use edited entity record so we see unsaved, in-progress changes.
			// Using getEntityRecord returns the last persisted version which causes the
			// controlled inputs to reset on each keypress (only showing one character).
			// getEditedEntityRecord includes the optimistic updates produced by editEntityRecord.
			return (
				select('core').getEditedEntityRecord('root', 'site') ||
				select('core').getEntityRecord('root', 'site')
			);
		},
		[context]
	);

	const templateData = useMemo(() => {
		if (!context?.key || !settingsRecord) {
			return {
				title_pattern: '',
				description_pattern: '',
				schema_type: '',
				noindex: false,
				og_image: 0,
				twitter_image: 0,
			};
		}

		// Read from nested structure: settingsRecord.prc_schema_seo_templates[context.key]
		const allTemplates = settingsRecord.prc_schema_seo_templates || {};
		const data = allTemplates[context.key];

		if (!data || typeof data !== 'object') {
			return {
				title_pattern: '',
				description_pattern: '',
				schema_type: '',
				noindex: false,
				og_image: 0,
				twitter_image: 0,
			};
		}

		return {
			title_pattern: data.title_pattern || '',
			description_pattern: data.description_pattern || '',
			schema_type: data.schema_type || '',
			noindex: data.noindex || false,
			og_image: data.og_image || 0,
			twitter_image: data.twitter_image || 0,
		};
	}, [context, settingsRecord]);

	const update = (field, value) => {
		if (!context?.key) return;

		const updatedData = { ...templateData, [field]: value };

		// Get current templates object
		const currentTemplates = settingsRecord.prc_schema_seo_templates || {};

		// Merge with updated template data
		const newTemplates = {
			...currentTemplates,
			[context.key]: updatedData,
		};

		editEntityRecord('root', 'site', undefined, {
			prc_schema_seo_templates: newTemplates,
		});
	};

	const save = async () => {
		await saveEditedEntityRecord('root', 'site');
	};

	return { templateData, update, save };
}
