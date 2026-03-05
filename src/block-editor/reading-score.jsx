/**
 * WordPress Dependencies
 */
import { useSelect } from '@wordpress/data';
import { useEffect, useState, useRef } from '@wordpress/element';
import { PluginPostStatusInfo } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';

/**
 * External Dependencies
 */
import styled from '@emotion/styled';

/**
 * Internal Dependencies
 */
import { calculate } from './flesch-kincaid';

const StyledPostStatusInfo = styled(PluginPostStatusInfo)`
	order: -1;
	margin-top: 0;
	margin-bottom: 1em;
`;

const DEBOUNCE_MS = 500;

/**
 * Map a Reading Ease score to a CSS color token.
 * green >= 60, yellow 30–59, red < 30.
 *
 * @param {number|null} score
 * @return {string} CSS color value.
 */
function easeColor(score) {
	if (score === null) return '#ccc';
	if (score >= 60) return '#00a32a'; // WP green
	if (score >= 30) return '#dba617'; // WP yellow
	return '#d63638'; // WP red
}

/**
 * Small colored circle indicator.
 *
 * @param {{ score: number|null }} props
 */
function ScoreDot({ score }) {
	return (
		<span
			style={{
				display: 'inline-block',
				width: 10,
				height: 10,
				borderRadius: '50%',
				backgroundColor: easeColor(score),
				marginRight: 6,
				flexShrink: 0,
				verticalAlign: 'middle',
			}}
			aria-hidden="true"
		/>
	);
}

/**
 * ReadingScoreInfo
 *
 * Renders Flesch-Kincaid Reading Ease and Grade Level in the post status info
 * slot. Score is recalculated client-side from the live post content so it
 * updates as the author writes (debounced at 500 ms).
 */
function ReadingScoreInfo() {
	const postType = useSelect(
		(select) => select('core/editor').getCurrentPostType(),
		[]
	);
	const content = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('content') ?? '',
		[]
	);

	const enabledPostTypes =
		(window.PRCSchemaSEO && window.PRCSchemaSEO.enabledPostTypes) || [];

	const [score, setScore] = useState(null);
	const timerRef = useRef(null);

	useEffect(() => {
		if (timerRef.current) {
			clearTimeout(timerRef.current);
		}
		timerRef.current = setTimeout(() => {
			setScore(calculate(content));
		}, DEBOUNCE_MS);

		return () => clearTimeout(timerRef.current);
	}, [content]);

	// Only show on supported post types.
	if (!enabledPostTypes.includes(postType)) {
		return null;
	}

	if (!score) {
		return null;
	}

	return (
		<StyledPostStatusInfo className="prc-reading-score">
			<span
				style={{
					display: 'flex',
					alignItems: 'center',
					width: '100%',
					flexWrap: 'wrap',
					justifyContent: 'space-between',
				}}
			>
				<span style={{ display: 'flex', alignItems: 'center' }}>
					<ScoreDot score={score.readingEase} />
					<span style={{ fontSize: 12 }}>
						Reading Level: <strong>{score.gradeLabel}</strong>
					</span>
				</span>
				<span
					style={{ fontSize: 11, color: '#757575' }}
					title={`Flesch Reading Ease: ${score.readingEase} / 100 (${score.easeLabel})`}
				>
					Ease {score.readingEase}
				</span>
			</span>
		</StyledPostStatusInfo>
	);
}

export default function registerReadingScore() {
	registerPlugin('prc-schema-seo-reading-score', {
		render: ReadingScoreInfo,
	});
}
