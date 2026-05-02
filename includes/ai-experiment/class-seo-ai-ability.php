<?php
/**
 * SEO AI Ability.
 *
 * A single ability that generates AI suggestions for SEO title, meta description,
 * and social (Open Graph) text based on a post's content.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO AI Ability class.
 *
 * @since 1.0.0
 */
class SEO_AI_Ability {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	public static $ability_name = 'prc-schema-seo/suggest';

	/**
	 * Register the ability with WP Abilities API.
	 *
	 * @hook wp_abilities_api_init
	 */
	public function register_ability() {
		wp_register_ability(
			self::$ability_name,
			array(
				'label'               => __( 'Suggest SEO Metadata', 'prc-schema-seo' ),
				'description'         => __( 'Generates AI suggestions for SEO title, meta description, and social sharing text based on a post\'s content.', 'prc-schema-seo' ),
				'category'            => 'data-retrieval',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'The post ID to generate SEO metadata for.',
						),
						'fields'  => array(
							'type'        => 'array',
							'description' => 'Which fields to generate. Defaults to all. Options: title, description, og_title, og_description.',
							'items'       => array(
								'type' => 'string',
								'enum' => array( 'title', 'description', 'og_title', 'og_description' ),
							),
							'default'     => array( 'title', 'description', 'og_title', 'og_description' ),
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'error'       => array(
							'type'        => 'string',
							'description' => 'An error message, if any.',
						),
						'suggestions' => array(
							'type'       => 'object',
							'properties' => array(
								'title'          => array(
									'type'        => 'string',
									'description' => 'Suggested SEO title (max 60 chars).',
								),
								'description'    => array(
									'type'        => 'string',
									'description' => 'Suggested meta description (max 160 chars).',
								),
								'og_title'       => array(
									'type'        => 'string',
									'description' => 'Suggested social sharing title (max 70 chars).',
								),
								'og_description' => array(
									'type'        => 'string',
									'description' => 'Suggested social sharing description (max 200 chars).',
								),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'generate_seo_suggestions' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations'  => array(
						'instructions' => 'This ability takes a post ID, reads the post content, title, and excerpt, then uses AI to generate optimized SEO metadata suggestions including search title, meta description, and social sharing text. When the Content Guidelines plugin is active, site-level voice, tone, vocabulary, and copy rules are automatically incorporated into the system instructions as authoritative editorial constraints, ensuring generated metadata aligns with organizational standards.',
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => false,
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
	 * Role and brand rules shared by all generated metadata fields.
	 *
	 * @return string System instruction fragment.
	 */
	private static function get_instructions_brand_context(): string {
		$org_name = apply_filters( 'prc_schema_seo_organization_name', 'Pew Research Center' );

		$instructions = <<<INSTRUCTIONS
You are an SEO specialist for {$org_name}, a nonpartisan research organization. Your task is to generate optimized metadata for research content.

BRAND GUIDELINES:
- {$org_name} produces objective, nonpartisan research. Never use sensational, clickbait, or opinion language.
- Tone should be authoritative, clear, and informative.
- Use active voice when possible.
- Do NOT include "{$org_name}" in the SEO title unless specifically relevant — search engines append the site name automatically.
INSTRUCTIONS;

		/**
		 * Filter the full AI brand context block (system instructions).
		 *
		 * @param string $instructions Brand context text.
		 */
		return apply_filters( 'prc_schema_seo_ai_brand_context', $instructions );
	}

	/**
	 * Title casing mandate and bullet for SEO and social titles (aligned with platform-core title generation).
	 *
	 * @param string|null $post_type Post type slug; null defaults to title case.
	 * @return array{mandate: string, bullet: string}
	 */
	private static function get_title_casing_parts_for_post_type( ?string $post_type ): array {
		$is_short_read = 'short-read' === $post_type;
		$mandate       = $is_short_read
			? 'This post is a short read: the SEO title and social title must use sentence case only (not title case).'
			: ( null !== $post_type
				? 'This post is not a short read: the SEO title and social title must use title case (not sentence case).'
				: 'The SEO title and social title must use title case (not sentence case).' );
		$bullet        = $is_short_read
			? '- Use sentence case: capitalize only the first letter of the first word (and proper nouns as usual); do not use title case.'
			: '- Use title case: capitalize principal words (nouns, verbs, adjectives, and adverbs). Keep articles (a, an, the), coordinating conjunctions (and, but, or), and short prepositions (of, in, on, at, to, for) lowercase unless they begin or end the title.';

		return array(
			'mandate' => $mandate,
			'bullet'  => $bullet,
		);
	}

	/**
	 * Single mandate block when generating SEO and/or social titles (avoid repeating in each field section).
	 *
	 * @param string|null $post_type Post type slug.
	 * @return string System instruction fragment.
	 */
	private static function get_title_casing_mandate_section( ?string $post_type ): string {
		$casing = self::get_title_casing_parts_for_post_type( $post_type );

		return 'TITLE CASING (SEO title and social title):' . "\n" . $casing['mandate'];
	}

	/**
	 * System instructions for the SEO title field (`title`).
	 *
	 * @param string|null $post_type Post type slug.
	 * @return string System instruction fragment.
	 */
	private static function get_instructions_seo_title( ?string $post_type ): string {
		$casing   = self::get_title_casing_parts_for_post_type( $post_type );
		$org_name = apply_filters( 'prc_schema_seo_organization_name', 'Pew Research Center' );

		$key_findings_rule = apply_filters(
			'prc_schema_seo_ai_brand_rules',
			'- Do not use the phrase "key findings" in any casing unless the piece is clearly a standalone key-findings product. For typical reports and articles, use other framings; at ' . $org_name . ' this phrasing is reserved for specific packaging.'
		);

		return <<<INSTRUCTIONS
SEO TITLE GUIDELINES (max 60 characters):
{$casing['bullet']}
- Front-load the most important keywords.
- Be specific and descriptive about what the content covers.
- Avoid generic titles; each should be unique and specific to the content.
{$key_findings_rule}
- When the content summary includes publication timing (lines such as DRAFT LAST MODIFIED and SCHEDULED OR PUBLISHED), favor timely framing aligned with that publication window. Avoid making a survey field year or data collection year from the body the centerpiece of the title when it would read as stale relative to when the work is published.
- Align emphasis with the article primary argument as established by the TITLE and the opening of the CONTENT. Do not center the title on a statistic or example that appears only deep in the body unless the opening already establishes it as central. Do not let a vivid fact dominate if it sidesteps the headline core tension (for example, consensus on one item when the piece is about divisions or debate).
INSTRUCTIONS;
	}

	/**
	 * System instructions for the meta description field (`description`).
	 *
	 * @return string System instruction fragment.
	 */
	private static function get_instructions_seo_description(): string {
		return <<<'INSTRUCTIONS'
META DESCRIPTION GUIDELINES (max 160 characters):
- Summarize the key finding or takeaway of the research.
- Include a compelling reason for someone to click through.
- Use natural language that reads well as a search result snippet.
- Include relevant keywords naturally.
- When publication timing lines are present in the content summary, prefer framing consistent with that window. Do not anchor the description on a data or fieldwork year that undercuts a timely read relative to publication.
- Align with the primary argument from the TITLE and opening of the CONTENT. Do not open with a statistic or example that appears only deep in the body unless the opening establishes it as central.
- For meta descriptions only (not titles): when stating shares from one through ten, use spelled-out hyphenated forms (e.g. nine-in-ten, not "9 in 10").
INSTRUCTIONS;
	}

	/**
	 * System instructions for the social sharing title field (`og_title`).
	 *
	 * @param string|null $post_type Post type slug.
	 * @return string System instruction fragment.
	 */
	private static function get_instructions_social_title( ?string $post_type ): string {
		$casing = self::get_title_casing_parts_for_post_type( $post_type );

		return <<<INSTRUCTIONS
SOCIAL TITLE GUIDELINES (max 70 characters):
{$casing['bullet']}
- Slightly more engaging than the SEO title — optimized for social media feeds.
- You may highlight a finding or statistic only when it aligns with the TITLE and opening of the CONTENT; avoid emphasis that misleads or reflects a detail buried late in the piece.
- Should work well alongside a social sharing image.
- Follow the same "key findings", publication-year, and thesis-alignment rules as the SEO title. Do not use spelled-out hyphenated share forms in the social title (those apply only to descriptions).
INSTRUCTIONS;
	}

	/**
	 * System instructions for the social sharing description field (`og_description`).
	 *
	 * @return string System instruction fragment.
	 */
	private static function get_instructions_social_description(): string {
		return <<<'INSTRUCTIONS'
SOCIAL DESCRIPTION GUIDELINES (max 200 characters):
- Expand on the social title with additional context.
- Encourage engagement and sharing.
- Can pose a question or highlight the scope of the research.
- When publication timing lines are present, prefer framing consistent with that window; avoid anchoring on a data-year that undercuts timeliness.
- Align with the TITLE and opening of the CONTENT; do not open with a deep-body statistic unless the opening establishes it as central.
- For social descriptions only: when stating shares from one through ten, use spelled-out hyphenated forms (e.g. nine-in-ten). Do not use this style in titles.
INSTRUCTIONS;
	}

	/**
	 * JSON output contract for structured responses.
	 *
	 * @return string System instruction fragment.
	 */
	private static function get_instructions_json_contract(): string {
		return <<<'INSTRUCTIONS'
CRITICAL OUTPUT REQUIREMENTS:
- Return ONLY valid JSON matching the exact structure specified.
- Do not include explanatory text, markdown formatting, or commentary outside the JSON.
- Do not wrap JSON in code blocks or backticks.
- Return raw JSON only.
- Respect all character limits strictly.
INSTRUCTIONS;
	}

	/**
	 * Compose system instructions for only the requested metadata fields.
	 *
	 * @param array       $requested_keys      Field keys to include: title, description, og_title, og_description.
	 * @param string      $content_guidelines Optional content guidelines packet text from the Content Guidelines plugin.
	 * @param string|null $post_type          Post type slug for SEO/social title casing (short-read vs title case).
	 * @return string The full system instructions.
	 */
	private static function get_system_instructions( array $requested_keys, string $content_guidelines = '', ?string $post_type = null ): string {
		$sections  = array( self::get_instructions_brand_context() );
		$requested = array_flip( $requested_keys );
		if ( isset( $requested['title'] ) || isset( $requested['og_title'] ) ) {
			$sections[] = self::get_title_casing_mandate_section( $post_type );
		}
		$key_order = array( 'title', 'description', 'og_title', 'og_description' );
		$fragments = array(
			'title'          => self::get_instructions_seo_title( $post_type ),
			'description'    => self::get_instructions_seo_description(),
			'og_title'       => self::get_instructions_social_title( $post_type ),
			'og_description' => self::get_instructions_social_description(),
		);

		foreach ( $key_order as $key ) {
			if ( isset( $requested[ $key ] ) && isset( $fragments[ $key ] ) ) {
				$sections[] = $fragments[ $key ];
			}
		}

		$sections[]   = self::get_instructions_json_contract();
		$instructions = implode( "\n\n", $sections );

		if ( ! empty( $content_guidelines ) ) {
			$instructions .= "\n\nSITE CONTENT GUIDELINES (treat these as authoritative editorial constraints — all generated metadata must conform to them):\n\n" . $content_guidelines;
		}

		return $instructions;
	}

	/**
	 * Extract a content summary from a post for the AI prompt.
	 *
	 * @param \WP_Post $post The post object.
	 * @return string A trimmed content summary.
	 */
	private function get_content_summary( $post ) {
		$parts = array();

		// Post title.
		$parts[] = 'TITLE: ' . get_the_title( $post->ID );

		// Excerpt if available.
		if ( ! empty( $post->post_excerpt ) ) {
			$parts[] = 'EXCERPT: ' . $post->post_excerpt;
		}

		// Post content (trimmed to avoid token limits).
		$content = wp_strip_all_tags( $post->post_content );
		$content = wp_trim_words( $content, 500, '...' );
		if ( ! empty( $content ) ) {
			$parts[] = 'CONTENT: ' . $content;
		}

		// Categories.
		$categories = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			$parts[] = 'CATEGORIES: ' . implode( ', ', $categories );
		}

		// Post type.
		$parts[] = 'POST TYPE: ' . get_post_type( $post->ID );

		// Publication timing for timeliness (aligned with platform-core AI title/excerpt prompts).
		$draft_last_modified = get_post_modified_time( 'Y-m-d', false, $post );
		if ( ! is_string( $draft_last_modified ) ) {
			$draft_last_modified = '';
		}

		$status = $post->post_status;
		if ( 'future' === $status ) {
			$scheduled_local = get_post_time( 'Y-m-d H:i', false, $post );
			if ( ! is_string( $scheduled_local ) || '' === $scheduled_local ) {
				$scheduled_local = $post->post_date;
			}
			$scheduled_or_published = 'Scheduled for: ' . $scheduled_local;
		} elseif ( 'publish' === $status ) {
			$pub_date = get_post_time( 'Y-m-d', false, $post );
			if ( ! is_string( $pub_date ) || '' === $pub_date ) {
				$pub_date = $post->post_date;
			}
			$scheduled_or_published = 'Published: ' . $pub_date;
		} else {
			$scheduled_or_published = 'Not scheduled (draft or pending)';
		}

		$parts[] = 'DRAFT LAST MODIFIED: ' . $draft_last_modified;
		$parts[] = 'SCHEDULED OR PUBLISHED: ' . $scheduled_or_published;

		return implode( "\n\n", $parts );
	}

	/**
	 * Get site content guidelines for the prompt when the content-guidelines plugin is active.
	 *
	 * Uses the 'seo_metadata' task context to get a focused guidelines packet
	 * relevant to writing SEO titles and descriptions rather than body copy.
	 *
	 * @param int $post_id Post ID to fetch guidelines for (for block-aware context).
	 * @return string Packet text for LLM, or empty string if unavailable.
	 */
	private function get_content_guidelines( $post_id ) {
		if ( ! function_exists( 'PRC\Platform\AI\Utils\get_content_guidelines_for_post' ) ) {
			return '';
		}

		$result = \PRC\Platform\AI\Utils\get_content_guidelines_for_post( $post_id, array( 'task' => 'seo_metadata' ) );
		if ( empty( $result['packet_text'] ) || ! is_string( $result['packet_text'] ) ) {
			return '';
		}

		return trim( $result['packet_text'] );
	}

	/**
	 * Generate SEO metadata suggestions for a post.
	 *
	 * @param array $input The input parameters.
	 * @return array The result with suggestions.
	 */
	public function generate_seo_suggestions( $input ) {
		$post_id = $input['post_id'] ?? 0;
		$fields  = $input['fields'] ?? array( 'title', 'description', 'og_title', 'og_description' );

		if ( empty( $post_id ) ) {
			return array(
				'error'       => 'A valid post_id is required.',
				'suggestions' => new \stdClass(),
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'error'       => 'Post not found.',
				'suggestions' => new \stdClass(),
			);
		}

		// Check if the post type supports schema SEO.
		if ( ! post_type_supports( get_post_type( $post_id ), 'prc-schema-seo' ) ) {
			return array(
				'error'       => 'This post type does not support SEO metadata.',
				'suggestions' => new \stdClass(),
			);
		}

		// Check for meaningful content.
		if ( empty( $post->post_content ) && empty( $post->post_excerpt ) ) {
			return array(
				'error'       => 'Post has no content or excerpt. Add content before generating SEO suggestions.',
				'suggestions' => new \stdClass(),
			);
		}

		$content_summary = $this->get_content_summary( $post );

		// Build the field request description.
		$field_descriptions = array(
			'title'          => 'SEO title (max 60 characters)',
			'description'    => 'Meta description (max 160 characters)',
			'og_title'       => 'Social sharing title (max 70 characters)',
			'og_description' => 'Social sharing description (max 200 characters)',
		);

		$requested_fields = array();
		foreach ( $fields as $field ) {
			if ( isset( $field_descriptions[ $field ] ) ) {
				$requested_fields[ $field ] = $field_descriptions[ $field ];
			}
		}

		if ( empty( $requested_fields ) ) {
			return array(
				'error'       => 'No valid fields requested.',
				'suggestions' => new \stdClass(),
			);
		}

		$output_format = array();
		foreach ( $requested_fields as $key => $desc ) {
			$output_format[ $key ] = $desc;
		}

		$content_guidelines  = $this->get_content_guidelines( $post_id );
		$post_type           = get_post_type( $post_id );
		$system_instructions = self::get_system_instructions( array_keys( $requested_fields ), $content_guidelines, $post_type );

		$org_name = apply_filters( 'prc_schema_seo_organization_name', 'Pew Research Center' );

		$prompt = wp_sprintf(
			'Generate optimized SEO metadata for this %s content.

%s

Generate the following fields:
%s

Return ONLY a JSON object with these exact keys and string values: %s',
			$org_name,
			$content_summary,
			implode( "\n", array_map( fn( $k, $v ) => "- {$k}: {$v}", array_keys( $requested_fields ), array_values( $requested_fields ) ) ),
			wp_json_encode( $output_format )
		);

		// Build JSON schema for structured output (root `type` required — do not wrap in name/strict/schema; see wp_ai as_json_response).
		$schema_properties = array();
		foreach ( array_keys( $requested_fields ) as $key ) {
			$schema_properties[ $key ] = array(
				'type'        => 'string',
				'description' => $field_descriptions[ $key ] ?? $key,
			);
		}
		$json_schema = array(
			'type'                 => 'object',
			'properties'           => $schema_properties,
			'required'             => array_keys( $requested_fields ),
			'additionalProperties' => false,
		);

		try {
			$builder = wp_ai_client_prompt( $prompt );
			if ( is_wp_error( $builder ) ) {
				return array(
					'error'       => $builder->get_error_message(),
					'suggestions' => new \stdClass(),
				);
			}

			$response = $builder
				->using_system_instruction( $system_instructions )
				->using_temperature( 0.4 )
				->using_model_preference( ...\WordPress\AI\get_preferred_models_for_text_generation() )
				->as_json_response( $json_schema )
				->generate_text();

			if ( is_wp_error( $response ) ) {
				return array(
					'error'       => $response->get_error_message(),
					'suggestions' => new \stdClass(),
				);
			}

			$suggestions = json_decode( (string) $response, true );

			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $suggestions ) ) {
				return array(
					'error'       => 'Failed to parse AI response.',
					'suggestions' => new \stdClass(),
				);
			}

			// Enforce character limits.
			$limits = array(
				'title'          => 60,
				'description'    => 160,
				'og_title'       => 70,
				'og_description' => 200,
			);

			foreach ( $suggestions as $key => $value ) {
				if ( isset( $limits[ $key ] ) && is_string( $value ) ) {
					$suggestions[ $key ] = mb_substr( $value, 0, $limits[ $key ] );
				}
			}

			// Only return requested fields.
			$filtered = array();
			foreach ( $requested_fields as $key => $desc ) {
				if ( isset( $suggestions[ $key ] ) ) {
					$filtered[ $key ] = $suggestions[ $key ];
				}
			}

			return array(
				'error'       => '',
				'suggestions' => ! empty( $filtered ) ? $filtered : new \stdClass(),
			);
		} catch ( \Exception $e ) {
			return array(
				'error'       => 'AI generation failed: ' . $e->getMessage(),
				'suggestions' => new \stdClass(),
			);
		}
	}
}
