/**
 * WordPress Dependencies
 */
import { DataForm } from '@wordpress/dataviews';
import {
	Button,
	Notice,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useCallback, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * External Dependencies
 */
import { WPEntitySearch } from '@prc/components';

/**
 * Internal Dependencies
 */
import useUpdateField from './use-update-field';

// Stable reference: WPEntitySearch re-fetches when entityStatus identity changes.
const PUBLISH_STATUS = ['publish'];

function getSeoBoot() {
	return window?.prcWpAdminDataview?.seo || {};
}

function draftFromItem(item) {
	return {
		id: item?.id,
		seoTitle: item?.seoTitle || '',
		seoDescription: item?.seoDescription || '',
		noindex: !!item?.noindex,
		schemaType: item?.schemaType || '',
		primaryTermId: item?.primaryTermId ? Number(item.primaryTermId) : 0,
		primaryTerm: item?.primaryTerm || '',
		primaryTermTaxonomy:
			item?.primaryTermTaxonomy ||
			getSeoBoot().primaryTermTaxonomy ||
			'category',
	};
}

function PrimaryTermEdit({ data, onChange }) {
	const taxonomy =
		data?.primaryTermTaxonomy ||
		getSeoBoot().primaryTermTaxonomy ||
		'category';

	const handleSelect = useCallback(
		(entity) => {
			onChange({
				primaryTermId: Number(entity.entityId) || 0,
				primaryTerm: entity.entityName || '',
			});
		},
		[onChange]
	);

	return (
		<>
			{data?.primaryTerm ? (
				<p>
					{sprintf(
						/* translators: %s: selected taxonomy term name */
						__('Selected: %s', 'prc-schema-seo'),
						data.primaryTerm
					)}
				</p>
			) : null}
			<WPEntitySearch
				placeholder={__('Search for a term…', 'prc-schema-seo')}
				entityType="taxonomy"
				entitySubType={taxonomy}
				entityId={data?.primaryTermId || false}
				entityStatus={PUBLISH_STATUS}
				searchValue=""
				clearOnSelect
				showType={false}
				showUrl={false}
				onSelect={handleSelect}
			/>
		</>
	);
}

function getFormFields() {
	const schemaElements = (getSeoBoot().schemaTypeOptions || []).map(
		(option) => ({
			value: option.value,
			label: option.label,
		})
	);

	return [
		{
			id: 'seoTitle',
			type: 'text',
			label: __('SEO Title', 'prc-schema-seo'),
		},
		{
			id: 'seoDescription',
			type: 'text',
			label: __('Meta Description', 'prc-schema-seo'),
			Edit: {
				control: 'textarea',
				rows: 4,
			},
		},
		{
			id: 'noindex',
			type: 'boolean',
			label: __('Noindex', 'prc-schema-seo'),
			Edit: 'toggle',
		},
		{
			id: 'schemaType',
			type: 'text',
			label: __('Schema Type', 'prc-schema-seo'),
			elements: schemaElements,
		},
		{
			id: 'primaryTermId',
			label: __('Primary Term', 'prc-schema-seo'),
			getValue: ({ item }) => item.primaryTermId || 0,
			render: ({ item }) => item.primaryTerm || '—',
			Edit: PrimaryTermEdit,
		},
	];
}

const FORM = {
	layout: {
		type: 'regular',
		labelPosition: 'top',
	},
	fields: [
		'seoTitle',
		'seoDescription',
		'noindex',
		'schemaType',
		'primaryTermId',
	],
};

function collectDirtyUpdates(initial, values) {
	const updates = [];

	if ((initial.seoTitle || '') !== (values.seoTitle || '')) {
		updates.push({ field: 'seoTitle', value: values.seoTitle || '' });
	}
	if ((initial.seoDescription || '') !== (values.seoDescription || '')) {
		updates.push({
			field: 'seoDescription',
			value: values.seoDescription || '',
		});
	}
	if (!!initial.noindex !== !!values.noindex) {
		updates.push({ field: 'noindex', value: !!values.noindex });
	}
	if ((initial.schemaType || '') !== (values.schemaType || '')) {
		updates.push({ field: 'schemaType', value: values.schemaType || '' });
	}
	if (
		Number(initial.primaryTermId || 0) !== Number(values.primaryTermId || 0)
	) {
		updates.push({
			field: 'primaryTerm',
			value: values.primaryTermId ? Number(values.primaryTermId) : '',
		});
	}

	return updates;
}

export default function SeoEditForm({ initialValues, onCancel, onSaved }) {
	const initial = useMemo(
		() => draftFromItem(initialValues),
		[initialValues]
	);
	const [values, setValues] = useState(initial);
	const [error, setError] = useState(null);
	const { updateField, isSaving } = useUpdateField({ quiet: true });
	const { createNotice } = useDispatch(noticesStore);
	const formFields = useMemo(() => getFormFields(), []);

	const handleSubmit = async () => {
		const updates = collectDirtyUpdates(initial, values);
		if (!updates.length) {
			onCancel?.();
			return;
		}

		setError(null);
		try {
			for (const update of updates) {
				await updateField(values.id, update.field, update.value);
			}
			createNotice('success', __('SEO fields saved.', 'prc-schema-seo'), {
				type: 'snackbar',
			});
			onSaved?.(values);
		} catch (err) {
			const message =
				err?.message ||
				__('Could not save SEO fields.', 'prc-schema-seo');
			setError(message);
			createNotice('error', message, { type: 'snackbar' });
		}
	};

	return (
		<VStack spacing={4}>
			{error ? (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			) : null}
			<DataForm
				data={values}
				fields={formFields}
				form={FORM}
				onChange={(edits) =>
					setValues((current) => ({ ...current, ...edits }))
				}
			/>
			<HStack justify="flex-end" spacing={2}>
				<Button
					variant="tertiary"
					onClick={onCancel}
					disabled={isSaving}
				>
					{__('Cancel', 'prc-schema-seo')}
				</Button>
				<Button
					variant="primary"
					onClick={handleSubmit}
					isBusy={isSaving}
					disabled={isSaving}
				>
					{__('Save', 'prc-schema-seo')}
				</Button>
			</HStack>
		</VStack>
	);
}
