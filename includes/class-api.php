<?php
/**
 * API/AJAX functionality
 *
 * @package Elementor_Bulk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * API class
 */
class EBI_API {

	/**
	 * Instance
	 *
	 * @var EBI_API
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return EBI_API
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'wp_ajax_ebi_get_templates', array( $this, 'get_templates' ) );
		add_action( 'wp_ajax_ebi_import_pages', array( $this, 'import_pages' ) );
		add_action( 'wp_ajax_ebi_import_single_page', array( $this, 'import_single_page' ) );
		add_action( 'wp_ajax_ebi_import_header_footer', array( $this, 'import_header_footer' ) );
	}

	/**
	 * Get templates from installed template kits
	 */
	public function get_templates() {
		check_ajax_referer( 'ebi-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'elementor-bulk-importer' ) ) );
		}

		$template_type = isset( $_POST['template_type'] ) ? sanitize_text_field( $_POST['template_type'] ) : 'all';
		$templates     = EBI_Importer::get_instance()->get_available_templates( $template_type );

		wp_send_json_success( array( 'templates' => $templates ) );
	}

	/**
	 * Import pages
	 */
	public function import_pages() {
		check_ajax_referer( 'ebi-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'elementor-bulk-importer' ) ) );
		}

		$pages = isset( $_POST['pages'] ) ? $_POST['pages'] : array();

		if ( empty( $pages ) || ! is_array( $pages ) ) {
			wp_send_json_error( array( 'message' => __( 'Please add at least one page.', 'elementor-bulk-importer' ) ) );
		}

		$results = array();
		foreach ( $pages as $page_data ) {
			$title    = isset( $page_data['title'] ) ? sanitize_text_field( $page_data['title'] ) : '';
			$template = isset( $page_data['template'] ) ? sanitize_text_field( $page_data['template'] ) : '';

			if ( empty( $title ) || empty( $template ) ) {
				continue;
			}

			$options = array(
				'slug'          => isset( $page_data['slug'] ) ? sanitize_title( $page_data['slug'] ) : '',
				'parent'        => isset( $page_data['parent'] ) ? intval( $page_data['parent'] ) : 0,
				'template_type' => isset( $page_data['template_type'] ) ? sanitize_text_field( $page_data['template_type'] ) : 'default',
				'excerpt'       => isset( $page_data['excerpt'] ) ? sanitize_textarea_field( $page_data['excerpt'] ) : '',
				'comments'      => isset( $page_data['comments'] ) ? (bool) $page_data['comments'] : false,
				'author'        => isset( $page_data['author'] ) ? intval( $page_data['author'] ) : get_current_user_id(),
				'status'        => isset( $page_data['status'] ) ? sanitize_text_field( $page_data['status'] ) : 'publish',
			);

			if ( isset( $page_data['translation'] ) && is_array( $page_data['translation'] ) && ! empty( $page_data['translation']['enabled'] ) ) {
				$options['translation'] = array(
					'enabled'     => true,
					'api_id'      => isset( $page_data['translation']['api_id'] ) ? sanitize_text_field( $page_data['translation']['api_id'] ) : '',
					'target_lang' => isset( $page_data['translation']['target_lang'] ) ? sanitize_text_field( $page_data['translation']['target_lang'] ) : 'tr',
				);
			}

			$result = EBI_Importer::get_instance()->import_template_to_page( $title, $template, $options );
			$results[] = array(
				'title'  => $title,
				'result' => $result,
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Import single page
	 */
	public function import_single_page() {
		check_ajax_referer( 'ebi-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'elementor-bulk-importer' ) ) );
		}

		$title    = isset( $_POST['page_title'] ) ? sanitize_text_field( $_POST['page_title'] ) : '';
		$template = isset( $_POST['page_template'] ) ? sanitize_text_field( $_POST['page_template'] ) : '';

		if ( empty( $title ) || empty( $template ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all fields.', 'elementor-bulk-importer' ) ) );
		}

		$options = array(
			'slug'          => isset( $_POST['page_slug'] ) ? sanitize_title( $_POST['page_slug'] ) : '',
			'parent'        => isset( $_POST['page_parent'] ) ? intval( $_POST['page_parent'] ) : 0,
			'template_type' => isset( $_POST['page_template_type'] ) ? sanitize_text_field( $_POST['page_template_type'] ) : 'default',
			'excerpt'       => isset( $_POST['page_excerpt'] ) ? sanitize_textarea_field( $_POST['page_excerpt'] ) : '',
			'comments'      => isset( $_POST['page_comments'] ) && $_POST['page_comments'] === '1',
			'author'        => isset( $_POST['page_author'] ) ? intval( $_POST['page_author'] ) : get_current_user_id(),
			'status'         => isset( $_POST['page_status'] ) ? sanitize_text_field( $_POST['page_status'] ) : 'publish',
		);

		// Translation settings - only for PRO version
		$translation_enabled = EBI_PRO_VERSION && isset( $_POST['translation'] ) && isset( $_POST['translation']['enable_translation'] ) && $_POST['translation']['enable_translation'];
		if ( $translation_enabled ) {
			$options['translation'] = array(
				'enabled'     => true,
				'api_id'      => isset( $_POST['translation']['translation_api'] ) ? sanitize_text_field( $_POST['translation']['translation_api'] ) : '',
				'target_lang' => isset( $_POST['translation']['target_lang'] ) ? sanitize_text_field( $_POST['translation']['target_lang'] ) : 'tr',
			);
		} elseif ( ! EBI_PRO_VERSION && isset( $_POST['translation'] ) && isset( $_POST['translation']['enable_translation'] ) && $_POST['translation']['enable_translation'] ) {
			// User tried to use translation without PRO
			wp_send_json_error( array( 'message' => __( 'Translation is a PRO feature. Please upgrade to use translation. Contact: https://t.me/bayramsavluk', 'elementor-bulk-importer' ) ) );
		}

		$result = EBI_Importer::get_instance()->import_template_to_page( $title, $template, $options );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'result' => $result ) );
	}


	/**
	 * Import header/footer
	 */
	public function import_header_footer() {
		check_ajax_referer( 'ebi-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'elementor-bulk-importer' ) ) );
		}

		$title    = isset( $_POST['hf_title'] ) ? sanitize_text_field( $_POST['hf_title'] ) : '';
		$type     = isset( $_POST['hf_type'] ) ? sanitize_text_field( $_POST['hf_type'] ) : '';
		$template = isset( $_POST['hf_template'] ) ? sanitize_text_field( $_POST['hf_template'] ) : '';

		if ( empty( $title ) || empty( $type ) || empty( $template ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all fields.', 'elementor-bulk-importer' ) ) );
		}

		// Get target rules - use our own class first, fallback to header-footer-elementor
		$target_rules_class = null;
		if ( class_exists( '\EBI\Lib\EBI_Target_Rules_Fields' ) ) {
			$target_rules_class = '\EBI\Lib\EBI_Target_Rules_Fields';
		} elseif ( class_exists( '\HFE\Lib\Astra_Target_Rules_Fields' ) ) {
			$target_rules_class = '\HFE\Lib\Astra_Target_Rules_Fields';
		}

		if ( $target_rules_class ) {
			$target_locations = $target_rules_class::get_format_rule_value( $_POST, 'bsf-target-rules-location' );
			$target_exclusion = $target_rules_class::get_format_rule_value( $_POST, 'bsf-target-rules-exclusion' );
			$target_users     = [];

			if ( isset( $_POST['bsf-target-rules-users'] ) ) {
				$target_users = array_map( 'sanitize_text_field', wp_unslash( $_POST['bsf-target-rules-users'] ) );
			}
		} else {
			// Fallback
			$target_locations = array(
				'rule' => array( 'basic-global' ),
			);
			$target_exclusion = array();
			$target_users = array();
		}

		$options = array(
			'target_locations' => $target_locations,
			'target_exclusion' => $target_exclusion,
			'target_users'     => $target_users,
			'enable_canvas'    => isset( $_POST['hf_enable_canvas'] ) && $_POST['hf_enable_canvas'] === '1',
		);

		// Translation settings - only for PRO version
		$translation_enabled = EBI_PRO_VERSION && isset( $_POST['translation'] ) && isset( $_POST['translation']['enable_translation'] ) && $_POST['translation']['enable_translation'];
		if ( $translation_enabled ) {
			$options['translation'] = array(
				'enabled'     => true,
				'api_id'      => isset( $_POST['translation']['translation_api'] ) ? sanitize_text_field( $_POST['translation']['translation_api'] ) : '',
				'target_lang' => isset( $_POST['translation']['target_lang'] ) ? sanitize_text_field( $_POST['translation']['target_lang'] ) : 'tr',
			);
		} elseif ( ! EBI_PRO_VERSION && isset( $_POST['translation'] ) && isset( $_POST['translation']['enable_translation'] ) && $_POST['translation']['enable_translation'] ) {
			// User tried to use translation without PRO
			wp_send_json_error( array( 'message' => __( 'Translation is a PRO feature. Please upgrade to use translation. Contact: https://t.me/bayramsavluk', 'elementor-bulk-importer' ) ) );
		}

		$result = EBI_Importer::get_instance()->import_template_as_section( $template, $type, $title, $options );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'result' => $result ) );
	}
}

