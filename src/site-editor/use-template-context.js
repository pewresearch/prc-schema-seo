/**
 * WordPress Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';

/**
 * parseTemplateSlug
 * Determines template context from slug.
 * Returns key without prefix (e.g., 'front_page' not 'prc_schema_seo_template_front_page')
 * @param {string} slug Template slug
 * @return {Object} Context object with type, key, label, post_type, taxonomy
 */
function parseTemplateSlug(slug) {
	// 404 error page
	if (slug === '404') {
		return {
			type: '404',
			key: '404',
			label: __('404 Error Page', 'prc-schema-seo'),
			post_type: '',
			taxonomy: '',
		};
	}

	// Search results
	if (slug === 'search') {
		return {
			type: 'search',
			key: 'search',
			label: __('Search Results', 'prc-schema-seo'),
			post_type: '',
			taxonomy: '',
		};
	}

	// Front page
	if (slug === 'front-page' || slug === 'frontpage') {
		return {
			type: 'front_page',
			key: 'front_page',
			label: __('Front Page', 'prc-schema-seo'),
			post_type: '',
			taxonomy: '',
		};
	}

	// Blog / Home
	if (slug === 'home' || slug === 'index') {
		return {
			type: 'blog',
			key: 'blog',
			label: __('Blog', 'prc-schema-seo'),
			post_type: 'post',
			taxonomy: '',
		};
	}

	// Attachment
	if (slug === 'attachment') {
		return {
			type: 'attachment',
			key: 'attachment',
			label: __('Attachment', 'prc-schema-seo'),
			post_type: 'attachment',
			taxonomy: '',
		};
	}

	// Author archive
	if (slug === 'author') {
		return {
			type: 'author',
			key: 'author',
			label: __('Author Archive', 'prc-schema-seo'),
			post_type: '',
			taxonomy: '',
		};
	}

	// Date archive
	if (slug === 'date') {
		return {
			type: 'date',
			key: 'date',
			label: __('Date Archive', 'prc-schema-seo'),
			post_type: '',
			taxonomy: '',
		};
	}

	// Generic archive
	if (slug === 'archive') {
		return {
			type: 'post_type_archive',
			key: 'post_type_archive_post',
			label: __('Archive', 'prc-schema-seo'),
			post_type: 'post',
			taxonomy: '',
		};
	}

	// Post type archive: archive-{post_type}
	const archiveMatch = slug.match(/^archive-(.+)$/);
	if (archiveMatch) {
		const postType = archiveMatch[1];
		return {
			type: 'post_type_archive',
			key: `post_type_archive_${postType}`,
			label: sprintf('%s Archive', postType),
			post_type: postType,
			taxonomy: '',
		};
	}

	// Single post type: single-{post_type}
	const singleMatch = slug.match(/^single-(.+)$/);
	if (singleMatch) {
		const postType = singleMatch[1];
		return {
			type: 'single_post_type',
			key: `single_${postType}`,
			label: sprintf('Single %s', postType),
			post_type: postType,
			taxonomy: '',
		};
	}

	// Generic single
	if (slug === 'single') {
		return {
			type: 'single_post_type',
			key: 'single_post',
			label: __('Single Post', 'prc-schema-seo'),
			post_type: 'post',
			taxonomy: '',
		};
	}

	// Page template (for singular pages)
	if (slug === 'page') {
		return {
			type: 'single_post_type',
			key: 'single_page',
			label: __('Single Page', 'prc-schema-seo'),
			post_type: 'page',
			taxonomy: '',
		};
	}

	// Taxonomy: taxonomy-{taxonomy}
	const taxonomyMatch = slug.match(/^taxonomy-(.+)$/);
	if (taxonomyMatch) {
		const taxonomy = taxonomyMatch[1];
		return {
			type: 'taxonomy',
			key: `taxonomy_${taxonomy}`,
			label: sprintf('%s Archive', taxonomy),
			post_type: '',
			taxonomy,
		};
	}

	// Category
	if (slug === 'category') {
		return {
			type: 'taxonomy',
			key: 'taxonomy_category',
			label: __('Category Archive', 'prc-schema-seo'),
			post_type: '',
			taxonomy: 'category',
		};
	}

	// Tag
	if (slug === 'tag') {
		return {
			type: 'taxonomy',
			key: 'taxonomy_post_tag',
			label: __('Tag Archive', 'prc-schema-seo'),
			post_type: '',
			taxonomy: 'post_tag',
		};
	}

	// Unknown
	return {
		type: 'unknown',
		key: null,
		label: sprintf('Template: %s', slug),
		post_type: '',
		taxonomy: '',
	};
}

/**
 * useTemplateContext
 * Detects the current template being edited in the Site Editor.
 * @return {{context: Object|null, isLoading: boolean}} Context and loading state
 */
export default function useTemplateContext() {
	const { currentTemplateId, currentPostType } = useSelect((select) => {
		const editor = select('core/editor');
		return {
			currentTemplateId: editor?.getCurrentPostId?.(),
			currentPostType: editor?.getCurrentPostType?.(),
		};
	}, []);

	const [context, setContext] = useState(null);
	const [isLoading, setIsLoading] = useState(true);

	useEffect(() => {
		if (!currentTemplateId || currentPostType !== 'wp_template') {
			setContext(null);
			setIsLoading(false);
			return;
		}

		// Check if currentTemplateId is a string and its not empty
		if (
			typeof currentTemplateId !== 'string' ||
			currentTemplateId.length === 0
		) {
			setContext(null);
			setIsLoading(false);
			return;
		}

		// Parse template ID to determine context.
		// Template IDs are in format: theme//template-slug
		const parts = currentTemplateId.split('//');
		const slug = parts[1] || currentTemplateId;

		const detectedContext = parseTemplateSlug(slug);
		setContext(detectedContext);
		setIsLoading(false);
	}, [currentTemplateId, currentPostType]);

	return { context, isLoading };
}
