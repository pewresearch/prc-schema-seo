/**
 * Base Test Suite
 *
 * Basic tests to verify the test environment is working correctly
 * and WordPress is properly configured for testing.
 *
 */

import {
	test,
	expect,
	type RequestUtils,
} from '@wordpress/e2e-test-utils-playwright';

const testTitle = 'Test Post';
const testContent = 'This is a test post.';

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

test.describe('Test Environment', () => {
	test('WordPress REST API is accessible', async ({ requestUtils }) => {
		const posts = await requestUtils.rest({
			path: '/wp/v2/posts',
			method: 'GET',
		});
		expect(posts).toBeDefined();
		expect(Array.isArray(posts)).toBe(true);
	});

	test('Post type is properly registered', async ({ requestUtils }) => {
		const types = await requestUtils.rest({
			path: '/wp/v2/types',
			method: 'GET',
		});
		expect(types).toBeDefined();
		expect(types.post).toBeDefined();
		expect(types.page).toBeDefined();
	});

	test('Can create and delete posts', async ({ requestUtils }) => {
		// Create a test post
		const post = await requestUtils.createPost({
			title: testTitle,
			content: testContent,
			status: 'publish',
		});

		expect(post).toBeDefined();
		expect(post.id).toBeTruthy();
		expect(post.title.raw).toBe(testTitle);

		// Clean up
		await deletePost(requestUtils, post.id);
	});

	test('Can create and delete pages', async ({ requestUtils }) => {
		// Create a test page
		const page = await requestUtils.createPage({
			title: 'Test Page',
			content: 'Test page content.',
			status: 'publish',
		});

		expect(page).toBeDefined();
		expect(page.id).toBeTruthy();

		// Clean up
		await deletePage(requestUtils, page.id);
	});
});

test.describe('Post Creation', () => {
	test('Post can be created via admin', async ({
		admin,
		editor,
		requestUtils,
		page,
	}) => {
		// Create a new post
		await admin.createNewPost({
			title: testTitle,
			content: testContent,
			postType: 'post',
		});

		// Publish the post
		await editor.publishPost();

		// Get posts via REST API
		const posts = await requestUtils.rest({
			path: '/wp/v2/posts',
			method: 'GET',
		});

		// Find the post we just created
		const createdPost = posts.find(
			(p: { title: { rendered: string } }) =>
				p.title.rendered === testTitle
		);

		expect(createdPost).toBeDefined();

		// Clean up
		if (createdPost) {
			await deletePost(requestUtils, createdPost.id);
		}
	});

	test('Post metadata can be updated', async ({ requestUtils }) => {
		const post = await requestUtils.createPost({
			title: 'Metadata Test Post',
			content: 'Testing metadata.',
			status: 'publish',
		});

		try {
			// Update post meta using the registered REST field
			const updatedPost = await requestUtils.rest({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: {
					prc_seo_data: {
						title: 'SEO Title Test',
					},
				},
			});

			expect(updatedPost).toBeDefined();
			expect(updatedPost.id).toBe(post.id);
		} finally {
			await deletePost(requestUtils, post.id);
		}
	});
});

test.describe('Category Management', () => {
	test('Can create and delete categories', async ({ requestUtils }) => {
		// Create a category
		const category = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'POST',
			data: {
				name: 'Test Category',
				slug: 'test-category',
			},
		});

		expect(category).toBeDefined();
		expect(category.id).toBeTruthy();

		// Clean up
		await requestUtils.rest({
			path: `/wp/v2/categories/${category.id}?force=true`,
			method: 'DELETE',
		});
	});

	test('Post can be assigned to category', async ({ requestUtils }) => {
		// Create a category
		const category = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'POST',
			data: {
				name: 'Assignment Test Category',
				slug: 'assignment-test',
			},
		});

		try {
			// Create a post with the category
			const post = await requestUtils.createPost({
				title: 'Category Assignment Test',
				content: 'Testing category assignment.',
				status: 'publish',
				categories: [category.id],
			});

			expect(post).toBeDefined();
			expect(post.categories).toContain(category.id);

			await deletePost(requestUtils, post.id);
		} finally {
			await requestUtils.rest({
				path: `/wp/v2/categories/${category.id}?force=true`,
				method: 'DELETE',
			});
		}
	});
});
