/**
 * Global Setup for Playwright Tests
 *
 * Runs before all tests to configure WordPress settings:
 * - Sets permalinks to "Day and name" format
 */

import { request, chromium } from '@playwright/test';
import type { FullConfig } from '@playwright/test';
import {
	RequestUtils,
	Admin,
	PageUtils,
	Editor,
} from '@wordpress/e2e-test-utils-playwright';

async function globalSetup(config: FullConfig) {
	const { storageState, baseURL } = config.projects[0].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext({
		baseURL,
	});

	const requestUtils = new RequestUtils(requestContext, {
		storageStatePath,
	});

	// Authenticate and save the storageState to disk
	await requestUtils.setupRest();

	// Launch a browser for admin page operations
	const browser = await chromium.launch();
	const browserContext = await browser.newContext({
		baseURL,
		storageState: storageStatePath,
	});
	const page = await browserContext.newPage();

	try {
		// Create required dependencies for Admin
		const pageUtils = new PageUtils({ page, browserName: 'chromium' });
		const editor = new Editor({ page });
		const admin = new Admin({ page, pageUtils, editor });

		// Check current permalink structure
		const currentStructure = await requestUtils.rest({
			path: '/wp/v2/settings',
			method: 'GET',
		});

		const targetStructure = '/%year%/%monthnum%/%day%/%postname%/';

		// Only set if not already set to target structure
		if (currentStructure.permalink_structure !== targetStructure) {
			// Visit permalink settings page
			await admin.visitAdminPage('options-permalink.php');

			// Wait for the form to be visible
			await page.waitForSelector('form[action="options-permalink.php"]', {
				timeout: 10000,
			});

			// Select "Day and name" radio button
			// The value for "Day and name" is /%year%/%monthnum%/%day%/%postname%/
			const dayAndNameRadio = page.locator(
				'input[type="radio"][value="/%year%/%monthnum%/%day%/%postname%/"]'
			);

			// If radio button exists, click it
			const radioCount = await dayAndNameRadio.count();
			if (radioCount > 0) {
				await dayAndNameRadio.click();
			} else {
				// Fallback: set the permalink_structure field directly
				const structureField = page.locator(
					'input[name="permalink_structure"]'
				);
				if ((await structureField.count()) > 0) {
					await structureField.fill(targetStructure);
				}
			}

			// Submit the form
			const submitButton = page.locator(
				'form[action="options-permalink.php"] input[type="submit"]'
			);
			await submitButton.click();

			// Wait for the page to reload/redirect after save
			await page.waitForLoadState('networkidle');

			// Verify the change was saved
			const updatedStructure = await requestUtils.rest({
				path: '/wp/v2/settings',
				method: 'GET',
			});

			if (updatedStructure.permalink_structure !== targetStructure) {
				// eslint-disable-next-line no-console
				console.warn(
					'Warning: Permalink structure may not have been set correctly.'
				);
			}
		}
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Error setting permalink structure:', error);
		// Don't throw - allow tests to continue even if permalink setup fails
	} finally {
		await page.close();
		await browserContext.close();
		await browser.close();
	}

	await requestContext.dispose();
}

export default globalSetup;
