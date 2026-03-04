<?php
/**
 * Admin Columns Integration
 *
 * Registers custom Admin Columns Pro columns for SEO data.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

use AC\ListScreen;

/**
 * Admin Columns Integration
 *
 * @package PRC\Platform\Schema_SEO
 */
class Admin_Columns {

	/**
	 * Initialize the class and set its properties.
	 *
	 * The ac/ready and ac/column_groups hooks only fire when Admin Columns
	 * is active, so no class_exists check is needed.
	 *
	 * @param Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'ac/ready', $this, 'register_columns' );
		$loader->add_action( 'ac/column_groups', $this, 'register_column_group' );
	}

	/**
	 * Register the PRC SEO column group.
	 *
	 * @param \AC\Groups $groups The column groups.
	 */
	public function register_column_group( $groups ) {
		$groups->add( 'prc-seo', __( 'PRC SEO', 'prc-schema-seo' ), 15 );
	}

	/**
	 * Register the custom columns.
	 */
	public function register_columns() {
		add_action(
			'ac/column_types',
			function ( ListScreen $list_screen ) {
				// Only register for Post list screens.
				if ( ! $list_screen instanceof \AC\ListScreen\Post ) {
					return;
				}

				// Only register for post types with SEO support.
				$post_type = $list_screen->get_post_type();
				if ( ! post_type_supports( $post_type, 'prc-schema-seo' ) ) {
					return;
				}

				// Load storage and editing classes.
				require_once __DIR__ . '/storage/class-seo-data-storage.php';
				require_once __DIR__ . '/editing/class-primary-term-editing.php';

				// Load column classes.
				require_once __DIR__ . '/columns/class-title.php';
				require_once __DIR__ . '/columns/class-description.php';
				require_once __DIR__ . '/columns/class-noindex.php';
				require_once __DIR__ . '/columns/class-schema-type.php';
				require_once __DIR__ . '/columns/class-primary-term.php';

				// Register column types.
				$list_screen->register_column_type( new Columns\Title() );
				$list_screen->register_column_type( new Columns\Description() );
				$list_screen->register_column_type( new Columns\Noindex() );
				$list_screen->register_column_type( new Columns\Schema_Type() );
				$list_screen->register_column_type( new Columns\Primary_Term() );
			}
		);
	}
}
