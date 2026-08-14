/**
 * WordPress Dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Persist a single SEO field via the shared admin-dataview REST endpoint.
 *
 * @param {Object}  [options]
 * @param {boolean} [options.quiet] When true, skip per-field snackbars and rethrow errors.
 */
export default function useUpdateField(options = {}) {
	const { quiet = false } = options;
	const [isSaving, setIsSaving] = useState(false);
	const { createNotice } = useDispatch(noticesStore);
	const postType = window?.prcWpAdminDataview?.postType || 'post';

	const updateField = useCallback(
		async (postId, field, value) => {
			setIsSaving(true);
			try {
				await apiFetch({
					path: '/prc-api/v3/wp-admin-dataview/field',
					method: 'POST',
					data: { postId, field, value, postType },
				});
				if (!quiet) {
					createNotice(
						'success',
						__('SEO field saved.', 'prc-schema-seo'),
						{ type: 'snackbar' }
					);
				}
			} catch (error) {
				if (!quiet) {
					createNotice(
						'error',
						error?.message ||
							__('Could not save SEO field.', 'prc-schema-seo'),
						{ type: 'snackbar' }
					);
				}
				throw error;
			} finally {
				setIsSaving(false);
			}
		},
		[postType, createNotice, quiet]
	);

	return { updateField, isSaving };
}
