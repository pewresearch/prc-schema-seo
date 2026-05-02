<?php
/**
 * SEO AI Experiment.
 *
 * Registers the SEO AI Suggest experiment with the WordPress AI Experiments
 * plugin. When enabled, this experiment registers the prc-schema-seo/suggest
 * ability and adds AI suggestion buttons to the SEO editor sidebar panels
 * for search title, description, and social text fields.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

use WordPress\AI\Abstracts\Abstract_Feature;
use WordPress\AI\Experiments\Experiment_Category;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO AI Experiment class.
 *
 * @since 1.0.0
 */
class SEO_AI_Experiment extends Abstract_Feature {

	/**
	 * Feature identifier.
	 *
	 * @since 1.0.0
	 */
	public static function get_id(): string {
		return 'seo-ai-suggest';
	}

	/**
	 * Loads feature metadata.
	 *
	 * @since 1.0.0
	 *
	 * @return array{label: string, description: string, category: string} Feature metadata.
	 */
	protected function load_metadata(): array {
		return array(
			'label'       => __( 'SEO AI Suggest', 'prc-schema-seo' ),
			'description' => __( 'Uses AI to generate optimized SEO titles, meta descriptions, and social sharing text based on post content. Adds "Suggest with AI" buttons to the SEO sidebar panels in the editor.', 'prc-schema-seo' ),
			'category'    => Experiment_Category::EDITOR,
		);
	}

	/**
	 * Registers the experiment's hooks and functionality.
	 *
	 * This method is only called when the experiment is enabled.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		// Register the AI ability when the experiment is enabled.
		$ability = new SEO_AI_Ability();
		add_action( 'wp_abilities_api_init', array( $ability, 'register_ability' ) );

		// Localize experiment data for the editor.
		add_action( 'enqueue_block_editor_assets', array( $this, 'localize_experiment_data' ), 20 );
	}

	/**
	 * Localizes the experiment enabled state for the editor script.
	 *
	 * The block editor script is already enqueued by the Editor_UI class.
	 * We add a localized variable to tell the JS components that the AI
	 * experiment is active.
	 *
	 * @hook enqueue_block_editor_assets
	 * @since 1.0.0
	 */
	public function localize_experiment_data(): void {
		$handle = 'prc-schema-seo-block-editor';

		// Only localize if the parent script is enqueued.
		if ( ! wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}

		wp_localize_script(
			$handle,
			'PRCSchemaSEOAI',
			array(
				'enabled'     => true,
				'abilityName' => SEO_AI_Ability::$ability_name,
			)
		);
	}
}
