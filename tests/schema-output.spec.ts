/**
 * Schema.org JSON-LD Output Tests
 *
 * Tests for verifying that JSON-LD structured data is correctly
 * generated and output on posts and pages.
 *
 */

import {
	test,
	expect,
	type RequestUtils,
} from '@wordpress/e2e-test-utils-playwright';
import { ensureNestedCategories } from './setup';

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

interface SchemaGraph {
	'@context': string;
	'@graph': SchemaItem[];
}

interface SchemaItem {
	'@type': string | string[];
	'@id'?: string;
	name?: string;
	headline?: string;
	url?: string;
	datePublished?: string;
	dateModified?: string;
	author?: { '@id': string } | { '@type': string; name: string };
	publisher?: { '@id': string };
	mainEntityOfPage?: { '@id': string };
	itemListElement?: BreadcrumbItem[];
	potentialAction?: unknown;
	[key: string]: unknown;
}

interface BreadcrumbItem {
	'@type': string;
	position: number;
	name: string;
	item?: string;
}

/**
 * Helper to extract JSON-LD schema from page
 * @param page
 */
async function getSchemaFromPage(
	page: import('@playwright/test').Page
): Promise<SchemaGraph | null> {
	const schemaScript = page.locator('script[type="application/ld+json"]');
	const count = await schemaScript.count();

	if (count === 0) {
		return null;
	}

	const content = await schemaScript.first().textContent();
	if (!content) {
		return null;
	}

	try {
		return JSON.parse(content) as SchemaGraph;
	} catch {
		return null;
	}
}

/**
 * Helper to find a schema item by type
 * @param schema
 * @param type
 */
function findSchemaByType(
	schema: SchemaGraph,
	type: string
): SchemaItem | undefined {
	return schema['@graph'].find((item) => {
		if (Array.isArray(item['@type'])) {
			return item['@type'].includes(type);
		}
		return item['@type'] === type;
	});
}

test.describe('Schema.org JSON-LD Output', () => {
	let testPostId: number;
	let testPostLink: string;
	let grandparentCategoryId: number;
	let parentCategoryId: number;
	let childCategoryId: number;

	test.beforeAll(async ({ requestUtils }) => {
		// Get or create nested categories using shared utility
		const categories = await ensureNestedCategories(requestUtils);
		grandparentCategoryId = categories.grandparentCategoryId;
		parentCategoryId = categories.parentCategoryId;
		childCategoryId = categories.childCategoryId;

		// Create test post with nested category
		const post = await requestUtils.createPost({
			title: 'Schema Test Post',
			content:
				'This is test content for JSON-LD schema validation. The content should be substantial enough to represent a real article.',
			status: 'publish',
			categories: [childCategoryId], // Use deepest category
		});
		testPostId = post.id;
		testPostLink = post.link;
	});

	test.afterAll(async ({ requestUtils }) => {
		if (testPostId) {
			await deletePost(requestUtils, testPostId);
		}
		// Categories persist across tests and are managed by shared setup
	});

	test('Post has JSON-LD script tag', async ({ page }) => {
		await page.goto(testPostLink);

		const schemaScript = page.locator('script[type="application/ld+json"]');
		await expect(schemaScript).toHaveCount(1);
	});

	test('Schema has correct @context', async ({ page }) => {
		await page.goto(testPostLink);

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();
		expect(schema!['@context']).toBe('https://schema.org');
	});

	test('Schema has @graph array', async ({ page }) => {
		await page.goto(testPostLink);

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();
		expect(schema!['@graph']).toBeInstanceOf(Array);
		expect(schema!['@graph'].length).toBeGreaterThan(0);
	});

	test('Schema includes WebSite type', async ({ page }) => {
		await page.goto(testPostLink);

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();

		const website = findSchemaByType(schema!, 'WebSite');
		expect(website).toBeDefined();
		expect(website!.name).toBeTruthy();
		expect(website!.url).toBeTruthy();
	});

	test('WebSite schema has SearchAction', async ({ page }) => {
		await page.goto(testPostLink);

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();

		const website = findSchemaByType(schema!, 'WebSite');
		expect(website).toBeDefined();
		expect(website!.potentialAction).toBeDefined();
	});

	test('Schema includes Organization type', async ({ page }) => {
		await page.goto(testPostLink);

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();

		const organization = findSchemaByType(schema!, 'Organization');
		expect(organization).toBeDefined();
		expect(organization!.name).toBeTruthy();
		expect(organization!.url).toBeTruthy();
	});

	test('Organization has logo', async ({ page }) => {
		await page.goto(testPostLink);

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();

		const organization = findSchemaByType(schema!, 'Organization');
		expect(organization).toBeDefined();
		expect(organization!.logo).toBeDefined();
	});

	test('Post has Article schema', async ({ page }) => {
		await page.goto(testPostLink);

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();

		// Find Article or any article subtype
		const article = schema!['@graph'].find((item) => {
			const type = item['@type'];
			if (Array.isArray(type)) {
				return type.some((t) =>
					[
						'Article',
						'NewsArticle',
						'BlogPosting',
						'Report',
					].includes(t)
				);
			}
			return ['Article', 'NewsArticle', 'BlogPosting', 'Report'].includes(
				type
			);
		});

		expect(article).toBeDefined();
	});

	test('Article schema has required properties', async ({ page }) => {
		await page.goto(testPostLink);

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();

		const article = schema!['@graph'].find((item) => {
			const type = item['@type'];
			if (Array.isArray(type)) {
				return type.some((t) =>
					[
						'Article',
						'NewsArticle',
						'BlogPosting',
						'Report',
					].includes(t)
				);
			}
			return ['Article', 'NewsArticle', 'BlogPosting', 'Report'].includes(
				type
			);
		});

		expect(article).toBeDefined();
		expect(article!.headline).toBe('Schema Test Post');
		expect(article!.datePublished).toBeTruthy();
		expect(article!.dateModified).toBeTruthy();
		expect(article!.author).toBeDefined();
		expect(article!.publisher).toBeDefined();
		expect(article!.mainEntityOfPage).toBeDefined();
	});

	test('Article schema dates are valid ISO 8601', async ({ page }) => {
		await page.goto(testPostLink);

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();

		const article = schema!['@graph'].find((item) => {
			const type = item['@type'];
			if (Array.isArray(type)) {
				return type.some((t) =>
					[
						'Article',
						'NewsArticle',
						'BlogPosting',
						'Report',
					].includes(t)
				);
			}
			return ['Article', 'NewsArticle', 'BlogPosting', 'Report'].includes(
				type
			);
		});

		expect(article).toBeDefined();

		// Verify dates are valid
		const datePublished = new Date(article!.datePublished as string);
		const dateModified = new Date(article!.dateModified as string);

		expect(datePublished.toString()).not.toBe('Invalid Date');
		expect(dateModified.toString()).not.toBe('Invalid Date');
	});

	test('Schema includes WebPage type', async ({ page }) => {
		await page.goto(testPostLink);

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();

		const webpage = findSchemaByType(schema!, 'WebPage');
		expect(webpage).toBeDefined();
		expect(webpage!.url).toBeTruthy();
	});

	test('Schema includes BreadcrumbList', async ({ page }) => {
		await page.goto(testPostLink);

		// Verify DOM structure exists
		const breadcrumbList = page.locator('.prc-block-breadcrumbs__list');
		await expect(breadcrumbList).toBeVisible();

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();

		const breadcrumb = findSchemaByType(schema!, 'BreadcrumbList');
		expect(breadcrumb).toBeDefined();
		expect(breadcrumb!.itemListElement).toBeInstanceOf(Array);
		expect(breadcrumb!.itemListElement!.length).toBeGreaterThan(0);
	});

	test('BreadcrumbList items have correct structure', async ({ page }) => {
		await page.goto(testPostLink);

		// Verify DOM structure exists
		const breadcrumbList = page.locator('.prc-block-breadcrumbs__list');
		await expect(breadcrumbList).toBeVisible();

		const schema = await getSchemaFromPage(page);
		expect(schema).not.toBeNull();

		const breadcrumb = findSchemaByType(schema!, 'BreadcrumbList');
		expect(breadcrumb).toBeDefined();

		const items = breadcrumb!.itemListElement!;
		items.forEach((item, index) => {
			expect(item['@type']).toBe('ListItem');
			expect(item.position).toBe(index + 1);
			expect(item.name).toBeTruthy();
		});

		// Verify DOM text contains nested category names
		const breadcrumbText = await breadcrumbList.textContent();
		expect(breadcrumbText).toBeTruthy();
		expect(breadcrumbText).toContain('Grandparent Category');
		expect(breadcrumbText).toContain('Parent Category');
		expect(breadcrumbText).toContain('Child Category');
	});
});

test.describe('Schema Type Selection', () => {
	test('NewsArticle schema type can be set', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'NewsArticle Schema Test',
			content: 'Content for NewsArticle schema testing.',
			status: 'publish',
		});

		try {
			// Set schema type to NewsArticle using the registered REST field
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						schema_type: 'NewsArticle',
					},
				},
			});

			await page.goto(post.link);

			const schema = await getSchemaFromPage(page);
			expect(schema).not.toBeNull();

			const article = schema!['@graph'].find((item) => {
				const type = item['@type'];
				if (Array.isArray(type)) {
					return type.includes('NewsArticle');
				}
				return type === 'NewsArticle';
			});

			expect(article).toBeDefined();
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('BlogPosting schema type can be set', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'BlogPosting Schema Test',
			content: 'Content for BlogPosting schema testing.',
			status: 'publish',
		});

		try {
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						schema_type: 'BlogPosting',
					},
				},
			});

			await page.goto(post.link);

			const schema = await getSchemaFromPage(page);
			expect(schema).not.toBeNull();

			const article = schema!['@graph'].find((item) => {
				const type = item['@type'];
				if (Array.isArray(type)) {
					return type.includes('BlogPosting');
				}
				return type === 'BlogPosting';
			});

			expect(article).toBeDefined();
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Report schema type can be set', async ({ page, requestUtils }) => {
		const post = await requestUtils.createPost({
			title: 'Report Schema Test',
			content: 'Content for Report schema testing.',
			status: 'publish',
		});

		try {
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						schema_type: 'Report',
					},
				},
			});

			await page.goto(post.link);

			const schema = await getSchemaFromPage(page);
			expect(schema).not.toBeNull();

			const article = schema!['@graph'].find((item) => {
				const type = item['@type'];
				if (Array.isArray(type)) {
					return type.includes('Report');
				}
				return type === 'Report';
			});

			expect(article).toBeDefined();
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});

test.describe('Page Schema', () => {
	test('Page has WebPage schema (not Article)', async ({
		page,
		requestUtils,
	}) => {
		const testPage = await requestUtils.createPage({
			title: 'WebPage Schema Test',
			content: 'Content for WebPage schema testing.',
			status: 'publish',
		});

		try {
			await page.goto(testPage.link);

			const schema = await getSchemaFromPage(page);
			expect(schema).not.toBeNull();

			const webpage = findSchemaByType(schema!, 'WebPage');
			expect(webpage).toBeDefined();
			expect(webpage!.name).toBe('WebPage Schema Test');
		} finally {
			await deletePage(requestUtils, testPage.id);
		}
	});
});

test.describe('Schema Validity', () => {
	test('Schema JSON is valid and parseable', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'JSON Validity Test',
			content: 'Testing that schema JSON is valid.',
			status: 'publish',
		});

		try {
			await page.goto(post.link);

			const schemaScript = page.locator(
				'script[type="application/ld+json"]'
			);
			const content = await schemaScript.textContent();

			expect(content).toBeTruthy();

			// Should not throw
			let parsed;
			expect(() => {
				parsed = JSON.parse(content!);
			}).not.toThrow();

			expect(parsed).toHaveProperty('@context');
			expect(parsed).toHaveProperty('@graph');
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Schema @id references are consistent', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'ID Reference Test',
			content: 'Testing schema ID references.',
			status: 'publish',
		});

		try {
			await page.goto(post.link);

			const schema = await getSchemaFromPage(page);
			expect(schema).not.toBeNull();

			// Collect all @id values
			const ids = new Set<string>();
			schema!['@graph'].forEach((item) => {
				if (item['@id']) {
					ids.add(item['@id']);
				}
			});

			// Verify references point to defined IDs
			const article = schema!['@graph'].find((item) => {
				const type = item['@type'];
				if (Array.isArray(type)) {
					return type.some((t) =>
						[
							'Article',
							'NewsArticle',
							'BlogPosting',
							'Report',
						].includes(t)
					);
				}
				return [
					'Article',
					'NewsArticle',
					'BlogPosting',
					'Report',
				].includes(type);
			});

			if (
				article &&
				article.publisher &&
				typeof article.publisher === 'object' &&
				'@id' in article.publisher
			) {
				expect(ids.has(article.publisher['@id'])).toBe(true);
			}

			if (
				article &&
				article.mainEntityOfPage &&
				typeof article.mainEntityOfPage === 'object' &&
				'@id' in article.mainEntityOfPage
			) {
				expect(ids.has(article.mainEntityOfPage['@id'])).toBe(true);
			}
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});
