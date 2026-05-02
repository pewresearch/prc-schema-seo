/**
 * External Dependencies
 */
import {
	useAISuggest,
	AISuggestButton,
	AILoadingIndicator,
	AISuggestionPreview,
} from '@prc/components';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Notice } from '@wordpress/components';

/**
 * Field labels for displaying AI-generated values.
 */
const FIELD_LABELS = {
	title: __('SEO Title', 'prc-schema-seo'),
	description: __('Description', 'prc-schema-seo'),
	og_title: __('Social Title', 'prc-schema-seo'),
	og_description: __('Social Description', 'prc-schema-seo'),
};

/**
 * AI Suggest SEO Button component.
 *
 * Calls the prc-schema-seo/suggest ability to generate AI suggestions
 * for the specified SEO fields and applies them via the update callback.
 *
 * @param {Object}   props        Component props.
 * @param {string[]} props.fields Which fields to request (e.g. ['title', 'description']).
 * @param {string}   props.label  Button label text.
 * @param {Function} props.update Callback to update SEO data: update(key, value).
 */
export default function AISuggestSEO({ fields, label, update }) {
	const aiConfig = window.PRCSchemaSEOAI;
	const abilityName = aiConfig?.abilityName || 'prc-schema-seo/suggest';

	const postId = useSelect(
		(select) => select('core/editor').getCurrentPostId(),
		[]
	);

	const { isLoading, error, result, fetch, reset, dismissError } =
		useAISuggest({
			abilityName,
			transformResult: (raw) => raw.suggestions,
		});

	const handleFetch = useCallback(() => {
		fetch({ post_id: postId, fields });
	}, [fetch, postId, fields]);

	const applySuggestions = useCallback(() => {
		if (!result) {
			return;
		}
		update(result);
		reset();
	}, [result, update, reset]);

	// Hide UI when AI experiment is disabled.
	if (!aiConfig || !aiConfig.enabled) {
		return null;
	}

	return (
		<div style={{ marginBottom: '16px' }}>
			{!result && !isLoading && (
				<AISuggestButton
					label={label || __('Suggest SEO', 'prc-schema-seo')}
					text={label || __('Suggest SEO', 'prc-schema-seo')}
					onClick={handleFetch}
					isLoading={isLoading}
					minWords={150}
				/>
			)}

			{isLoading && (
				<AILoadingIndicator
					message={__('Generating…', 'prc-schema-seo')}
				/>
			)}

			{error && (
				<Notice status="warning" isDismissible onDismiss={dismissError}>
					{error}
				</Notice>
			)}

			{result && !isLoading && (
				<AISuggestionPreview
					onApply={applySuggestions}
					onDismiss={reset}
					onRegenerate={handleFetch}
				>
					{Object.entries(result).map(([key, value]) => (
						<div
							key={key}
							style={{
								marginBottom: '8px',
								fontSize: '13px',
							}}
						>
							<strong>{FIELD_LABELS[key] || key}:</strong>
							<p
								style={{
									margin: '2px 0 0',
									color: '#333',
									fontStyle: 'italic',
								}}
							>
								{value}
							</p>
						</div>
					))}
				</AISuggestionPreview>
			)}
		</div>
	);
}
