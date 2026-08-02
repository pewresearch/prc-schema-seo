<?php
/**
 * Reading_Score class.
 *
 * Calculates Flesch-Kincaid readability metrics for a post or arbitrary text,
 * and exposes those metrics as a WP Ability for cross-plugin use.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Reading_Score
 *
 * Provides Flesch-Kincaid Reading Ease and Grade Level analysis.
 * Registered as the WP Ability `prc-schema-seo/reading-score` so any plugin
 * or AI agent can call it via the Abilities API.
 */
class Reading_Score {

	/**
	 * Plugin file used to validate activation on the target site.
	 *
	 * @var string
	 */
	private const PLUGIN_FILE = 'prc-schema-seo/prc-schema-seo.php';

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	public static $ability_name = 'prc-schema-seo/reading-score';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Loader instance for hook registration.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'wp_abilities_api_init', $this, 'register_ability' );
	}

	/**
	 * Register the reading score ability with the WP Abilities API.
	 *
	 * @hook wp_abilities_api_init
	 */
	public function register_ability() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::$ability_name,
			array(
				'label'               => __( 'Analyze Reading Level', 'prc-schema-seo' ),
				'description'         => __( 'Calculates Flesch-Kincaid Reading Ease and Grade Level for a post or arbitrary text. Accepts either a post_id or a raw text string.', 'prc-schema-seo' ),
				'category'            => 'data-analysis',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'Post ID to analyze. Uses post content only (same corpus as the editor Reading Score panel). Ignored when text is provided.',
						),
						'text'    => array(
							'type'        => 'string',
							'description' => 'Raw text to analyze. Takes precedence over post_id.',
						),
						'site_id' => \PRC\Platform\AI\Utils\site_id_input_schema_property(),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'reading_ease'   => array(
							'type'        => 'number',
							'description' => 'Flesch Reading Ease score (0–100). Higher = easier to read.',
						),
						'grade_level'    => array(
							'type'        => 'number',
							'description' => 'Flesch-Kincaid Grade Level (approximate US school grade).',
						),
						'grade_label'    => array(
							'type'        => 'string',
							'description' => 'Human-readable grade label, e.g. "8th Grade".',
						),
						'ease_label'     => array(
							'type'        => 'string',
							'description' => 'Human-readable reading ease label, e.g. "Standard".',
						),
						'word_count'     => array(
							'type'        => 'integer',
							'description' => 'Gutenberg editor document word count for the same corpus (matches Post sidebar "X words" via @wordpress/wordcount).',
						),
						'sentence_count' => array(
							'type'        => 'integer',
							'description' => 'Total sentence count of the analyzed text.',
						),
						'syllable_count' => array(
							'type'        => 'integer',
							'description' => 'Total syllable count of the analyzed text.',
						),
						'error'          => array(
							'type'        => 'string',
							'description' => 'Error message, if any.',
						),
					),
				),
				'execute_callback'    => function ( $input ) {
					return $this->with_site_when_needed(
						$input,
						function () use ( $input ) {
							return $this->execute( $input );
						}
					);
				},
				'permission_callback' => function ( $input = null ) {
					return $this->with_site_when_needed(
						$input,
						function () {
							return current_user_can( 'edit_posts' );
						}
					);
				},
				'meta'                => array(
					'annotations'  => array(
						'instructions' => 'Accepts a post_id or raw text and returns Flesch-Kincaid Reading Ease score, Grade Level, and supporting counts (sentences, syllables) plus word_count. word_count matches the Gutenberg editor document summary (Post sidebar "X words") via the @wordpress/wordcount algorithm; Ease/Grade use a separate Flesch-Kincaid pipeline and are not derived from that word count. For post_id analysis, post content is analyzed (excerpt is excluded) so Ease/Grade match the editor Reading Score panel. Optionally pass site_id to run against a specific multisite blog; defaults to the content site (20). If this plugin is inactive on the target site, the ability returns plugin_inactive_on_site. Pure text analysis runs on the current blog unless site_id is explicitly passed. Use to assess content readability before publication or as part of an editorial quality check. Does not require AI — computation is deterministic.',
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Execute callback for the WP Ability.
	 *
	 * @param array $input Input parameters: post_id or text.
	 * @return array Analysis result.
	 */
	public function execute( $input ) {
		$raw_text = '';

		// text takes precedence over post_id.
		if ( ! empty( $input['text'] ) && is_string( $input['text'] ) ) {
			$raw_text = $input['text'];
		} elseif ( ! empty( $input['post_id'] ) ) {
			$post_id = absint( $input['post_id'] );
			$post    = get_post( $post_id );

			if ( ! $post ) {
				return array(
					'error'          => 'Post not found.',
					'reading_ease'   => 0.0,
					'grade_level'    => 0.0,
					'grade_label'    => '',
					'ease_label'     => '',
					'word_count'     => 0,
					'sentence_count' => 0,
					'syllable_count' => 0,
				);
			}

			// Match the editor Reading Score panel: analyze post content only.
			$raw_text = (string) $post->post_content;
		}

		if ( empty( $raw_text ) ) {
			return array(
				'error'          => 'No text to analyze. Provide a post_id with content or a text string.',
				'reading_ease'   => 0.0,
				'grade_level'    => 0.0,
				'grade_label'    => '',
				'ease_label'     => '',
				'word_count'     => 0,
				'sentence_count' => 0,
				'syllable_count' => 0,
			);
		}

		$result               = $this->calculate( $raw_text );
		// Ability surface: report Gutenberg document word count; keep FK internals for Ease/Grade.
		$result['word_count'] = $this->count_document_words( $raw_text );
		$result['error']      = '';
		return $result;
	}

	/**
	 * Count words with the Gutenberg editor document algorithm (@wordpress/wordcount, type words).
	 *
	 * Matches JS defaults in @wordpress/wordcount defaultSettings + countWords pipeline.
	 * Digits are not stripped (unlike block_core_post_time_to_read_word_count).
	 *
	 * @param string $text Raw post content or text (HTML allowed).
	 * @return int Word count.
	 */
	private function count_document_words( string $text ): int {
		// 1–2. Strip tags → newline; strip HTML comments.
		$text = preg_replace( '/<\/?[a-z][^>]*?>/i', "\n", $text ) ?? $text;
		$text = preg_replace( '/<!--[\s\S]*?-->/', '', $text ) ?? $text;
		// 3. Shortcodes: JS default list is empty — skip.
		// 4. Encoded spaces → space.
		$text = preg_replace( '/&nbsp;|&#160;/i', ' ', $text ) ?? $text;
		// 5. Strip HTML entities (remove, do not decode).
		$text = preg_replace( '/&\S+?;/', '', $text ) ?? $text;
		// 6. Connectors → space.
		$text = preg_replace( '/--|\x{2014}/u', ' ', $text ) ?? $text;
		// 7. removeRegExp from JS defaultSettings (excludes digits 0-9).
		$remove = '/['
			. '\x{0021}-\x{002F}\x{003A}-\x{0040}\x{005B}-\x{0060}\x{007B}-\x{007E}'
			. '\x{0080}-\x{00BF}\x{00D7}\x{00F7}'
			. '\x{2000}-\x{2BFF}'
			. '\x{2E00}-\x{2E7F}'
			. ']/u';
		$text   = preg_replace( $remove, '', $text ) ?? $text;
		// 8. Append newline then match words (same order as JS countWords).
		$text  = $text . "\n";
		$count = preg_match_all( '/\S\s+/u', $text, $matches );

		return (int) $count;
	}

	/**
	 * Calculate Flesch-Kincaid metrics for a given text.
	 *
	 * Returns FK-internal word_count used by the Ease/Grade formulas (entity decode +
	 * whitespace split). Callers of execute() overwrite word_count with the Gutenberg
	 * document count before returning ability output.
	 *
	 * @param string $text Raw text (may include HTML — stripped internally).
	 * @return array {
	 *     @type float  $reading_ease   Flesch Reading Ease (0–100).
	 *     @type float  $grade_level    Flesch-Kincaid Grade Level.
	 *     @type string $grade_label    Human-readable grade label.
	 *     @type string $ease_label     Human-readable ease label.
	 *     @type int    $word_count     FK-internal word count (not Gutenberg).
	 *     @type int    $sentence_count Total sentences.
	 *     @type int    $syllable_count Total syllables.
	 * }
	 */
	public function calculate( string $text ): array {
		// Strip HTML tags, decode entities to Unicode, collapse whitespace.
		// Keep this preprocess contract identical to the JS stripHtml() helper.
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? '';
		$text = trim( $text );

		if ( empty( $text ) ) {
			return $this->empty_result();
		}

		// Count sentences: split on . ! ? followed by whitespace or end-of-string.
		$sentence_count = preg_match_all( '/[.!?]+(?:\s|$)/u', $text, $matches );
		// Minimum 1 sentence to avoid division by zero.
		$sentence_count = max( 1, (int) $sentence_count );

		// Count words.
		$words      = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$word_count = count( $words );

		if ( $word_count === 0 ) {
			return $this->empty_result();
		}

		// Count syllables across all words.
		$syllable_count = 0;
		foreach ( $words as $word ) {
			$syllable_count += $this->count_syllables( $word );
		}
		// Minimum 1 syllable per word.
		$syllable_count = max( $word_count, $syllable_count );

		// Flesch Reading Ease = 206.835 - 1.015*(words/sentences) - 84.6*(syllables/words).
		$reading_ease = 206.835
			- ( 1.015 * ( $word_count / $sentence_count ) )
			- ( 84.6 * ( $syllable_count / $word_count ) );
		$reading_ease = round( min( 100.0, max( 0.0, $reading_ease ) ), 1 );

		// Flesch-Kincaid Grade Level = 0.39*(words/sentences) + 11.8*(syllables/words) - 15.59.
		$grade_level = ( 0.39 * ( $word_count / $sentence_count ) )
			+ ( 11.8 * ( $syllable_count / $word_count ) )
			- 15.59;
		$grade_level = round( max( 0.0, $grade_level ), 1 );

		return array(
			'reading_ease'   => $reading_ease,
			'grade_level'    => $grade_level,
			'grade_label'    => $this->grade_label( $grade_level ),
			'ease_label'     => $this->ease_label( $reading_ease ),
			'word_count'     => $word_count,
			'sentence_count' => $sentence_count,
			'syllable_count' => $syllable_count,
		);
	}

	/**
	 * Count the syllables in a single word using English heuristics.
	 *
	 * @param string $word A single word (may contain punctuation — stripped internally).
	 * @return int Syllable count, minimum 1.
	 */
	private function count_syllables( string $word ): int {
		// Strip non-alpha characters and lower-case.
		$word = strtolower( preg_replace( '/[^a-zA-Z]/u', '', $word ) );

		if ( empty( $word ) ) {
			return 1;
		}

		// Remove trailing silent 'e' (but not words like "the").
		if ( strlen( $word ) > 2 && 'e' === substr( $word, -1 ) ) {
			$word = substr( $word, 0, -1 );
		}

		// Count vowel groups as syllables.
		$count = preg_match_all( '/[aeiouy]+/i', $word, $matches );

		return max( 1, (int) $count );
	}

	/**
	 * Convert a numeric grade level to a human-readable label.
	 *
	 * @param float $grade_level Flesch-Kincaid grade level.
	 * @return string Human-readable label.
	 */
	private function grade_label( float $grade_level ): string {
		if ( $grade_level <= 1 ) {
			return __( 'Kindergarten', 'prc-schema-seo' );
		}
		if ( $grade_level <= 6 ) {
			/* translators: %d is the school grade level number (e.g., 4th Grade). */
			return sprintf( __( '%dth Grade', 'prc-schema-seo' ), (int) $grade_level );
		}
		if ( $grade_level <= 8 ) {
			/* translators: %d is the school grade level number (e.g., 7th Grade). */
			return sprintf( __( '%dth Grade', 'prc-schema-seo' ), (int) $grade_level );
		}
		if ( $grade_level <= 9 ) {
			return __( '9th Grade', 'prc-schema-seo' );
		}
		if ( $grade_level <= 10 ) {
			return __( '10th Grade', 'prc-schema-seo' );
		}
		if ( $grade_level <= 11 ) {
			return __( '11th Grade', 'prc-schema-seo' );
		}
		if ( $grade_level <= 12 ) {
			return __( '12th Grade / High School', 'prc-schema-seo' );
		}
		if ( $grade_level <= 16 ) {
			return __( 'College Level', 'prc-schema-seo' );
		}
		return __( 'Graduate Level', 'prc-schema-seo' );
	}

	/**
	 * Convert a Flesch Reading Ease score to a human-readable label.
	 *
	 * @param float $score Flesch Reading Ease score (0–100).
	 * @return string Human-readable label.
	 */
	private function ease_label( float $score ): string {
		if ( $score >= 90 ) {
			return __( 'Very Easy', 'prc-schema-seo' );
		}
		if ( $score >= 80 ) {
			return __( 'Easy', 'prc-schema-seo' );
		}
		if ( $score >= 70 ) {
			return __( 'Fairly Easy', 'prc-schema-seo' );
		}
		if ( $score >= 60 ) {
			return __( 'Standard', 'prc-schema-seo' );
		}
		if ( $score >= 50 ) {
			return __( 'Fairly Difficult', 'prc-schema-seo' );
		}
		if ( $score >= 30 ) {
			return __( 'Difficult', 'prc-schema-seo' );
		}
		return __( 'Very Confusing', 'prc-schema-seo' );
	}

	/**
	 * Return a zeroed-out result structure.
	 *
	 * @return array Empty result.
	 */
	private function empty_result(): array {
		return array(
			'reading_ease'   => 0.0,
			'grade_level'    => 0.0,
			'grade_label'    => '',
			'ease_label'     => '',
			'word_count'     => 0,
			'sentence_count' => 0,
			'syllable_count' => 0,
		);
	}

	/**
	 * Run a callback on the requested target site for post-based or explicitly targeted invocations.
	 *
	 * @param array|null $input    Ability input.
	 * @param callable   $callback Callback to run after site validation/switching.
	 * @return mixed
	 */
	private function with_site_when_needed( $input, callable $callback ) {
		if ( ! is_array( $input ) || ( empty( $input['post_id'] ) && ! isset( $input['site_id'] ) && ! isset( $input['blog_id'] ) ) ) {
			return $callback();
		}

		return \PRC\Platform\AI\Utils\with_site(
			\PRC\Platform\AI\Utils\resolve_site_id( $input ),
			self::PLUGIN_FILE,
			$callback
		);
	}
}
