import { defineConfig } from '@playwright/test';
import baseConfig from '@wordpress/scripts/config/playwright.config';
import { join } from 'path';

const testDir = './tests';

export default defineConfig({
	...baseConfig,
	testDir,
	globalSetup: join(__dirname, 'tests/global-setup.ts'),
	outputDir: './tests/artifacts/results',
	use: {
		...baseConfig.use,
		video: 'on',
		trace: 'on',
		ignoreHTTPSErrors: true,
	},
	reporter: [
		...baseConfig.reporter,
		['html', { outputFolder: './tests/artifacts/reports' }],
	],
});
