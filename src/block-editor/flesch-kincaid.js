/**
 * Flesch-Kincaid readability utilities.
 *
 * Mirrors the PHP Reading_Score::calculate() logic so the editor slot fill
 * can compute scores client-side without a server round-trip.
 *
 * Formulas:
 *   Reading Ease  = 206.835 - 1.015*(words/sentences) - 84.6*(syllables/words)
 *   Grade Level   = 0.39*(words/sentences) + 11.8*(syllables/words) - 15.59
 */

/**
 * Count the syllables in a single word using English heuristics.
 *
 * @param {string} word
 * @return {number} Syllable count, minimum 1.
 */
function countSyllables(word) {
	word = word.toLowerCase().replace(/[^a-z]/g, '');
	if (!word) return 1;

	// Strip trailing silent 'e' (but keep short words like "the").
	if (word.length > 2 && word.endsWith('e')) {
		word = word.slice(0, -1);
	}

	const matches = word.match(/[aeiouy]+/gi);
	return Math.max(1, matches ? matches.length : 1);
}

/**
 * Strip HTML tags from a string.
 *
 * @param {string} html
 * @return {string} The string with HTML tags stripped.
 */
function stripHtml(html) {
	return html.replace(/<[^>]*>/g, ' ').replace(/&[a-z]+;/gi, ' ');
}

/**
 * Convert a Flesch Reading Ease score to a human-readable label.
 *
 * @param {number} score
 * @return {string} The human-readable label for the Flesch Reading Ease score.
 */
export function easeLabel(score) {
	if (score >= 90) return 'Very Easy';
	if (score >= 80) return 'Easy';
	if (score >= 70) return 'Fairly Easy';
	if (score >= 60) return 'Standard';
	if (score >= 50) return 'Fairly Difficult';
	if (score >= 30) return 'Difficult';
	return 'Very Confusing';
}

/**
 * Convert a Flesch-Kincaid grade level number to a human-readable label.
 *
 * @param {number} grade
 * @return {string} The human-readable label for the Flesch-Kincaid grade level.
 */
export function gradeLabel(grade) {
	if (grade <= 1) return 'Kindergarten';
	if (grade <= 6) return `${Math.floor(grade)}th Grade`;
	if (grade <= 8) return `${Math.floor(grade)}th Grade`;
	if (grade <= 9) return '9th Grade';
	if (grade <= 10) return '10th Grade';
	if (grade <= 11) return '11th Grade';
	if (grade <= 12) return '12th Grade / High School';
	if (grade <= 16) return 'College Level';
	return 'Graduate Level';
}

/**
 * Calculate Flesch-Kincaid metrics for a given text string.
 *
 * @param {string} text Raw or HTML text.
 * @return {{
 *   readingEase: number,
 *   gradeLevel: number,
 *   gradeLabel: string,
 *   easeLabel: string,
 *   wordCount: number,
 *   sentenceCount: number,
 *   syllableCount: number,
 * }|null} Null when there is not enough text to analyze.
 */
export function calculate(text) {
	const plain = stripHtml(text).trim();
	if (!plain) return null;

	// Sentence count: split on . ! ? followed by whitespace or end.
	const sentenceMatches = plain.match(/[.!?]+(?:\s|$)/g);
	// eslint-disable-next-line @wordpress/no-unused-vars-before-return
	const sentenceCount = Math.max(
		1,
		sentenceMatches ? sentenceMatches.length : 1
	);

	// Word count.
	const words = plain.split(/\s+/).filter(Boolean);
	const wordCount = words.length;
	if (wordCount === 0) return null;

	// Syllable count.
	let syllableCount = 0;
	for (const word of words) {
		syllableCount += countSyllables(word);
	}
	syllableCount = Math.max(wordCount, syllableCount);

	// Flesch Reading Ease.
	const readingEase = Math.min(
		100,
		Math.max(
			0,
			206.835 -
				1.015 * (wordCount / sentenceCount) -
				84.6 * (syllableCount / wordCount)
		)
	);

	// Flesch-Kincaid Grade Level.
	const gradeLevel = Math.max(
		0,
		0.39 * (wordCount / sentenceCount) +
			11.8 * (syllableCount / wordCount) -
			15.59
	);

	return {
		readingEase: Math.round(readingEase * 10) / 10,
		gradeLevel: Math.round(gradeLevel * 10) / 10,
		gradeLabel: gradeLabel(gradeLevel),
		easeLabel: easeLabel(readingEase),
		wordCount,
		sentenceCount,
		syllableCount,
	};
}
