/**
 * Shared Test Setup Utilities
 *
 * Provides shared setup functions for tests, including nested category creation.
 *
 */

import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

interface NestedCategories {
	grandparentCategoryId: number;
	parentCategoryId: number;
	childCategoryId: number;
}

/**
 * Ensures nested test categories exist, creating them if they don't.
 * Returns category IDs for use in tests.
 *
 * @param requestUtils RequestUtils instance for REST API calls.
 * @return Object containing category IDs.
 */
export async function ensureNestedCategories(
	requestUtils: RequestUtils
): Promise<NestedCategories> {
	// Check if categories exist by slug
	let grandparentCategoryId: number;
	let parentCategoryId: number;
	let childCategoryId: number;

	// Check for grandparent category
	try {
		const categories = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'GET',
			params: { slug: 'grandparent-cat' },
		});

		if (Array.isArray(categories) && categories.length > 0) {
			grandparentCategoryId = categories[0].id;
		} else {
			// Create grandparent category
			const grandparent = await requestUtils.rest({
				path: '/wp/v2/categories',
				method: 'POST',
				data: { name: 'Grandparent Category', slug: 'grandparent-cat' },
			});
			grandparentCategoryId = grandparent.id;
		}
	} catch (error) {
		// If check fails, try to create
		const grandparent = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'POST',
			data: { name: 'Grandparent Category', slug: 'grandparent-cat' },
		});
		grandparentCategoryId = grandparent.id;
	}

	// Check for parent category
	try {
		const categories = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'GET',
			params: { slug: 'parent-cat' },
		});

		if (Array.isArray(categories) && categories.length > 0) {
			parentCategoryId = categories[0].id;
		} else {
			// Create parent category (child of grandparent)
			const parent = await requestUtils.rest({
				path: '/wp/v2/categories',
				method: 'POST',
				data: {
					name: 'Parent Category',
					slug: 'parent-cat',
					parent: grandparentCategoryId,
				},
			});
			parentCategoryId = parent.id;
		}
	} catch (error) {
		// If check fails, try to create
		const parent = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'POST',
			data: {
				name: 'Parent Category',
				slug: 'parent-cat',
				parent: grandparentCategoryId,
			},
		});
		parentCategoryId = parent.id;
	}

	// Check for child category
	try {
		const categories = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'GET',
			params: { slug: 'child-cat' },
		});

		if (Array.isArray(categories) && categories.length > 0) {
			childCategoryId = categories[0].id;
		} else {
			// Create child category (child of parent)
			const child = await requestUtils.rest({
				path: '/wp/v2/categories',
				method: 'POST',
				data: {
					name: 'Child Category',
					slug: 'child-cat',
					parent: parentCategoryId,
				},
			});
			childCategoryId = child.id;
		}
	} catch (error) {
		// If check fails, try to create
		const child = await requestUtils.rest({
			path: '/wp/v2/categories',
			method: 'POST',
			data: {
				name: 'Child Category',
				slug: 'child-cat',
				parent: parentCategoryId,
			},
		});
		childCategoryId = child.id;
	}

	return {
		grandparentCategoryId,
		parentCategoryId,
		childCategoryId,
	};
}
