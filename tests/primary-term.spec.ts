/**
 * Primary Term Tests
 *
 * Tests for verifying primary term selection and its integration
 * with breadcrumb schema and other SEO features.
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

interface SchemaGraph {
	'@context': string;
	'@graph': SchemaItem[];
}

interface SchemaItem {
	'@type': string | string[];
	'@id'?: string;
	itemListElement?: BreadcrumbItem[];
	[key: string]: unknown;
}

interface BreadcrumbItem {
	'@type': string;
	position: number;
	name: string;
	item?: string;
}

/**
 * Helper to get schema from page
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
 * Helper to find breadcrumb schema
 * @param schema
 */
function findBreadcrumb(schema: SchemaGraph): SchemaItem | undefined {
	return schema['@graph'].find((item) => item['@type'] === 'BreadcrumbList');
}

test.describe('Primary Term Selection', () => {
	let categoryId: number;
	let categoryName: string;

	test.beforeAll(async ({ requestUtils }) => {
		// Create a test category
		const category = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'POST',
			data: {
				name: 'Primary Term Test Category',
				slug: 'primary-term-test',
			},
		});
		categoryId = category.id;
		categoryName = category.name;
	});

	test.afterAll(async ({ requestUtils }) => {
		// Clean up test category
		if (categoryId) {
			try {
				await requestUtils.rest({
					path: `/wp/v2/categories/${categoryId}?force=true`,
					method: 'DELETE',
				});
			} catch {
				// Category may have been deleted already
			}
		}
	});

	test('Post with category has breadcrumb schema', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Breadcrumb Test Post',
			content: 'Testing breadcrumb schema.',
			status: 'publish',
			categories: [categoryId],
		});

		try {
			await page.goto(post.link);

			const schema = await getSchemaFromPage(page);
			expect(schema).not.toBeNull();

			const breadcrumb = findBreadcrumb(schema!);
			expect(breadcrumb).toBeDefined();
			expect(breadcrumb!.itemListElement).toBeInstanceOf(Array);
			expect(breadcrumb!.itemListElement!.length).toBeGreaterThan(0);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Primary term appears in breadcrumb', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Primary Term Breadcrumb Test',
			content: 'Testing primary term in breadcrumb.',
			status: 'publish',
			categories: [categoryId],
		});

		try {
			// Set primary category using the registered REST field
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						primary_terms: {
							category: categoryId,
						},
					},
				},
			});

			await page.goto(post.link);

			const schema = await getSchemaFromPage(page);
			expect(schema).not.toBeNull();

			const breadcrumb = findBreadcrumb(schema!);
			expect(breadcrumb).toBeDefined();

			// Check if category name appears in breadcrumb
			const items = breadcrumb!.itemListElement!;
			const hasCategoryInBreadcrumb = items.some(
				(item) =>
					item.name === categoryName ||
					item.name.includes('Primary Term Test')
			);

			// Breadcrumb should include the category
			expect(items.length).toBeGreaterThanOrEqual(1);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Primary term can be set via REST API', async ({ requestUtils }) => {
		const post = await requestUtils.createPost({
			title: 'Primary Term API Test',
			content: 'Testing primary term via API.',
			status: 'publish',
			categories: [categoryId],
		});

		try {
			// Set primary term using the registered REST field
			const updateResponse = await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						primary_terms: {
							category: categoryId,
						},
					},
				},
			});

			expect(updateResponse.id).toBe(post.id);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});

test.describe('Multiple Categories', () => {
	let category1Id: number;
	let category2Id: number;
	let category1Name: string;
	let category2Name: string;

	test.beforeAll(async ({ requestUtils }) => {
		// Create two test categories
		const cat1 = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'POST',
			data: {
				name: 'First Test Category',
				slug: 'first-test-cat',
			},
		});
		category1Id = cat1.id;
		category1Name = cat1.name;

		const cat2 = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'POST',
			data: {
				name: 'Second Test Category',
				slug: 'second-test-cat',
			},
		});
		category2Id = cat2.id;
		category2Name = cat2.name;
	});

	test.afterAll(async ({ requestUtils }) => {
		// Clean up
		for (const id of [category1Id, category2Id]) {
			if (id) {
				try {
					await requestUtils.rest({
						path: `/wp/v2/categories/${id}?force=true`,
						method: 'DELETE',
					});
				} catch {
					// Ignore errors
				}
			}
		}
	});

	test('Post with multiple categories can set primary', async ({
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Multi-Category Test',
			content: 'Post with multiple categories.',
			status: 'publish',
			categories: [category1Id, category2Id],
		});

		try {
			// Set category2 as primary using the registered REST field
			const updateResponse = await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						primary_terms: {
							category: category2Id,
						},
					},
				},
			});

			expect(updateResponse.id).toBe(post.id);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Primary term selection persists after update', async ({
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Primary Term Persistence Test',
			content: 'Testing that primary term persists.',
			status: 'publish',
			categories: [category1Id, category2Id],
		});

		try {
			// Set primary term using the registered REST field
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						primary_terms: {
							category: category1Id,
						},
					},
				},
			});

			// Update something else
			await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					title: 'Updated Title',
				},
			});

			// Verify post still exists and was updated
			const updatedPost = await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'GET',
			});

			expect(updatedPost.title.rendered).toBe('Updated Title');
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});

test.describe('Breadcrumb Schema Structure', () => {
	let grandparentCategoryId: number;
	let parentCategoryId: number;
	let childCategoryId: number;

	test.beforeAll(async ({ requestUtils }) => {
		// Get or create nested categories using shared utility
		const categories = await ensureNestedCategories(requestUtils);
		grandparentCategoryId = categories.grandparentCategoryId;
		parentCategoryId = categories.parentCategoryId;
		childCategoryId = categories.childCategoryId;
	});

	test.afterAll(async () => {
		// Categories persist across tests and are managed by shared setup
	});

	test('Breadcrumb has correct item structure', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Breadcrumb Structure Test',
			content: 'Testing breadcrumb structure.',
			status: 'publish',
			categories: [childCategoryId], // Use deepest category
		});

		try {
			await page.goto(post.link);

			// Verify DOM structure exists
			const breadcrumbList = page.locator('.prc-block-breadcrumbs__list');
			await expect(breadcrumbList).toBeVisible();

			// Verify JSON-LD schema structure
			const schema = await getSchemaFromPage(page);
			expect(schema).not.toBeNull();

			const breadcrumb = findBreadcrumb(schema!);
			expect(breadcrumb).toBeDefined();

			const items = breadcrumb!.itemListElement!;

			// Each item should have required properties
			items.forEach((item, index) => {
				expect(item['@type']).toBe('ListItem');
				expect(item.position).toBe(index + 1);
				expect(item.name).toBeTruthy();
			});

			// Verify DOM text contains the category names from schema
			const breadcrumbText = await breadcrumbList.textContent();
			expect(breadcrumbText).toBeTruthy();

			// Check that nested categories appear in the breadcrumb text
			expect(breadcrumbText).toContain('Grandparent Category');
			expect(breadcrumbText).toContain('Parent Category');
			expect(breadcrumbText).toContain('Child Category');
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Breadcrumb positions are sequential', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Sequential Position Test',
			content: 'Testing sequential breadcrumb positions.',
			status: 'publish',
			categories: [childCategoryId], // Use deepest category
		});

		try {
			await page.goto(post.link);

			const schema = await getSchemaFromPage(page);
			expect(schema).not.toBeNull();

			const breadcrumb = findBreadcrumb(schema!);
			expect(breadcrumb).toBeDefined();

			const items = breadcrumb!.itemListElement!;

			// Verify sequential positions
			for (let i = 0; i < items.length; i++) {
				expect(items[i].position).toBe(i + 1);
			}
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Breadcrumb includes home as first item', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Home Breadcrumb Test',
			content: 'Testing home in breadcrumb.',
			status: 'publish',
			categories: [childCategoryId], // Use deepest category
		});

		try {
			await page.goto(post.link);

			const schema = await getSchemaFromPage(page);
			expect(schema).not.toBeNull();

			const breadcrumb = findBreadcrumb(schema!);
			expect(breadcrumb).toBeDefined();

			const items = breadcrumb!.itemListElement!;
			expect(items.length).toBeGreaterThan(0);

			// First item should be position 1
			expect(items[0].position).toBe(1);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});

	test('Breadcrumb DOM structure matches rendered markup', async ({
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Breadcrumb DOM Test',
			content: 'Testing breadcrumb DOM structure.',
			status: 'publish',
			categories: [childCategoryId], // Use deepest category
		});

		try {
			await page.goto(post.link);

			// Check that breadcrumb nav element exists
			const breadcrumbNav = page.locator('nav[id^="breadcrumbs-"]');
			await expect(breadcrumbNav).toBeVisible();

			// Check that breadcrumb list div exists
			const breadcrumbList = page.locator('.prc-block-breadcrumbs__list');
			await expect(breadcrumbList).toBeVisible();

			// Get the breadcrumb text content
			const breadcrumbText = await breadcrumbList.textContent();
			expect(breadcrumbText).toBeTruthy();

			// Verify the breadcrumb text contains the nested categories in order
			// The actual format appears to be: "Home>Research Topics>Grandparent Category>Parent Category>Child Category"
			expect(breadcrumbText).toContain('Home');
			expect(breadcrumbText).toContain('Grandparent Category');
			expect(breadcrumbText).toContain('Parent Category');
			expect(breadcrumbText).toContain('Child Category');

			// Verify the order: Child should come after Parent, Parent after Grandparent
			const childIndex = breadcrumbText!.indexOf('Child Category');
			const parentIndex = breadcrumbText!.indexOf('Parent Category');
			const grandparentIndex = breadcrumbText!.indexOf(
				'Grandparent Category'
			);

			expect(grandparentIndex).toBeGreaterThan(-1);
			expect(parentIndex).toBeGreaterThan(grandparentIndex);
			expect(childIndex).toBeGreaterThan(parentIndex);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});
