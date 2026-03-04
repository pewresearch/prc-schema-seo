/**
 * SEO Meta Tags Tests
 *
 * Tests for verifying that SEO meta tags are correctly output
 * on the frontend of posts and pages.
 *
 */

import {
	test,
	expect,
	type RequestUtils,
} from '@wordpress/e2e-test-utils-playwright';

/**
 * Helper to delete a post via REST API.
 * Note: requestUtils.deletePost doesn't exist in @wordpress/e2e-test-utils-playwright
 * @param requestUtils
 * @param postId
 */
async function deletePost(
	requestUtils: RequestUtils,
	postId: number
): Promise<void> {
	await requestUtils.rest({
		method: 'DELETE',
		path: `/wp/v2/posts/${postId}`,
		params: { force: true },
	});
}

/**
 * Helper to delete a page via REST API.
 * Note: requestUtils.deletePage isn't exported in @wordpress/e2e-test-utils-playwright
 * @param requestUtils
 * @param pageId
 */
async function deletePage(
	requestUtils: RequestUtils,
	pageId: number
): Promise<void> {
	await requestUtils.rest({
		method: 'DELETE',
		path: `/wp/v2/pages/${pageId}`,
		params: { force: true },
	});
}

test.describe('SEO Meta Tags', () => {
	let testPostId: number;

	test.beforeAll(async ({ requestUtils }) => {
		// Create a test post to use across tests
		const post = await requestUtils.createPost({
			title: 'SEO Meta Tags Test Post',
			content:
				'This is test content for SEO meta tag validation. It should be long enough to generate a proper excerpt for the meta description fallback.',
			status: 'publish',
		});
		testPostId = post.id;
	});

	test.afterAll(async ({ requestUtils }) => {
		// Clean up test post
		if (testPostId) {
			await deletePost(requestUtils, testPostId);
		}
	});

	test('Post has title tag', async ({ page, requestUtils }) => {
		const post = await requestUtils.rest({
			path: `/wp/v2/posts/${testPostId}`,
			method: 'GET',
		});

		await page.goto(post.link);

		// Verify title tag exists
		const title = await page.locator('title').textContent();
		expect(title).toBeTruthy();
		expect(title).toContain('SEO Meta Tags Test Post');
	});

	test('Post has meta description', async ({ page, requestUtils }) => {
		const post = await requestUtils.rest({
			path: `/wp/v2/posts/${testPostId}`,
			method: 'GET',
		});

		await page.goto(post.link);

		// Check for meta description tag
		const descriptionMeta = page.locator('meta[name="description"]');
		await expect(descriptionMeta).toHaveCount(1);

		const description = await descriptionMeta.getAttribute('content');
		expect(description).toBeTruthy();
	});

	test('Post has canonical URL', async ({ page, requestUtils }) => {
		const post = await requestUtils.rest({
			path: `/wp/v2/posts/${testPostId}`,
			method: 'GET',
		});

		await page.goto(post.link);

		// Check for canonical link
		const canonicalLink = page.locator('link[rel="canonical"]');
		await expect(canonicalLink).toHaveCount(1);

		const href = await canonicalLink.getAttribute('href');
		expect(href).toBeTruthy();
		expect(href).toContain(post.link.replace(/^https?:\/\/[^/]+/, ''));
	});

	test('Post allows indexing by default', async ({ page, requestUtils }) => {
		const post = await requestUtils.rest({
			path: `/wp/v2/posts/${testPostId}`,
			method: 'GET',
		});

		await page.goto(post.link);

		// Check for robots meta tag (may not exist for indexable content)
		const robotsMeta = page.locator('meta[name="robots"]');
		const count = await robotsMeta.count();

		if (count > 0) {
			// If robots tag exists, verify it doesn't contain noindex
			const content = await robotsMeta.getAttribute('content');
			expect(content).toBeTruthy();
			expect(content).not.toContain('noindex');
		}
		// No robots tag = default WordPress behavior = indexable
	});

	test('Post has Open Graph tags', async ({ page, requestUtils }) => {
		const post = await requestUtils.rest({
			path: `/wp/v2/posts/${testPostId}`,
			method: 'GET',
		});

		await page.goto(post.link);

		// Check for required OG tags
		const ogTitle = page.locator('meta[property="og:title"]');
		await expect(ogTitle).toHaveCount(1);

		const ogDescription = page.locator('meta[property="og:description"]');
		await expect(ogDescription).toHaveCount(1);

		const ogUrl = page.locator('meta[property="og:url"]');
		await expect(ogUrl).toHaveCount(1);

		const ogType = page.locator('meta[property="og:type"]');
		await expect(ogType).toHaveCount(1);

		// Verify og:type is 'article' for posts
		const ogTypeContent = await ogType.getAttribute('content');
		expect(ogTypeContent).toBe('article');
	});

	test('Post has Twitter Card tags', async ({ page, requestUtils }) => {
		const post = await requestUtils.rest({
			path: `/wp/v2/posts/${testPostId}`,
			method: 'GET',
		});

		await page.goto(post.link);

		// Check for Twitter card tag
		const twitterCard = page.locator('meta[name="twitter:card"]');
		await expect(twitterCard).toHaveCount(1);

		const cardType = await twitterCard.getAttribute('content');
		expect(['summary', 'summary_large_image']).toContain(cardType);
	});

	test('Custom SEO title overrides default', async ({
		page,
		requestUtils,
	}) => {
		// Create a post with custom SEO title
		const customTitle = 'Custom SEO Title for Testing';
		const post = await requestUtils.createPost({
			title: 'Original Post Title',
			content: 'Test content',
			status: 'publish',
		});

		try {
			// Update post meta with custom SEO data via REST using the registered REST field
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						title: customTitle,
					},
				},
			});

			await page.goto(post.link);

			const title = await page.locator('title').textContent();
			expect(title).toContain(customTitle);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Custom meta description is used when set', async ({
		page,
		requestUtils,
	}) => {
		const customDescription =
			'This is a custom meta description set for SEO testing purposes.';
		const post = await requestUtils.createPost({
			title: 'Post With Custom Description',
			content:
				'Default content that should not appear in meta description.',
			status: 'publish',
		});

		try {
			// Update post meta with custom SEO data using the registered REST field
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						description: customDescription,
					},
				},
			});

			await page.goto(post.link);

			const descriptionMeta = page.locator('meta[name="description"]');
			const description = await descriptionMeta.getAttribute('content');
			expect(description).toBe(customDescription);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Custom OG title and description override defaults', async ({
		page,
		requestUtils,
	}) => {
		const ogTitle = 'Custom OG Title for Social Sharing';
		const ogDescription =
			'Custom OG description optimized for social media sharing.';

		const post = await requestUtils.createPost({
			title: 'Post With Custom OG Data',
			content: 'Test content',
			status: 'publish',
		});

		try {
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						og_title: ogTitle,
						og_description: ogDescription,
					},
				},
			});

			await page.goto(post.link);

			const ogTitleMeta = page.locator('meta[property="og:title"]');
			const ogTitleContent = await ogTitleMeta.getAttribute('content');
			expect(ogTitleContent).toBe(ogTitle);

			const ogDescMeta = page.locator('meta[property="og:description"]');
			const ogDescContent = await ogDescMeta.getAttribute('content');
			expect(ogDescContent).toBe(ogDescription);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Custom canonical URL is respected', async ({
		page,
		requestUtils,
	}) => {
		const customCanonical = 'https://www.example.com/custom-canonical-url/'; // pragma: allowlist secret

		const post = await requestUtils.createPost({
			title: 'Post With Custom Canonical',
			content: 'Test content',
			status: 'publish',
		});

		try {
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						canonical_url: customCanonical,
					},
				},
			});

			await page.goto(post.link);

			const canonicalLink = page.locator('link[rel="canonical"]');
			const href = await canonicalLink.getAttribute('href');
			expect(href).toBe(customCanonical);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});

test.describe('Page Meta Tags', () => {
	test('Page has correct og:type', async ({ page, requestUtils }) => {
		// Create a page (not a post)
		const testPage = await requestUtils.createPage({
			title: 'Test Page for OG Type',
			content: 'Page content for testing.',
			status: 'publish',
		});

		try {
			await page.goto(testPage.link);

			const ogType = page.locator('meta[property="og:type"]');
			await expect(ogType).toHaveCount(1);

			const ogTypeContent = await ogType.getAttribute('content');
			// Pages should have 'website' type, not 'article'
			expect(ogTypeContent).toBe('website');
		} finally {
			await deletePage(requestUtils, testPage.id);
		}
	});
});
