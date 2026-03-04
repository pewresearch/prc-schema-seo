/**
 * Redirect CSV Import
 *
 * Injects an "Import CSV" button into the Safe Redirect Manager list table
 * and handles file upload via AJAX -> srm_import_file().
 *
 * @package
 */

/* global prcSeoRedirectImport */

/**
 * Create and insert a WordPress-style admin notice.
 *
 * @param {string} message Notice text (may contain HTML).
 * @param {string} type    One of 'success', 'error', 'warning', 'info'.
 */
function showNotice(message, type = 'success') {
	const existing = document.querySelector('.prc-seo-import-notice');
	if (existing) {
		existing.remove();
	}

	const wrap = document.querySelector('.wrap');
	if (!wrap) {
		return;
	}

	const notice = document.createElement('div');
	notice.className = `notice notice-${type} is-dismissible prc-seo-import-notice`;
	notice.innerHTML = `<p>${message}</p>`;

	// Insert after the page title heading.
	const heading = wrap.querySelector('h1, .wp-heading-inline');
	if (heading && heading.nextSibling) {
		wrap.insertBefore(notice, heading.nextSibling);
	} else {
		wrap.prepend(notice);
	}

	// Wire up the dismiss button if WP adds one.
	const dismissBtn = notice.querySelector('.notice-dismiss');
	if (dismissBtn) {
		dismissBtn.addEventListener('click', () => notice.remove());
	}
}

/**
 * Upload the selected CSV file to the AJAX handler.
 *
 * @param {File}        file   The CSV file to upload.
 * @param {HTMLElement} button The import button (for loading state).
 */
function uploadCSV(file, button) {
	const originalText = button.textContent;
	button.textContent = 'Importing\u2026';
	button.disabled = true;

	const formData = new FormData();
	formData.append('action', prcSeoRedirectImport.action);
	formData.append('_ajax_nonce', prcSeoRedirectImport.nonce);
	formData.append('csv_file', file);

	fetch(prcSeoRedirectImport.ajax_url, {
		method: 'POST',
		credentials: 'same-origin',
		body: formData,
	})
		.then((response) => response.json())
		.then((data) => {
			if (data.success) {
				const { created, skipped } = data.data;
				showNotice(
					`Import complete: <strong>${created}</strong> redirect(s) created, <strong>${skipped}</strong> skipped.`,
					'success'
				);
				// Reload after a short delay so the user can read the notice.
				setTimeout(() => window.location.reload(), 1500);
			} else {
				const msg =
					data.data && data.data.message
						? data.data.message
						: 'An unknown error occurred.';
				showNotice(`Import failed: ${msg}`, 'error');
			}
		})
		.catch((err) => {
			showNotice(
				`Import failed: ${err.message || 'Network error.'}`,
				'error'
			);
		})
		.finally(() => {
			button.textContent = originalText;
			button.disabled = false;
		});
}

/**
 * Initialize the import UI on the SRM list table page.
 */
function init() {
	// Find the "Add New Redirect" link.
	const addNewLink = document.querySelector('.wrap .page-title-action');
	if (!addNewLink) {
		return;
	}

	// Create a hidden file input.
	const fileInput = document.createElement('input');
	fileInput.type = 'file';
	fileInput.accept = '.csv';
	fileInput.style.display = 'none';
	document.body.appendChild(fileInput);

	// Create the "Import CSV" button.
	const importBtn = document.createElement('a');
	importBtn.href = '#';
	importBtn.className = 'page-title-action';
	importBtn.textContent = 'Import CSV';
	addNewLink.insertAdjacentElement('afterend', importBtn);

	// Wire up interactions.
	importBtn.addEventListener('click', (e) => {
		e.preventDefault();
		fileInput.value = ''; // Reset so re-selecting the same file works.
		fileInput.click();
	});

	fileInput.addEventListener('change', () => {
		const file = fileInput.files[0];
		if (!file) {
			return;
		}

		if (!file.name.toLowerCase().endsWith('.csv')) {
			showNotice('Please select a .csv file.', 'error');
			return;
		}

		uploadCSV(file, importBtn);
	});
}

// Run when the DOM is ready.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
