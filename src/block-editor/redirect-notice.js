/**
 * Redirect Notice
 *
 * Displays a snackbar notice in the block editor when a slug change
 * triggered an automatic redirect via Safe Redirect Manager.
 * Includes an undo action to delete the redirect.
 *
 * Because the block editor saves via REST API without a page reload,
 * we subscribe to the editor save state and poll a REST endpoint after
 * each successful save to check for redirect data.
 */

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { dispatch, subscribe, select } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { store as editorStore } from '@wordpress/editor';
import apiFetch from '@wordpress/api-fetch';

/**
 * Display the redirect notice snackbar with an undo action.
 *
 * @param {Object} data           Redirect data from the REST endpoint.
 * @param {number} data.redirectId The SRM redirect_rule post ID.
 * @param {string} data.fromUrl    Old URL path.
 * @param {string} data.toUrl      New URL path.
 * @param {string} data.deleteUrl  REST URL to delete the redirect.
 */
function showRedirectNotice({ redirectId, fromUrl, toUrl, deleteUrl }) {
	const message = `${__('A 301 redirect was created:', 'prc-schema-seo')} ${fromUrl} → ${toUrl}`;

	dispatch(noticesStore).createNotice('success', message, {
		type: 'snackbar',
		isDismissible: true,
		actions: [
			{
				label: __("Don't create redirect", 'prc-schema-seo'),
				onClick: () => {
					apiFetch({
						url: deleteUrl,
						method: 'DELETE',
					})
						.then(() => {
							dispatch(noticesStore).createNotice(
								'info',
								__('Redirect removed.', 'prc-schema-seo'),
								{
									type: 'snackbar',
									isDismissible: true,
								}
							);
						})
						.catch(() => {
							dispatch(noticesStore).createNotice(
								'error',
								__(
									'Could not remove redirect.',
									'prc-schema-seo'
								),
								{
									type: 'snackbar',
									isDismissible: true,
								}
							);
						});
				},
			},
		],
	});
}

/**
 * Check the REST endpoint for a pending redirect notice for the given post.
 *
 * @param {number} postId The post ID to check.
 */
function checkForRedirectNotice(postId) {
	if (!postId) {
		return;
	}

	apiFetch({
		path: `/prc-schema-seo/v1/redirect-notice/${postId}`,
	})
		.then((response) => {
			if (response && response.hasRedirect) {
				showRedirectNotice(response);
			}
		})
		.catch(() => {
			// Silently ignore — the notice endpoint is non-critical.
		});
}

/**
 * Subscribe to the editor save lifecycle.
 *
 * Watches for transitions from "saving" to "not saving" and then
 * polls the REST endpoint for redirect notice data.
 */
function subscribeToSaveEvents() {
	let wasSaving = false;

	subscribe(() => {
		const isSaving = select(editorStore).isSavingPost();
		const isAutosaving = select(editorStore).isAutosavingPost();

		// We only care about manual saves, not autosaves.
		if (isSaving && !isAutosaving) {
			wasSaving = true;
		}

		// Transition from saving → done: check for redirect data.
		if (wasSaving && !isSaving) {
			wasSaving = false;

			const postId = select(editorStore).getCurrentPostId();
			if (postId) {
				// Small delay to ensure the transient has been written.
				setTimeout(() => checkForRedirectNotice(postId), 500);
			}
		}
	});
}

/**
 * Initialize the redirect notice system.
 *
 * Handles both:
 * 1. Initial page load (if localized data exists from wp_localize_script)
 * 2. Subsequent saves (via subscribe to editor state)
 */
export default function initRedirectNotice() {
	// Handle data from initial page load (e.g., if the user reloads after save).
	if (
		typeof window.PRCSchemaSEORedirect !== 'undefined' &&
		window.PRCSchemaSEORedirect.redirectId
	) {
		const { redirectId, fromUrl, toUrl, restUrl } =
			window.PRCSchemaSEORedirect;
		delete window.PRCSchemaSEORedirect;

		showRedirectNotice({
			redirectId,
			fromUrl,
			toUrl,
			deleteUrl: restUrl,
		});
	}

	// Subscribe to future saves.
	subscribeToSaveEvents();
}
