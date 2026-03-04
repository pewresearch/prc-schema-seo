<?php
/**
 * Taxonomy UI class.
 *
 * Registers term edit screen fields for taxonomy SEO data.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Taxonomy UI class.
 *
 * Handles taxonomy term edit screen SEO fields (title, description, og_image, noindex)
 * and cache invalidation for term-level schema & meta tags.
 */
class Taxonomy_UI {
	/**
	 * Loader instance.
	 *
	 * @var Loader
	 */
	protected $loader;

	/** Term meta key */
	const TERM_META_KEY = '_prc_seo_term_data';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Loader instance.
	 */
	/**
	 * Constructor.
	 *
	 * @param Loader $loader Loader instance used to register actions.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;
		// Hooks for category, tag & expertise edit forms (extendable to other taxonomies via filter).
		$taxonomies = apply_filters( 'prc_schema_seo_enabled_taxonomies_for_term_meta', array( 'category', 'post_tag', 'areas-of-expertise' ) );
		foreach ( $taxonomies as $taxonomy ) {
			$loader->add_action( $taxonomy . '_edit_form_fields', $this, 'render_term_fields' );
			$loader->add_action( 'edited_' . $taxonomy, $this, 'save_term', 10, 2 );
		}
	}

	/**
	 * Render custom fields in term edit form.
	 */
	/**
	 * Render custom term SEO fields in the edit form.
	 *
	 * @param \WP_Term $term Current term object.
	 * @return void Outputs HTML directly.
	 */
	public function render_term_fields( $term ) {
		$meta             = get_term_meta( $term->term_id, self::TERM_META_KEY, true );
		$meta             = is_array( $meta ) ? $meta : array();
		$title            = $meta['title'] ?? '';
		$description      = $meta['description'] ?? '';
		$og_image         = ! empty( $meta['og_image'] ) ? absint( $meta['og_image'] ) : 0;
		$noindex          = ! empty( $meta['noindex'] );
		$term_code        = $meta['term_code'] ?? '';
		$defined_term_set = $meta['defined_term_set'] ?? '';
		$is_about_term    = ! empty( $meta['is_about_term'] );
		$expertise_id     = ! empty( $meta['expertise_id'] ) ? absint( $meta['expertise_id'] ) : 0;
		$contact_staff_id = ! empty( $meta['contact_staff_id'] ) ? absint( $meta['contact_staff_id'] ) : 0;

		wp_nonce_field( 'prc_seo_term_save', 'prc_seo_term_nonce' );
		?>
		<tr class="form-field prc-seo-term-title-field">
			<th scope="row"><label for="prc_seo_term_title"><?php esc_html_e( 'SEO Title', 'prc-schema-seo' ); ?></label></th>
			<td>
				<input name="prc_seo_term_title" id="prc_seo_term_title" type="text" value="<?php echo esc_attr( $title ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Optional custom SEO title for this term archive.', 'prc-schema-seo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field prc-seo-term-description-field">
			<th scope="row"><label for="prc_seo_term_description"><?php esc_html_e( 'SEO Description', 'prc-schema-seo' ); ?></label></th>
			<td>
				<textarea name="prc_seo_term_description" id="prc_seo_term_description" rows="4" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Optional custom meta description for this term archive.', 'prc-schema-seo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field prc-seo-term-og-image-field">
			<th scope="row"><label for="prc_seo_term_og_image"><?php esc_html_e( 'OG Image Attachment ID', 'prc-schema-seo' ); ?></label></th>
			<td>
				<input name="prc_seo_term_og_image" id="prc_seo_term_og_image" type="number" min="0" value="<?php echo esc_attr( $og_image ); ?>" />
				<p class="description"><?php esc_html_e( 'Provide attachment ID for Open Graph image (future media UI integration).', 'prc-schema-seo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field prc-seo-term-noindex-field">
			<th scope="row"><label for="prc_seo_term_noindex"><?php esc_html_e( 'Noindex', 'prc-schema-seo' ); ?></label></th>
			<td>
				<label><input name="prc_seo_term_noindex" id="prc_seo_term_noindex" type="checkbox" value="1" <?php checked( $noindex ); ?> /> <?php esc_html_e( 'Prevent search engine indexing of this term archive.', 'prc-schema-seo' ); ?></label>
			</td>
		</tr>

		<?php // Schema.org DefinedTerm Fields. ?>
		<tr class="form-field prc-seo-term-code-field">
			<th scope="row"><label for="prc_seo_term_code"><?php esc_html_e( 'Term Code', 'prc-schema-seo' ); ?></label></th>
			<td>
				<input name="prc_seo_term_code" id="prc_seo_term_code" type="text" value="<?php echo esc_attr( $term_code ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Schema.org termCode identifier (e.g., "POL" for Politics). Used in DefinedTerm schema.', 'prc-schema-seo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field prc-seo-defined-term-set-field">
			<th scope="row"><label for="prc_seo_defined_term_set"><?php esc_html_e( 'DefinedTermSet URL', 'prc-schema-seo' ); ?></label></th>
			<td>
				<input name="prc_seo_defined_term_set" id="prc_seo_defined_term_set" type="url" value="<?php echo esc_url( $defined_term_set ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Optional custom DefinedTermSet URL. Defaults to taxonomy archive URL if empty.', 'prc-schema-seo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field prc-seo-is-about-term-field">
			<th scope="row"><label for="prc_seo_is_about_term"><?php esc_html_e( 'Include in About Property', 'prc-schema-seo' ); ?></label></th>
			<td>
				<label><input name="prc_seo_is_about_term" id="prc_seo_is_about_term" type="checkbox" value="1" <?php checked( $is_about_term ); ?> /> <?php esc_html_e( 'Include this term as a DefinedTerm in the "about" property of posts.', 'prc-schema-seo' ); ?></label>
			</td>
		</tr>

		<?php
		// Show expertise area binding for category taxonomy only.
		if ( 'category' === $term->taxonomy ) {
			$this->render_expertise_binding_field( $term, $expertise_id );
		}

		// Show contact staff field for expertise areas only.
		if ( 'areas-of-expertise' === $term->taxonomy ) {
			$this->render_contact_staff_field( $term, $contact_staff_id );
		}
	}

	/**
	 * Render the expertise area binding field for categories.
	 *
	 * @param \WP_Term $term         The category term object.
	 * @param int      $expertise_id Current expertise area ID.
	 * @return void Outputs HTML directly.
	 */
	private function render_expertise_binding_field( $term, $expertise_id ) {
		// Get all expertise areas.
		$expertise_terms = get_terms(
			array(
				'taxonomy'   => 'areas-of-expertise',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $expertise_terms ) || empty( $expertise_terms ) ) {
			return;
		}
		?>
		<tr class="form-field prc-seo-expertise-binding-field">
			<th scope="row"><label for="prc_seo_expertise_id"><?php esc_html_e( 'Linked Expertise Area', 'prc-schema-seo' ); ?></label></th>
			<td>
				<select name="prc_seo_expertise_id" id="prc_seo_expertise_id">
					<option value=""><?php esc_html_e( '— Select Expertise Area —', 'prc-schema-seo' ); ?></option>
					<?php foreach ( $expertise_terms as $expertise_term ) : ?>
						<option value="<?php echo esc_attr( $expertise_term->term_id ); ?>" <?php selected( $expertise_id, $expertise_term->term_id ); ?>>
							<?php echo esc_html( $expertise_term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Link this category to an expertise area. Used for contact resolution and schema generation.', 'prc-schema-seo' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render the contact staff field for expertise areas.
	 *
	 * @param \WP_Term $term            The expertise term object.
	 * @param int      $contact_staff_id Current contact staff post ID.
	 * @return void Outputs HTML directly.
	 */
	private function render_contact_staff_field( $term, $contact_staff_id ) {
		// Get all published staff posts.
		$staff_posts = get_posts(
			array(
				'post_type'      => 'staff',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( empty( $staff_posts ) ) {
			return;
		}
		?>
		<tr class="form-field prc-seo-contact-staff-field">
			<th scope="row"><label for="prc_seo_contact_staff_id"><?php esc_html_e( 'Contact Point Staff', 'prc-schema-seo' ); ?></label></th>
			<td>
				<select name="prc_seo_contact_staff_id" id="prc_seo_contact_staff_id">
					<option value=""><?php esc_html_e( '— Select Staff Member —', 'prc-schema-seo' ); ?></option>
					<?php foreach ( $staff_posts as $staff_post ) : ?>
						<option value="<?php echo esc_attr( $staff_post->ID ); ?>" <?php selected( $contact_staff_id, $staff_post->ID ); ?>>
							<?php echo esc_html( $staff_post->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Select the Staff member who serves as the Contact Point for this expertise area. Posts with primary categories linked to this expertise will use this contact.', 'prc-schema-seo' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save term meta when term edited.
	 */
	/**
	 * Persist submitted term SEO fields to term meta.
	 *
	 * @param int $term_id Term ID being saved.
	 * @param int $tt_id   Term taxonomy ID (unused, provided by hook signature).
	 * @return void
	 */
	public function save_term( $term_id, $tt_id ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- WP hook signature.
		// Verify nonce for security.
		if ( ! isset( $_POST['prc_seo_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['prc_seo_term_nonce'] ) ), 'prc_seo_term_save' ) ) {
			return;
		}

		$meta = get_term_meta( $term_id, self::TERM_META_KEY, true );
		$meta = is_array( $meta ) ? $meta : array();

		if ( isset( $_POST['prc_seo_term_title'] ) ) {
			$meta['title'] = sanitize_text_field( wp_unslash( $_POST['prc_seo_term_title'] ) );
		}
		if ( isset( $_POST['prc_seo_term_description'] ) ) {
			$meta['description'] = sanitize_textarea_field( wp_unslash( $_POST['prc_seo_term_description'] ) );
		}
		if ( isset( $_POST['prc_seo_term_og_image'] ) ) {
			$meta['og_image'] = absint( $_POST['prc_seo_term_og_image'] );
		}
		$meta['noindex'] = isset( $_POST['prc_seo_term_noindex'] );

		// DefinedTerm fields.
		if ( isset( $_POST['prc_seo_term_code'] ) ) {
			$meta['term_code'] = sanitize_text_field( wp_unslash( $_POST['prc_seo_term_code'] ) );
		}
		if ( isset( $_POST['prc_seo_defined_term_set'] ) ) {
			$meta['defined_term_set'] = esc_url_raw( wp_unslash( $_POST['prc_seo_defined_term_set'] ) );
		}
		$meta['is_about_term'] = isset( $_POST['prc_seo_is_about_term'] );

		// Expertise binding for categories.
		if ( isset( $_POST['prc_seo_expertise_id'] ) ) {
			$meta['expertise_id'] = absint( $_POST['prc_seo_expertise_id'] );
		}

		// Contact staff for expertise areas.
		if ( isset( $_POST['prc_seo_contact_staff_id'] ) ) {
			$meta['contact_staff_id'] = absint( $_POST['prc_seo_contact_staff_id'] );
		}

		update_term_meta( $term_id, self::TERM_META_KEY, $meta );

		// Invalidate term schema & meta tag caches.
		wp_cache_delete( 'term_schema_' . $term_id, Generator::CACHE_GROUP );
		wp_cache_delete( 'meta_tags_term_' . $term_id, Meta_Tags::CACHE_GROUP );

		// Fire action for cache invalidation of related content.
		do_action( 'prc_schema_seo_term_meta_updated', $term_id, $meta );
	}
}
