/**
 * WordPress Dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

addFilter('prcWpAdminDataview.fields', 'prc-schema-seo/fields', (fields) => {
	if (!window?.prcWpAdminDataview?.seo?.enabled) {
		return fields;
	}

	const schemaElements = (
		window.prcWpAdminDataview.seo.schemaTypeOptions || []
	)
		.filter((option) => typeof option?.value === 'string')
		.map((option) => ({
			value: option.value,
			label: option.label,
		}));

	const noindexElements =
		window.prcWpAdminDataview.seo.noindexFilterOptions || [];

	const primaryTermElements = (
		window.prcWpAdminDataview.seo.primaryTermOptions || []
	)
		.filter((option) => typeof option?.value === 'string')
		.map((option) => ({
			value: option.value,
			label: option.label,
		}));

	return [
		...fields,
		{
			id: 'seoTitle',
			type: 'text',
			label: __('SEO Title', 'prc-schema-seo'),
			enableSorting: false,
			getValue: ({ item }) => item.seoTitle || '',
			render: ({ item }) => item.seoTitle || '—',
		},
		{
			id: 'seoDescription',
			type: 'text',
			label: __('Meta Description', 'prc-schema-seo'),
			enableSorting: false,
			Edit: {
				control: 'textarea',
				rows: 4,
			},
			getValue: ({ item }) => item.seoDescription || '',
			render: ({ item }) => {
				const text = item.seoDescription || '';
				if (!text) {
					return '—';
				}
				return text.length > 80 ? `${text.slice(0, 77)}...` : text;
			},
		},
		{
			id: 'noindex',
			label: __('Is Indexed', 'prc-schema-seo'),
			type: 'text',
			elements: noindexElements,
			filterBy: {
				operators: ['isAny'],
			},
			enableSorting: false,
			getValue: ({ item }) => (item.noindex ? 'noindexed' : 'indexed'),
			render: ({ item }) =>
				item.noindex
					? __('Noindex', 'prc-schema-seo')
					: __('Indexed', 'prc-schema-seo'),
		},
		{
			id: 'schemaType',
			label: __('Schema Type', 'prc-schema-seo'),
			type: 'text',
			elements: schemaElements,
			filterBy: {
				operators: ['isAny'],
			},
			enableSorting: false,
			getValue: ({ item }) => item.schemaType || '',
			render: ({ item }) => item.schemaType || '—',
		},
		{
			id: 'primaryTerm',
			label: __('Primary Term', 'prc-schema-seo'),
			type: 'text',
			readOnly: primaryTermElements.length === 0,
			elements: primaryTermElements,
			filterBy: {
				operators: ['isAny'],
			},
			enableSorting: false,
			getValue: ({ item }) =>
				item.primaryTermId ? String(item.primaryTermId) : '',
			render: ({ item }) => item.primaryTerm || '—',
		},
		{
			id: 'indexNowStatus',
			label: __('IndexNow', 'prc-schema-seo'),
			readOnly: true,
			enableSorting: false,
			getValue: ({ item }) => item.indexNowStatus || '',
			render: ({ item }) => item.indexNowStatus || '—',
		},
		{
			id: 'googleIndexStatus',
			label: __('Google Index', 'prc-schema-seo'),
			readOnly: true,
			enableSorting: false,
			getValue: ({ item }) => item.googleIndexStatus || '',
			render: ({ item }) => item.googleIndexStatus || '—',
		},
	];
});
