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

use WordPress\AiClient\AiClient;

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
	 * Get the system instructions for the AI, optionally incorporating site content guidelines.
	 *
	 * @param string $content_guidelines Optional content guidelines packet text from the Content Guidelines plugin.
	 * @return string The system instructions.
	 */
	private static function get_system_instructions( string $content_guidelines = '' ): string {
		$instructions = 'You are an SEO specialist for Pew Research Center, a nonpartisan research organization. Your task is to generate optimized metadata for research content.

BRAND GUIDELINES:
- Pew Research Center produces objective, nonpartisan research. Never use sensational, clickbait, or opinion language.
- Tone should be authoritative, clear, and informative.
- Use active voice when possible.
- Do NOT include "Pew Research Center" in the SEO title unless specifically relevant — search engines append the site name automatically.

SEO TITLE GUIDELINES (max 60 characters):
- Front-load the most important keywords.
- Be specific and descriptive about what the content covers.
- Use numbers and data points when available (e.g., "8 in 10 Americans...").
- Avoid generic titles; each should be unique and specific to the content.

META DESCRIPTION GUIDELINES (max 160 characters):
- Summarize the key finding or takeaway of the research.
- Include a compelling reason for someone to click through.
- Use natural language that reads well as a search result snippet.
- Include relevant keywords naturally.

SOCIAL TITLE GUIDELINES (max 70 characters):
- Slightly more engaging than the SEO title — optimized for social media feeds.
- Can highlight a surprising finding or key statistic.
- Should work well alongside a social sharing image.

SOCIAL DESCRIPTION GUIDELINES (max 200 characters):
- Expand on the social title with additional context.
- Encourage engagement and sharing.
- Can pose a question or highlight the scope of the research.

CRITICAL OUTPUT REQUIREMENTS:
- Return ONLY valid JSON matching the exact structure specified.
- Do not include explanatory text, markdown formatting, or commentary outside the JSON.
- Do not wrap JSON in code blocks or backticks.
- Return raw JSON only.
- Respect all character limits strictly.';

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
		if ( ! function_exists( 'wp_get_content_guidelines_for_post' ) ) {
			return '';
		}

		$result = \wp_get_content_guidelines_for_post( $post_id, array( 'task' => 'seo_metadata' ) );
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
		$system_instructions = self::get_system_instructions( $content_guidelines );

		$prompt = wp_sprintf(
			'Generate optimized SEO metadata for this Pew Research Center content.

%s

Generate the following fields:
%s

Return ONLY a JSON object with these exact keys and string values: %s',
			$content_summary,
			implode( "\n", array_map( fn( $k, $v ) => "- {$k}: {$v}", array_keys( $requested_fields ), array_values( $requested_fields ) ) ),
			wp_json_encode( $output_format )
		);

		// Build JSON schema for structured output (API requires response_format.type: 'json_schema').
		$schema_properties = array();
		foreach ( array_keys( $requested_fields ) as $key ) {
			$schema_properties[ $key ] = array(
				'type'        => 'string',
				'description' => $field_descriptions[ $key ] ?? $key,
			);
		}
		$json_schema = array(
			'name'   => 'seo_suggestions',
			'strict' => true,
			'schema' => array(
				'type'                 => 'object',
				'properties'           => $schema_properties,
				'required'             => array_keys( $requested_fields ),
				'additionalProperties' => false,
			),
		);

		try {
			$response = AiClient::prompt( $prompt )
				->usingSystemInstruction( $system_instructions )
				->usingTemperature( 0.4 )
				->asJsonResponse( $json_schema )
				->generateText();

			$suggestions = json_decode( $response, true );

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
