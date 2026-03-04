<?php
/**
 * Redirect_On_Slug_Change class.
 *
 * Detects slug changes on published posts and taxonomy terms,
 * automatically creates 301 redirects via Safe Redirect Manager,
 * and provides editor notices with an undo option.
 *
 * @package PRC\Platform\Schema_SEO
 */

namespace PRC\Platform\Schema_SEO;

/**
 * Redirect_On_Slug_Change class.
 */
class Redirect_On_Slug_Change {
	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'prc-schema-seo/v1';

	/**
	 * Loader instance.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Stashed term slugs keyed by term ID (captured before update).
	 *
	 * @var array<int, string>
	 */
	private $stashed_term_slugs = array();

	/**
	 * Stashed term URLs keyed by term ID (captured before update).
	 *
	 * @var array<int, string>
	 */
	private $stashed_term_urls = array();

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;

		// Post slug change detection.
		$this->loader->add_action( 'post_updated', $this, 'on_post_updated', 10, 3 );

		// Taxonomy term slug change detection.
		$this->loader->add_action( 'edit_terms', $this, 'stash_term_slug_before_update', 10, 2 );
		$this->loader->add_action( 'edited_term', $this, 'on_term_edited', 10, 3 );

		// Block editor notice: localize redirect data for JS.
		$this->loader->add_action( 'enqueue_block_editor_assets', $this, 'localize_redirect_notice_data', 20 );

		// Classic admin notice for taxonomy term screens.
		$this->loader->add_action( 'admin_notices', $this, 'render_term_redirect_admin_notice' );

		// REST endpoint for deleting a redirect (undo).
		$this->loader->add_action( 'rest_api_init', $this, 'register_rest_routes' );

		// AJAX handlers for term screens.
		$this->loader->add_action( 'wp_ajax_prc_seo_delete_redirect', $this, 'ajax_delete_redirect' );
		$this->loader->add_action( 'wp_ajax_prc_seo_check_term_redirect', $this, 'ajax_check_term_redirect' );

		// Enqueue inline script on taxonomy listing screens for quick-edit redirect notices.
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_term_list_redirect_script' );

		// Increase SRM's default max redirects from 1000 to 3000.
		$this->loader->add_filter( 'srm_max_redirects', $this, 'filter_max_redirects' );
	}

	/**
	 * Increase the maximum number of redirects SRM will handle.
	 *
	 * @hook srm_max_redirects
	 * @return int
	 */
	public function filter_max_redirects() {
		return 3000;
	}

	/**
	 * Check if the auto-redirect feature is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		if ( ! function_exists( 'srm_create_redirect' ) ) {
			return false;
		}
		return (bool) apply_filters( 'prc_schema_seo_auto_redirect_enabled', true );
	}

	/**
	 * Get the HTTP status code for auto-created redirects.
	 *
	 * @return int
	 */
	private function get_status_code() {
		return (int) apply_filters( 'prc_schema_seo_auto_redirect_status_code', 301 );
	}

	/**
	 * Check if a post type is eligible for auto-redirects.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private function is_post_type_eligible( $post_type ) {
		$eligible = post_type_supports( $post_type, 'prc-schema-seo' );

		/**
		 * Filter which post types are eligible for automatic slug-change redirects.
		 *
		 * @param bool   $eligible  Whether the post type is eligible.
		 * @param string $post_type Post type slug.
		 */
		return (bool) apply_filters( 'prc_schema_seo_auto_redirect_post_types', $eligible, $post_type );
	}

	/**
	 * Extract the path component from a full URL.
	 *
	 * @param string $url Full URL.
	 * @return string Path component (e.g., "/my-post/").
	 */
	private function url_to_path( $url ) {
		$parsed = wp_parse_url( $url );
		return isset( $parsed['path'] ) ? $parsed['path'] : '/';
	}

	// -------------------------------------------------------------------------
	// Post Slug Change Detection
	// -------------------------------------------------------------------------

	/**
	 * Detect slug changes when a post is updated.
	 *
	 * @hook post_updated
	 *
	 * @param int      $post_id    Post ID.
	 * @param \WP_Post $post_after  Post object after the update.
	 * @param \WP_Post $post_before Post object before the update.
	 */
	public function on_post_updated( $post_id, $post_after, $post_before ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Ignore autosaves and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only act on published posts (both before and after).
		if ( 'publish' !== $post_before->post_status || 'publish' !== $post_after->post_status ) {
			return;
		}

		// Check post type eligibility.
		if ( ! $this->is_post_type_eligible( $post_after->post_type ) ) {
			return;
		}

		// Compare slugs.
		if ( $post_before->post_name === $post_after->post_name ) {
			return;
		}

		// Build the old permalink by swapping the slug in the current permalink.
		$new_permalink = get_permalink( $post_after->ID );
		if ( ! $new_permalink ) {
			return;
		}

		$old_permalink = str_replace(
			'/' . $post_after->post_name . '/',
			'/' . $post_before->post_name . '/',
			$new_permalink
		);

		$old_path = $this->url_to_path( $old_permalink );
		$new_path = $this->url_to_path( $new_permalink );

		// Don't create a redirect if paths are identical after parsing.
		if ( $old_path === $new_path ) {
			return;
		}

		$notes = sprintf(
			'Auto-redirect: slug changed from "%s" to "%s" on post #%d.',
			$post_before->post_name,
			$post_after->post_name,
			$post_id
		);

		$current_user_id = get_current_user_id();
		$author_id       = $current_user_id ? $current_user_id : 1;

		$redirect_id = srm_create_redirect(
			$old_path,
			$new_path,
			$this->get_status_code(),
			false,
			'publish',
			0,
			$notes,
			$author_id
		);

		if ( is_wp_error( $redirect_id ) ) {
			return;
		}

		// Store a short-lived transient so the editor can display a notice.
		$transient_key = $this->get_post_redirect_transient_key( $current_user_id, $post_id );
		set_transient(
			$transient_key,
			array(
				'redirect_post_id' => $redirect_id,
				'from_url'         => $old_path,
				'to_url'           => $new_path,
			),
			60
		);
	}

	/**
	 * Build the transient key for a post redirect notice.
	 *
	 * @param int $user_id User ID.
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_post_redirect_transient_key( $user_id, $post_id ) {
		return '_prc_seo_redirect_created_' . $user_id . '_' . $post_id;
	}

	// -------------------------------------------------------------------------
	// Taxonomy Term Slug Change Detection
	// -------------------------------------------------------------------------

	/**
	 * Stash the current term slug before WordPress updates it.
	 *
	 * @hook edit_terms
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function stash_term_slug_before_update( $term_id, $taxonomy ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$enabled_taxonomies = apply_filters(
			'prc_schema_seo_enabled_taxonomies_for_term_meta',
			array( 'category', 'post_tag', 'areas-of-expertise' )
		);

		if ( ! in_array( $taxonomy, $enabled_taxonomies, true ) ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$this->stashed_term_slugs[ $term_id ] = $term->slug;

		// Capture the old URL before the update changes it.
		$old_url = get_term_link( $term );
		if ( ! is_wp_error( $old_url ) ) {
			$this->stashed_term_urls[ $term_id ] = $old_url;
		}
	}

	/**
	 * Compare old and new term slugs after update and create redirect if changed.
	 *
	 * @hook edited_term
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function on_term_edited( $term_id, $tt_id, $taxonomy ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! isset( $this->stashed_term_slugs[ $term_id ] ) ) {
			return;
		}

		$old_slug = $this->stashed_term_slugs[ $term_id ];
		unset( $this->stashed_term_slugs[ $term_id ] );

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		// No change.
		if ( $old_slug === $term->slug ) {
			return;
		}

		// Build paths.
		$new_url = get_term_link( $term );
		if ( is_wp_error( $new_url ) ) {
			return;
		}

		$old_url = isset( $this->stashed_term_urls[ $term_id ] )
			? $this->stashed_term_urls[ $term_id ]
			: str_replace( '/' . $term->slug . '/', '/' . $old_slug . '/', $new_url );
		unset( $this->stashed_term_urls[ $term_id ] );

		$old_path = $this->url_to_path( $old_url );
		$new_path = $this->url_to_path( $new_url );

		if ( $old_path === $new_path ) {
			return;
		}

		$notes = sprintf(
			'Auto-redirect: %s term slug changed from "%s" to "%s" (term #%d).',
			$taxonomy,
			$old_slug,
			$term->slug,
			$term_id
		);

		$current_user_id = get_current_user_id();
		$author_id       = $current_user_id ? $current_user_id : 1;

		$redirect_id = srm_create_redirect(
			$old_path,
			$new_path,
			$this->get_status_code(),
			false,
			'publish',
			0,
			$notes,
			$author_id
		);

		if ( is_wp_error( $redirect_id ) ) {
			return;
		}

		// Store transient for the admin notice on the term edit screen.
		$transient_key = '_prc_seo_term_redirect_' . $current_user_id . '_' . $term_id;
		set_transient(
			$transient_key,
			array(
				'redirect_post_id' => $redirect_id,
				'from_url'         => $old_path,
				'to_url'           => $new_path,
			),
			60
		);
	}

	// -------------------------------------------------------------------------
	// Block Editor Notice (localize data for JS)
	// -------------------------------------------------------------------------

	/**
	 * Localize redirect notice data for the block editor JS.
	 *
	 * @hook enqueue_block_editor_assets
	 */
	public function localize_redirect_notice_data() {
		if ( ! wp_script_is( 'prc-schema-seo-block-editor', 'enqueued' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! isset( $screen->post_type ) ) {
			return;
		}

		// Determine the current post ID from the URL query parameter.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $post_id ) {
			return;
		}

		$user_id       = get_current_user_id();
		$transient_key = $this->get_post_redirect_transient_key( $user_id, $post_id );
		$redirect_data = get_transient( $transient_key );

		if ( empty( $redirect_data ) || ! is_array( $redirect_data ) ) {
			return;
		}

		// Delete the transient so the notice only shows once.
		delete_transient( $transient_key );

		wp_localize_script(
			'prc-schema-seo-block-editor',
			'PRCSchemaSEORedirect',
			array(
				'redirectId' => absint( $redirect_data['redirect_post_id'] ),
				'fromUrl'    => esc_url( $redirect_data['from_url'] ),
				'toUrl'      => esc_url( $redirect_data['to_url'] ),
				'restUrl'    => rest_url( self::REST_NAMESPACE . '/redirect/' . absint( $redirect_data['redirect_post_id'] ) ),
				'restNonce'  => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Classic Admin Notice for Term Edit Screens
	// -------------------------------------------------------------------------

	/**
	 * Render an admin notice on term edit screens when a redirect was just created.
	 *
	 * @hook admin_notices
	 */
	public function render_term_redirect_admin_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'term' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$term_id = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0;
		if ( ! $term_id ) {
			return;
		}

		$user_id       = get_current_user_id();
		$transient_key = '_prc_seo_term_redirect_' . $user_id . '_' . $term_id;
		$redirect_data = get_transient( $transient_key );

		if ( empty( $redirect_data ) || ! is_array( $redirect_data ) ) {
			return;
		}

		// Delete the transient so the notice only shows once.
		delete_transient( $transient_key );

		$redirect_post_id = absint( $redirect_data['redirect_post_id'] );
		$from_url         = esc_html( $redirect_data['from_url'] );
		$to_url           = esc_html( $redirect_data['to_url'] );
		$status_code      = absint( $this->get_status_code() );
		$nonce            = wp_create_nonce( 'prc_seo_delete_redirect_' . $redirect_post_id );
		$ajax_url         = esc_url( admin_url( 'admin-ajax.php' ) );

		$message = sprintf(
			/* translators: 1: old URL path, 2: new URL path, 3: HTTP status code */
			esc_html__( 'A %3$d redirect was created: %1$s &rarr; %2$s', 'prc-schema-seo' ),
			'<code>' . esc_html( $redirect_data['from_url'] ) . '</code>',
			'<code>' . esc_html( $redirect_data['to_url'] ) . '</code>',
			$status_code
		);

		$btn_label      = esc_html__( "Don't create redirect", 'prc-schema-seo' );
		$removing_label = wp_json_encode( __( 'Removing...', 'prc-schema-seo' ) );
		$removed_label  = wp_json_encode( __( 'Redirect removed.', 'prc-schema-seo' ) );
		$error_label    = wp_json_encode( __( 'Could not remove redirect. Try again.', 'prc-schema-seo' ) );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All variables are pre-escaped above.
		echo '<div class="notice notice-success is-dismissible prc-seo-redirect-notice" data-redirect-id="' . esc_attr( $redirect_post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
		echo '<p>' . wp_kses( $message, array( 'code' => array() ) ) . '</p>';
		echo '<p><button type="button" class="button button-link prc-seo-undo-redirect">' . esc_html( $btn_label ) . '</button></p>';
		echo '</div>';
		echo '<script>
		(function() {
			var notice = document.querySelector(".prc-seo-redirect-notice");
			if (!notice) return;
			var btn = notice.querySelector(".prc-seo-undo-redirect");
			if (!btn) return;
			btn.addEventListener("click", function() {
				btn.disabled = true;
				btn.textContent = ' . $removing_label . ';
				var xhr = new XMLHttpRequest();
				xhr.open("POST", "' . $ajax_url . '");
				xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
				xhr.onload = function() {
					if (xhr.status === 200) {
						notice.querySelector("p").textContent = ' . $removed_label . ';
						notice.classList.remove("notice-success");
						notice.classList.add("notice-info");
						if (btn.parentNode) btn.parentNode.removeChild(btn);
					} else {
						btn.textContent = ' . $error_label . ';
						btn.disabled = false;
					}
				};
				xhr.onerror = function() {
					btn.textContent = ' . $error_label . ';
					btn.disabled = false;
				};
				xhr.send("action=prc_seo_delete_redirect&redirect_id=" + notice.dataset.redirectId + "&_wpnonce=" + notice.dataset.nonce);
			});
		})();
		</script>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// -------------------------------------------------------------------------
	// REST API Endpoints
	// -------------------------------------------------------------------------

	/**
	 * Register REST routes for redirect management.
	 *
	 * @hook rest_api_init
	 */
	public function register_rest_routes() {
		// DELETE endpoint: remove a redirect (undo).
		register_rest_route(
			self::REST_NAMESPACE,
			'/redirect/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'rest_delete_redirect' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && absint( $param ) > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET endpoint: check for pending redirect notice (polled by JS after save).
		register_rest_route(
			self::REST_NAMESPACE,
			'/redirect-notice/(?P<post_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_redirect_notice' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && absint( $param ) > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Return pending redirect notice data for a post and consume the transient.
	 *
	 * Called by the block editor JS after a successful save to check if a
	 * slug-change redirect was created during that save.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_redirect_notice( $request ) {
		$post_id       = $request->get_param( 'post_id' );
		$user_id       = get_current_user_id();
		$transient_key = $this->get_post_redirect_transient_key( $user_id, $post_id );
		$redirect_data = get_transient( $transient_key );

		if ( empty( $redirect_data ) || ! is_array( $redirect_data ) ) {
			return new \WP_REST_Response( array( 'hasRedirect' => false ), 200 );
		}

		// Consume the transient so it only shows once.
		delete_transient( $transient_key );

		return new \WP_REST_Response(
			array(
				'hasRedirect' => true,
				'redirectId'  => absint( $redirect_data['redirect_post_id'] ),
				'fromUrl'     => $redirect_data['from_url'],
				'toUrl'       => $redirect_data['to_url'],
				'deleteUrl'   => rest_url( self::REST_NAMESPACE . '/redirect/' . absint( $redirect_data['redirect_post_id'] ) ),
			),
			200
		);
	}

	/**
	 * Handle REST request to delete a redirect rule.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_delete_redirect( $request ) {
		$redirect_id = $request->get_param( 'id' );

		// Verify the post exists and is a redirect_rule.
		$post = get_post( $redirect_id );
		if ( ! $post || 'redirect_rule' !== $post->post_type ) {
			return new \WP_Error(
				'invalid_redirect',
				__( 'The specified redirect does not exist.', 'prc-schema-seo' ),
				array( 'status' => 404 )
			);
		}

		$deleted = wp_delete_post( $redirect_id, true );
		if ( ! $deleted ) {
			return new \WP_Error(
				'delete_failed',
				__( 'Could not delete the redirect.', 'prc-schema-seo' ),
				array( 'status' => 500 )
			);
		}

		// Flush SRM cache so the deleted redirect stops being served.
		if ( function_exists( 'srm_flush_cache' ) ) {
			srm_flush_cache();
		}

		return new \WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	// -------------------------------------------------------------------------
	// AJAX Handler for Term Screen Undo
	// -------------------------------------------------------------------------

	/**
	 * Enqueue inline script on taxonomy listing screens (edit-tags.php) to
	 * detect quick-edit saves and show redirect notices.
	 *
	 * @hook admin_enqueue_scripts
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_term_list_redirect_script( $hook_suffix ) {
		if ( 'edit-tags.php' !== $hook_suffix ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! isset( $screen->taxonomy ) ) {
			return;
		}

		$enabled_taxonomies = apply_filters(
			'prc_schema_seo_enabled_taxonomies_for_term_meta',
			array( 'category', 'post_tag', 'areas-of-expertise' )
		);

		if ( ! in_array( $screen->taxonomy, $enabled_taxonomies, true ) ) {
			return;
		}

		$nonce    = wp_create_nonce( 'prc_seo_check_term_redirect' );
		$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );

		$removing_label = wp_json_encode( __( 'Removing...', 'prc-schema-seo' ) );
		$removed_label  = wp_json_encode( __( 'Redirect removed.', 'prc-schema-seo' ) );
		$error_label    = wp_json_encode( __( 'Could not remove redirect. Try again.', 'prc-schema-seo' ) );
		$btn_label      = wp_json_encode( __( "Don't create redirect", 'prc-schema-seo' ) );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All JS string values are wp_json_encode'd.
		wp_add_inline_script( 'inline-edit-tax', '
		(function($) {
			if (!$) return;
			$(document).ajaxComplete(function(event, xhr, settings) {
				if (!settings.data || typeof settings.data !== "string") return;
				if (settings.data.indexOf("action=inline-save-tax") === -1) return;

				var params = new URLSearchParams(settings.data);
				var termId = params.get("tax_ID");
				if (!termId) return;

				$.post("' . $ajax_url . '", {
					action: "prc_seo_check_term_redirect",
					term_id: termId,
					_wpnonce: "' . esc_js( $nonce ) . '"
				}, function(response) {
					if (!response.success || !response.data || !response.data.hasRedirect) return;

					var d = response.data;
					var wrap = document.getElementById("wpbody-content");
					if (!wrap) return;

					var notice = document.createElement("div");
					notice.className = "notice notice-success is-dismissible prc-seo-redirect-notice";
					notice.setAttribute("data-redirect-id", d.redirectId);

					var msg = document.createElement("p");
					msg.innerHTML = "A " + d.statusCode + " redirect was created: <code>" +
						d.fromUrl.replace(/</g, "&lt;") + "</code> &rarr; <code>" +
						d.toUrl.replace(/</g, "&lt;") + "</code>";
					notice.appendChild(msg);

					var btnP = document.createElement("p");
					var btn = document.createElement("button");
					btn.type = "button";
					btn.className = "button button-link";
					btn.textContent = ' . $btn_label . ';
					btnP.appendChild(btn);
					notice.appendChild(btnP);

					var firstChild = wrap.firstChild;
					wrap.insertBefore(notice, firstChild);

					if (typeof wp !== "undefined" && wp.a11y && wp.a11y.speak) {
						wp.a11y.speak(msg.textContent);
					}

					btn.addEventListener("click", function() {
						btn.disabled = true;
						btn.textContent = ' . $removing_label . ';
						$.post("' . $ajax_url . '", {
							action: "prc_seo_delete_redirect",
							redirect_id: d.redirectId,
							_wpnonce: d.deleteNonce
						}, function(resp) {
							if (resp.success) {
								msg.textContent = ' . $removed_label . ';
								notice.classList.remove("notice-success");
								notice.classList.add("notice-info");
								if (btn.parentNode) btn.parentNode.removeChild(btn);
							} else {
								btn.textContent = ' . $error_label . ';
								btn.disabled = false;
							}
						}).fail(function() {
							btn.textContent = ' . $error_label . ';
							btn.disabled = false;
						});
					});
				});
			});
		})(window.jQuery);
		' );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * AJAX handler: check if a term redirect was just created (for taxonomy listing screen).
	 *
	 * @hook wp_ajax_prc_seo_check_term_redirect
	 */
	public function ajax_check_term_redirect() {
		if ( ! check_ajax_referer( 'prc_seo_check_term_redirect', '_wpnonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'prc-schema-seo' ), 403 );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'prc-schema-seo' ), 403 );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( ! $term_id ) {
			wp_send_json_success( array( 'hasRedirect' => false ) );
		}

		$user_id       = get_current_user_id();
		$transient_key = '_prc_seo_term_redirect_' . $user_id . '_' . $term_id;
		$redirect_data = get_transient( $transient_key );

		if ( empty( $redirect_data ) || ! is_array( $redirect_data ) ) {
			wp_send_json_success( array( 'hasRedirect' => false ) );
		}

		// Consume the transient.
		delete_transient( $transient_key );

		$redirect_post_id = absint( $redirect_data['redirect_post_id'] );

		wp_send_json_success(
			array(
				'hasRedirect' => true,
				'redirectId'  => $redirect_post_id,
				'fromUrl'     => $redirect_data['from_url'],
				'toUrl'       => $redirect_data['to_url'],
				'statusCode'  => $this->get_status_code(),
				'deleteNonce' => wp_create_nonce( 'prc_seo_delete_redirect_' . $redirect_post_id ),
			)
		);
	}

	/**
	 * Handle AJAX request to delete a redirect from the term edit screen.
	 *
	 * @hook wp_ajax_prc_seo_delete_redirect
	 */
	public function ajax_delete_redirect() {
		$redirect_id = isset( $_POST['redirect_id'] ) ? absint( $_POST['redirect_id'] ) : 0;

		if ( ! $redirect_id ) {
			wp_send_json_error( __( 'Invalid redirect ID.', 'prc-schema-seo' ), 400 );
		}

		// Verify nonce.
		if ( ! check_ajax_referer( 'prc_seo_delete_redirect_' . $redirect_id, '_wpnonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'prc-schema-seo' ), 403 );
		}

		// Verify capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'prc-schema-seo' ), 403 );
		}

		// Verify the post exists and is a redirect_rule.
		$post = get_post( $redirect_id );
		if ( ! $post || 'redirect_rule' !== $post->post_type ) {
			wp_send_json_error( __( 'Redirect not found.', 'prc-schema-seo' ), 404 );
		}

		$deleted = wp_delete_post( $redirect_id, true );
		if ( ! $deleted ) {
			wp_send_json_error( __( 'Could not delete the redirect.', 'prc-schema-seo' ), 500 );
		}

		// Flush SRM cache.
		if ( function_exists( 'srm_flush_cache' ) ) {
			srm_flush_cache();
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}
}
