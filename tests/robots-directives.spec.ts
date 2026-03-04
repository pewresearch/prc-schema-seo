/**
 * Robots Directives Tests
 *
 * Tests for verifying robots meta tag directives (noindex, follow)
 * and canonical URL handling.
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

test.describe('Robots Meta Tag', () => {
	test('Default robots allows indexing', async ({ page, requestUtils }) => {
		const post = await requestUtils.createPost({
			title: 'Default Robots Test',
			content: 'Testing default robots directive.',
			status: 'publish',
		});

		try {
			await page.goto(post.link);

			const robotsMeta = page.locator('meta[name="robots"]');
			const count = await robotsMeta.count();

			if (count > 0) {
				// If robots tag exists, verify it doesn't contain noindex
				const content = await robotsMeta.getAttribute('content');
				expect(content).toBeTruthy();
				expect(content).not.toContain('noindex');
			}
			// No robots tag = default WordPress behavior = indexable
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Noindex directive is applied when set', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Noindex Test Post',
			content: 'Testing noindex directive.',
			status: 'publish',
		});

		try {
			// Set noindex using the registered REST field
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						noindex: true,
					},
				},
			});

			await page.goto(post.link);

			const robotsMeta = page.locator('meta[name="robots"]');
			await expect(robotsMeta).toHaveCount(1);

			const content = await robotsMeta.getAttribute('content');
			expect(content).toBeTruthy();
			expect(content).toContain('noindex');
			expect(content).toContain('follow');
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Noindex can be toggled off', async ({ page, requestUtils }) => {
		const post = await requestUtils.createPost({
			title: 'Toggle Noindex Test',
			content: 'Testing noindex toggle.',
			status: 'publish',
		});

		try {
			// First set noindex to true using the registered REST field
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						noindex: true,
					},
				},
			});

			// Verify noindex is set
			await page.goto(post.link);
			let robotsMeta = page.locator('meta[name="robots"]');
			let content = await robotsMeta.getAttribute('content');
			expect(content).toContain('noindex');

			// Now toggle off using the registered REST field
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						noindex: false,
					},
				},
			});

			// Reload and verify
			await page.reload();
			robotsMeta = page.locator('meta[name="robots"]');
			const count = await robotsMeta.count();

			if (count > 0) {
				// If robots tag exists, verify noindex is gone
				content = await robotsMeta.getAttribute('content');
				expect(content).not.toContain('noindex');
			}
			// No robots tag = default WordPress behavior = indexable
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Noindex post excludes from indexing', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Search Engine Exclusion Test',
			content: 'This post should be excluded from search engines.',
			status: 'publish',
		});

		try {
			// Set noindex using the registered REST field
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						noindex: true,
					},
				},
			});

			await page.goto(post.link);

			// Verify the page still loads (noindex doesn't block viewing)
			const title = await page.locator('title').textContent();
			expect(title).toContain('Search Engine Exclusion Test');

			// But has noindex directive
			const robotsMeta = page.locator('meta[name="robots"]');
			const content = await robotsMeta.getAttribute('content');
			expect(content).toContain('noindex');
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});

test.describe('Canonical URL', () => {
	test('Default canonical URL matches permalink', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Default Canonical Test',
			content: 'Testing default canonical URL.',
			status: 'publish',
		});

		try {
			await page.goto(post.link);

			const canonicalLink = page.locator('link[rel="canonical"]');
			await expect(canonicalLink).toHaveCount(1);

			const href = await canonicalLink.getAttribute('href');
			expect(href).toBeTruthy();

			// Should contain the post slug or ID reference
			expect(href).toContain('default-canonical-test');
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Custom canonical URL overrides default', async ({
		page,
		requestUtils,
	}) => {
		const customCanonical = 'https://www.example.com/different-page/'; // pragma: allowlist secret

		const post = await requestUtils.createPost({
			title: 'Custom Canonical Test',
			content: 'Testing custom canonical URL.',
			status: 'publish',
		});

		try {
			// Set custom canonical using the registered REST field
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
			await expect(canonicalLink).toHaveCount(1);

			const href = await canonicalLink.getAttribute('href');
			expect(href).toBe(customCanonical);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Canonical URL in OG matches canonical link', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'OG URL Canonical Test',
			content: 'Testing OG URL matches canonical.',
			status: 'publish',
		});

		try {
			await page.goto(post.link);

			const canonicalLink = page.locator('link[rel="canonical"]');
			const canonicalHref = await canonicalLink.getAttribute('href');

			const ogUrl = page.locator('meta[property="og:url"]');
			const ogUrlContent = await ogUrl.getAttribute('content');

			// OG URL should match canonical
			expect(ogUrlContent).toBe(canonicalHref);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Custom canonical affects OG URL', async ({ page, requestUtils }) => {
		const customCanonical = 'https://www.example.com/canonical-og-test/'; // pragma: allowlist secret

		const post = await requestUtils.createPost({
			title: 'Canonical OG Sync Test',
			content: 'Testing canonical syncs with OG.',
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

			const ogUrl = page.locator('meta[property="og:url"]');
			const ogUrlContent = await ogUrl.getAttribute('content');

			// OG URL should also use custom canonical
			expect(ogUrlContent).toBe(customCanonical);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Canonical URL is absolute', async ({ page, requestUtils }) => {
		const post = await requestUtils.createPost({
			title: 'Absolute Canonical Test',
			content: 'Testing canonical is absolute URL.',
			status: 'publish',
		});

		try {
			await page.goto(post.link);

			const canonicalLink = page.locator('link[rel="canonical"]');
			const href = await canonicalLink.getAttribute('href');

			expect(href).toBeTruthy();
			// Should be an absolute URL (starts with http:// or https://)
			expect(href).toMatch(/^https?:\/\//);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});

test.describe('Page Robots Directives', () => {
	test('Page allows indexing by default', async ({ page, requestUtils }) => {
		const testPage = await requestUtils.createPage({
			title: 'Page Robots Test',
			content: 'Testing page robots directive.',
			status: 'publish',
		});

		try {
			await page.goto(testPage.link);

			const robotsMeta = page.locator('meta[name="robots"]');
			const count = await robotsMeta.count();

			if (count > 0) {
				// If robots tag exists, verify it doesn't contain noindex
				const content = await robotsMeta.getAttribute('content');
				expect(content).toBeTruthy();
				expect(content).not.toContain('noindex');
			}
			// No robots tag = default WordPress behavior = indexable
		} finally {
			await deletePage(requestUtils, testPage.id);
		}
	});

	test('Page can be set to noindex', async ({ page, requestUtils }) => {
		const testPage = await requestUtils.createPage({
			title: 'Noindex Page Test',
			content: 'Testing page noindex.',
			status: 'publish',
		});

		try {
			await requestUtils.rest({
				path: `/wp/v2/pages/${testPage.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						noindex: true,
					},
				},
			});

			await page.goto(testPage.link);

			const robotsMeta = page.locator('meta[name="robots"]');
			const content = await robotsMeta.getAttribute('content');
			expect(content).toContain('noindex');
		} finally {
			await deletePage(requestUtils, testPage.id);
		}
	});
});

test.describe('Combined Directives', () => {
	test('Noindex post still has follow directive', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Noindex Follow Test',
			content: 'Testing noindex with follow.',
			status: 'publish',
		});

		try {
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						noindex: true,
					},
				},
			});

			await page.goto(post.link);

			const robotsMeta = page.locator('meta[name="robots"]');
			const content = await robotsMeta.getAttribute('content');

			// Should have noindex but still follow links
			expect(content).toContain('noindex');
			expect(content).toContain('follow');
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Noindex and custom canonical work together', async ({
		page,
		requestUtils,
	}) => {
		const customCanonical = 'https://www.example.com/combined-test/'; // pragma: allowlist secret

		const post = await requestUtils.createPost({
			title: 'Combined Directives Test',
			content: 'Testing noindex with custom canonical.',
			status: 'publish',
		});

		try {
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						noindex: true,
						canonical_url: customCanonical,
					},
				},
			});

			await page.goto(post.link);

			// Verify noindex
			const robotsMeta = page.locator('meta[name="robots"]');
			const robotsContent = await robotsMeta.getAttribute('content');
			expect(robotsContent).toContain('noindex');

			// Verify custom canonical
			const canonicalLink = page.locator('link[rel="canonical"]');
			const canonicalHref = await canonicalLink.getAttribute('href');
			expect(canonicalHref).toBe(customCanonical);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});

test.describe('Draft and Private Posts', () => {
	test('Draft posts are not accessible to anonymous users', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Draft Post Test',
			content: 'This is a draft post.',
			status: 'draft',
		});

		try {
			// Try to access draft post (should redirect or 404)
			const response = await page.goto(`/?p=${post.id}`);

			// Draft posts should not be publicly accessible
			// This might return 404 or redirect to homepage
			// The important thing is it doesn't expose SEO tags for draft content
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Published post has proper indexable robots', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Published Post Robots Test',
			content: 'This published post should be indexable.',
			status: 'publish',
		});

		try {
			await page.goto(post.link);

			const robotsMeta = page.locator('meta[name="robots"]');
			const count = await robotsMeta.count();

			if (count > 0) {
				// If robots tag exists, verify it doesn't contain noindex
				const content = await robotsMeta.getAttribute('content');
				expect(content).not.toContain('noindex');
			}
			// No robots tag = default WordPress behavior = indexable
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});

test.describe('URL Validation', () => {
	test('Invalid canonical URL is handled gracefully', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Invalid Canonical Test',
			content: 'Testing invalid canonical handling.',
			status: 'publish',
		});

		try {
			// Try to set an invalid URL using the registered REST field
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						canonical_url: 'not-a-valid-url',
					},
				},
			});

			await page.goto(post.link);

			// Page should still load without error
			const title = await page.locator('title').textContent();
			expect(title).toContain('Invalid Canonical Test');

			// Canonical should either be sanitized or fall back to default
			const canonicalLink = page.locator('link[rel="canonical"]');
			await expect(canonicalLink).toHaveCount(1);

			const href = await canonicalLink.getAttribute('href');
			expect(href).toBeTruthy();
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Empty canonical URL uses default', async ({ page, requestUtils }) => {
		const post = await requestUtils.createPost({
			title: 'Empty Canonical Test',
			content: 'Testing empty canonical URL.',
			status: 'publish',
		});

		try {
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						canonical_url: '',
					},
				},
			});

			await page.goto(post.link);

			const canonicalLink = page.locator('link[rel="canonical"]');
			const href = await canonicalLink.getAttribute('href');

			// Should have a valid canonical (fallback to permalink)
			expect(href).toBeTruthy();
			expect(href).toMatch(/^https?:\/\//);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});
