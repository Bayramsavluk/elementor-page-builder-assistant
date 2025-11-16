<?php
/**
 * Importer functionality
 *
 * @package Elementor_Bulk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Importer class
 */
class EBI_Importer {

	/**
	 * Instance
	 *
	 * @var EBI_Importer
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return EBI_Importer
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
		// Constructor
	}

	/**
	 * Get available templates from installed template kits and Elementor library
	 *
	 * @param string $filter_type Filter by template type (page, section-header, section-footer, single-404, all).
	 * @return array
	 */
	public function get_available_templates( $filter_type = 'all' ) {
		$templates = array();

		// 1. Get templates from template kits
		if ( class_exists( '\Template_Kit_Import\Backend\Template_Kits' ) ) {
		$template_kits = \Template_Kit_Import\Backend\Template_Kits::get_instance()->get_installed_template_kits();

		foreach ( $template_kits as $kit ) {
			$kit_data = \Template_Kit_Import\Backend\Template_Kits::get_instance()->get_installed_template_kit( $kit['id'] );

			if ( is_wp_error( $kit_data ) || empty( $kit_data['templates'] ) ) {
				continue;
			}

			foreach ( $kit_data['templates'] as $template ) {
				$template_type = isset( $template['metadata']['template_type'] ) ? $template['metadata']['template_type'] : '';

				// Filter templates by type
				if ( 'all' !== $filter_type ) {
					if ( 'page' === $filter_type && 'single-page' !== $template_type ) {
						continue;
					}
					if ( 'header' === $filter_type && 'section-header' !== $template_type ) {
						continue;
					}
					if ( 'footer' === $filter_type && 'section-footer' !== $template_type ) {
						continue;
					}
					if ( '404' === $filter_type && 'single-404' !== $template_type ) {
						continue;
					}
				}

				$templates[] = array(
					'id'          => $template['id'],
					'kit_id'      => $kit['id'],
					'name'        => $template['name'],
					'type'        => $template_type,
					'full_id'     => $kit['id'] . ':' . $template['id'],
					'kit_name'    => $kit_data['title'],
						'source'      => 'template_kit',
					);
				}
			}
		}

		// 2. Get templates from Elementor library (elementor_library post type)
		if ( post_type_exists( 'elementor_library' ) ) {
			// Map filter types to Elementor template types
			$elementor_type_map = array(
				'page' => 'page',
				'header' => 'section',
				'footer' => 'section',
				'404' => 'single',
			);

			$meta_query = array();
			
			// Filter by Elementor template type if needed
			if ( 'all' !== $filter_type && isset( $elementor_type_map[ $filter_type ] ) ) {
				$meta_query[] = array(
					'key'   => '_elementor_template_type',
					'value' => $elementor_type_map[ $filter_type ],
				);
			}

			$args = array(
				'post_type'      => 'elementor_library',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => $meta_query,
				'orderby'        => 'title',
				'order'          => 'ASC',
			);

			$library_posts = get_posts( $args );

			foreach ( $library_posts as $post ) {
				$elementor_template_type = get_post_meta( $post->ID, '_elementor_template_type', true );
				
				// Map Elementor types to our types
				$our_template_type = '';
				if ( 'page' === $elementor_template_type ) {
					$our_template_type = 'single-page';
                } elseif ( 'section' === $elementor_template_type ) {
                    // Check if it's header or footer by meta
                    $section_type = get_post_meta( $post->ID, '_elementor_page_settings', true );
                    $section_type = isset( $section_type['ehf_template_type'] ) ? $section_type['ehf_template_type'] : '';

                    if ( 'type_header' === $section_type ) {
                        $our_template_type = 'section-header';
                    } elseif ( 'type_footer' === $section_type || 'type_before_footer' === $section_type ) {
                        $our_template_type = 'section-footer';
                    } else {
                        // Generic section - mark as 'section-other' so it can be listed distinctly
                        $our_template_type = 'section-other';
                    }
				} elseif ( 'single' === $elementor_template_type ) {
					$our_template_type = 'single-404';
				} else {
					continue; // Skip unknown types
				}

				// Filter by our type
				if ( 'all' !== $filter_type ) {
					if ( 'page' === $filter_type && 'single-page' !== $our_template_type ) {
						continue;
					}
					if ( 'header' === $filter_type && 'section-header' !== $our_template_type ) {
						continue;
					}
					if ( 'footer' === $filter_type && 'section-footer' !== $our_template_type ) {
						continue;
					}
					if ( '404' === $filter_type && 'single-404' !== $our_template_type ) {
						continue;
					}
				}

				$templates[] = array(
					'id'          => $post->ID,
					'kit_id'      => 0, // Library templates don't have kit ID
					'name'        => $post->post_title,
					'type'        => $our_template_type,
					'full_id'     => 'library:' . $post->ID, // Special format for library templates
					'kit_name'    => __( 'Elementor Library', 'elementor-bulk-importer' ),
					'source'      => 'elementor_library',
				);
			}
		}

		return $templates;
	}

	/**
	 * Import template to a page
	 *
	 * @param string $page_title Page title.
	 * @param string $template Template ID (format: kit_id:template_id).
	 * @param array  $options Additional options (slug, parent, template_type, excerpt, comments, author, status).
	 * @return array|WP_Error
	 */
	public function import_template_to_page( $page_title, $template, $options = array() ) {
		if ( empty( $page_title ) || empty( $template ) ) {
			return new \WP_Error( 'invalid_params', __( 'Sayfa adı ve şablon gerekli.', 'elementor-bulk-importer' ) );
		}

        // Parse template ID (supports "library:POST_ID" or "KIT_ID:TEMPLATE_ID")
        $parts = explode( ':', $template );
        if ( count( $parts ) !== 2 ) {
            return new \WP_Error( 'invalid_template', __( 'Geçersiz şablon formatı.', 'elementor-bulk-importer' ) );
        }

        // Handle Elementor Library templates directly
        if ( 'library' === $parts[0] ) {
            $library_post_id = intval( $parts[1] );
            return $this->import_library_template_to_page( $page_title, $library_post_id, $options );
        }

        $kit_id     = intval( $parts[0] );
        $template_id = intval( $parts[1] );

		// Import template using template-kit-import plugin
		if ( ! class_exists( '\Template_Kit_Import\Backend\Template_Kits' ) ) {
			return new \WP_Error( 'plugin_missing', __( 'Template Kit Import eklentisi bulunamadı.', 'elementor-bulk-importer' ) );
		}

		$imported_template = \Template_Kit_Import\Backend\Template_Kits::get_instance()->import_single_template( $kit_id, $template_id, false );

		if ( is_wp_error( $imported_template ) ) {
			return $imported_template;
		}

		$template_post_id = $imported_template['imported_template_id'];

		// Prepare page data
		$page_data = array(
			'post_title'  => $page_title,
			'post_type'   => 'page',
			'post_status' => isset( $options['status'] ) ? $options['status'] : 'publish',
			'post_author' => isset( $options['author'] ) ? $options['author'] : get_current_user_id(),
			'post_excerpt' => isset( $options['excerpt'] ) ? $options['excerpt'] : '',
		);

		// Add slug if provided, otherwise WordPress will auto-generate it
		// WordPress'in sanitize_title() fonksiyonu Türkçe karakterleri doğru çevirir
		if ( ! empty( $options['slug'] ) ) {
			$page_data['post_name'] = sanitize_title( $options['slug'] );
		} else {
			// WordPress otomatik olarak title'dan slug oluşturacak
			// Ancak biz manuel olarak da oluşturabiliriz
			$page_data['post_name'] = sanitize_title( $page_title );
		}

		// Add parent if provided
		if ( ! empty( $options['parent'] ) && $options['parent'] > 0 ) {
			$page_data['post_parent'] = intval( $options['parent'] );
		}

		// Create a new page
		$page_id = wp_insert_post( $page_data );

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		// Set Elementor template to page
		update_post_meta( $page_id, '_elementor_template_type', 'page' );
		
		// Get Elementor content from template
		$elementor_content = get_post_meta( $template_post_id, '_elementor_data', true );
		
		if ( ! empty( $elementor_content ) ) {
			// Translate Elementor content if translation is enabled
			if ( isset( $options['translation'] ) && $options['translation']['enabled'] ) {
				$translation_api = isset( $options['translation']['api_id'] ) ? $options['translation']['api_id'] : 'mymemory';
				$target_lang = isset( $options['translation']['target_lang'] ) ? $options['translation']['target_lang'] : 'tr';
				
				$elementor_data = json_decode( $elementor_content, true );
				if ( $elementor_data && is_array( $elementor_data ) ) {
					$translator = EBI_Translator::get_instance();
					$translated_data = array();
					
					foreach ( $elementor_data as $element ) {
						$translated_element = $translator->translate_elementor_content( $element, $target_lang, $translation_api );
						$translated_data[] = $translated_element;
					}
					
					// Use JSON_UNESCAPED_UNICODE to preserve Turkish characters
					$elementor_content = wp_json_encode( $translated_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				}
			}
			
			// Copy Elementor data to page
			update_post_meta( $page_id, '_elementor_data', $elementor_content );
			update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $page_id, '_elementor_version', \Elementor\DB::DB_VERSION );
			update_post_meta( $page_id, '_elementor_pro_version', defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '' );

			// Copy page settings from template
			$page_settings = get_post_meta( $template_post_id, '_elementor_page_settings', true );
			if ( ! empty( $page_settings ) ) {
				update_post_meta( $page_id, '_elementor_page_settings', $page_settings );
			}

			// Copy CSS from template
			$elementor_css = get_post_meta( $template_post_id, '_elementor_css', true );
			if ( ! empty( $elementor_css ) ) {
				update_post_meta( $page_id, '_elementor_css', $elementor_css );
			}
		}

		// Get template name for reference
		$template_data = \Template_Kit_Import\Backend\Template_Kits::get_instance()->get_installed_template_kit( $kit_id );
		$template_name = '';
		if ( ! is_wp_error( $template_data ) && ! empty( $template_data['templates'] ) ) {
			foreach ( $template_data['templates'] as $tmpl ) {
				if ( isset( $tmpl['id'] ) && $tmpl['id'] == $template_id ) {
					$template_name = isset( $tmpl['name'] ) ? $tmpl['name'] : '';
					break;
				}
			}
		}

		// Set page template type
		if ( ! empty( $options['template_type'] ) && 'default' !== $options['template_type'] ) {
			if ( 'elementor_canvas' === $options['template_type'] ) {
				update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' );
			} elseif ( 'elementor_header_footer' === $options['template_type'] ) {
				update_post_meta( $page_id, '_wp_page_template', 'elementor_header_footer' );
			} else {
				// Theme template
				update_post_meta( $page_id, '_wp_page_template', $options['template_type'] );
			}
		}

		// Set comments
		if ( isset( $options['comments'] ) && $options['comments'] ) {
			wp_set_comment_status( $page_id, 'open' );
		} else {
			wp_set_comment_status( $page_id, 'closed' );
		}

		// Mark as imported by our plugin
		update_post_meta( $page_id, 'ebi_imported', '1' );
		update_post_meta( $page_id, 'ebi_template_id', $template_post_id );
		update_post_meta( $page_id, 'ebi_template_name', $template_name );

		// Regenerate CSS
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return array(
			'page_id'  => $page_id,
			'edit_url' => admin_url( 'post.php?post=' . $page_id . '&action=edit' ),
		);
	}

	/**
	 * Import Elementor library template to a page
	 *
	 * @param string $page_title Page title.
	 * @param int    $template_id Elementor library template post ID.
	 * @param array  $options Additional options.
	 * @return array|WP_Error
	 */
	private function import_library_template_to_page( $page_title, $template_id, $options = array() ) {
		// Get the library template post
		$template_post = get_post( $template_id );
		if ( ! $template_post || 'elementor_library' !== $template_post->post_type ) {
			return new \WP_Error( 'invalid_template', __( 'Geçersiz Elementor şablonu.', 'elementor-bulk-importer' ) );
		}

		// Prepare page data
		$page_data = array(
			'post_title'  => $page_title,
			'post_type'   => 'page',
			'post_status' => isset( $options['status'] ) ? $options['status'] : 'publish',
			'post_author' => isset( $options['author'] ) ? $options['author'] : get_current_user_id(),
			'post_excerpt' => isset( $options['excerpt'] ) ? $options['excerpt'] : '',
		);

		// Add slug
		if ( ! empty( $options['slug'] ) ) {
			$page_data['post_name'] = sanitize_title( $options['slug'] );
		} else {
			$page_data['post_name'] = sanitize_title( $page_title );
		}

		// Add parent if provided
		if ( ! empty( $options['parent'] ) && $options['parent'] > 0 ) {
			$page_data['post_parent'] = intval( $options['parent'] );
		}

		// Create page
		$page_id = wp_insert_post( $page_data );

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		// Set Elementor template type
		update_post_meta( $page_id, '_elementor_template_type', 'page' );

		// Copy Elementor data from library template
		$elementor_content = get_post_meta( $template_id, '_elementor_data', true );

		if ( ! empty( $elementor_content ) ) {
			// Translate if needed
			if ( isset( $options['translation'] ) && $options['translation']['enabled'] ) {
				$translation_api = isset( $options['translation']['api_id'] ) ? $options['translation']['api_id'] : 'mymemory';
				$target_lang = isset( $options['translation']['target_lang'] ) ? $options['translation']['target_lang'] : 'tr';

				$elementor_data = json_decode( $elementor_content, true );
				if ( $elementor_data && is_array( $elementor_data ) ) {
					$translator = EBI_Translator::get_instance();
					$translated_data = array();

				foreach ( $elementor_data as $element ) {
					$translated_element = $translator->translate_elementor_content( $element, $target_lang, $translation_api );
					$translated_data[] = $translated_element;
				}

				// Use JSON_UNESCAPED_UNICODE to preserve Turkish characters
				$elementor_content = wp_json_encode( $translated_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
			}

			// Copy Elementor data
			update_post_meta( $page_id, '_elementor_data', $elementor_content );
			update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $page_id, '_elementor_version', \Elementor\DB::DB_VERSION );
			update_post_meta( $page_id, '_elementor_pro_version', defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '' );

			// Copy page settings
			$page_settings = get_post_meta( $template_id, '_elementor_page_settings', true );
			if ( ! empty( $page_settings ) ) {
				update_post_meta( $page_id, '_elementor_page_settings', $page_settings );
			}
		}

		// Set page template type
		if ( ! empty( $options['template_type'] ) && 'default' !== $options['template_type'] ) {
			if ( 'elementor_canvas' === $options['template_type'] ) {
				update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' );
			} elseif ( 'elementor_header_footer' === $options['template_type'] ) {
				update_post_meta( $page_id, '_wp_page_template', 'elementor_header_footer' );
			} else {
				update_post_meta( $page_id, '_wp_page_template', $options['template_type'] );
			}
		}

		// Set comments
		if ( isset( $options['comments'] ) && $options['comments'] ) {
			wp_set_comment_status( $page_id, 'open' );
		} else {
			wp_set_comment_status( $page_id, 'closed' );
		}

		// Mark as imported
		update_post_meta( $page_id, 'ebi_imported', '1' );
		update_post_meta( $page_id, 'ebi_template_id', $template_id );
		update_post_meta( $page_id, 'ebi_template_name', $template_post->post_title );

		// Regenerate CSS
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return array(
			'page_id'  => $page_id,
			'edit_url' => admin_url( 'post.php?post=' . $page_id . '&action=edit' ),
		);
	}

	/**
	 * Import template as section (header/footer/404)
	 *
	 * @param string $template Template ID (format: kit_id:template_id).
	 * @param string $section_type Section type (header, footer, 404).
	 * @param string $title Section title.
	 * @param array  $options Display options (display_on, exclude_on, user_roles, enable_canvas).
	 * @return array|WP_Error
	 */
	public function import_template_as_section( $template, $section_type = 'header', $title = '', $options = array() ) {
		if ( empty( $template ) ) {
			return new \WP_Error( 'invalid_params', __( 'Şablon gerekli.', 'elementor-bulk-importer' ) );
		}

		// Parse template ID - can be "kit_id:template_id" or "library:post_id"
		$parts = explode( ':', $template );
		if ( count( $parts ) !== 2 ) {
			return new \WP_Error( 'invalid_template', __( 'Geçersiz şablon formatı.', 'elementor-bulk-importer' ) );
		}

		$source = $parts[0];
		$template_id = intval( $parts[1] );

		// Handle library templates differently
		if ( 'library' === $source ) {
			return $this->import_library_template_as_section( $template_id, $section_type, $title, $options );
		}

		$kit_id = intval( $source );

		// Import template using template-kit-import plugin
		if ( ! class_exists( '\Template_Kit_Import\Backend\Template_Kits' ) ) {
			return new \WP_Error( 'plugin_missing', __( 'Template Kit Import eklentisi bulunamadı.', 'elementor-bulk-importer' ) );
		}

		$imported_template = \Template_Kit_Import\Backend\Template_Kits::get_instance()->import_single_template( $kit_id, $template_id, false );

		if ( is_wp_error( $imported_template ) ) {
			return $imported_template;
		}

		$section_post_id = $imported_template['imported_template_id'];
		
		// Translate Elementor content if translation is enabled (before post type conversion)
		if ( isset( $options['translation'] ) && $options['translation']['enabled'] ) {
			$translation_api = isset( $options['translation']['api_id'] ) ? $options['translation']['api_id'] : 'mymemory';
			$target_lang = isset( $options['translation']['target_lang'] ) ? $options['translation']['target_lang'] : 'tr';
			
			$elementor_content = get_post_meta( $section_post_id, '_elementor_data', true );
			if ( ! empty( $elementor_content ) ) {
				$elementor_data = is_string( $elementor_content ) ? json_decode( $elementor_content, true ) : $elementor_content;
				if ( $elementor_data && is_array( $elementor_data ) ) {
					$translator = EBI_Translator::get_instance();
					$translated_data = array();
					
					foreach ( $elementor_data as $element ) {
						$translated_element = $translator->translate_elementor_content( $element, $target_lang, $translation_api );
						$translated_data[] = $translated_element;
					}
					
					// Use JSON_UNESCAPED_UNICODE to preserve Turkish characters
					$translated_content = wp_json_encode( $translated_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
					update_post_meta( $section_post_id, '_elementor_data', $translated_content );
				}
			}
		}

		// Use provided title or default
		$section_names = array(
			'header'       => __( 'Header', 'elementor-bulk-importer' ),
			'footer'       => __( 'Footer', 'elementor-bulk-importer' ),
			'before_footer' => __( 'Before Footer', 'elementor-bulk-importer' ),
			'404'          => __( '404 Page', 'elementor-bulk-importer' ),
		);

		$section_title = ! empty( $title ) ? $title : ( isset( $section_names[ $section_type ] ) ? $section_names[ $section_type ] : ucfirst( $section_type ) );

		// Get template name
		$template_data = \Template_Kit_Import\Backend\Template_Kits::get_instance()->get_installed_template_kit( $kit_id );
		$template_name = '';
		if ( ! is_wp_error( $template_data ) && ! empty( $template_data['templates'] ) ) {
			foreach ( $template_data['templates'] as $tmpl ) {
				if ( isset( $tmpl['id'] ) && $tmpl['id'] == $template_id ) {
					$template_name = isset( $tmpl['name'] ) ? $tmpl['name'] : '';
					break;
				}
			}
		}

		// Check if elementor-hf post type exists (header-footer-elementor plugin)
		$post_type = post_type_exists( 'elementor-hf' ) ? 'elementor-hf' : 'elementor_library';

		// If post type is elementor_library, we need to convert it
		if ( 'elementor-hf' === $post_type && get_post_type( $section_post_id ) !== 'elementor-hf' ) {
			// Create new post in elementor-hf
			$new_post_id = wp_insert_post(
				array(
					'post_title'  => $section_title,
					'post_type'   => 'elementor-hf',
					'post_status' => 'publish',
				)
			);

			if ( ! is_wp_error( $new_post_id ) ) {
				// Copy Elementor data
				$elementor_data = get_post_meta( $section_post_id, '_elementor_data', true );
				if ( ! empty( $elementor_data ) ) {
					// Translate Elementor content if translation is enabled
					if ( isset( $options['translation'] ) && $options['translation']['enabled'] ) {
						$translation_api = isset( $options['translation']['api_id'] ) ? $options['translation']['api_id'] : 'mymemory';
						$target_lang = isset( $options['translation']['target_lang'] ) ? $options['translation']['target_lang'] : 'tr';
						
						$elementor_data_array = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
						if ( $elementor_data_array && is_array( $elementor_data_array ) ) {
							$translator = EBI_Translator::get_instance();
							$translated_data = array();
							
							foreach ( $elementor_data_array as $element ) {
								$translated_element = $translator->translate_elementor_content( $element, $target_lang, $translation_api );
								$translated_data[] = $translated_element;
							}
							
							// Use JSON_UNESCAPED_UNICODE to preserve Turkish characters
							$elementor_data = wp_json_encode( $translated_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
						}
					}
					
					update_post_meta( $new_post_id, '_elementor_data', $elementor_data );
					update_post_meta( $new_post_id, '_elementor_edit_mode', 'builder' );
					update_post_meta( $new_post_id, '_elementor_version', \Elementor\DB::DB_VERSION );
					update_post_meta( $new_post_id, '_elementor_pro_version', defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '' );

					$page_settings = get_post_meta( $section_post_id, '_elementor_page_settings', true );
					if ( ! empty( $page_settings ) ) {
						update_post_meta( $new_post_id, '_elementor_page_settings', $page_settings );
					}

					$elementor_css = get_post_meta( $section_post_id, '_elementor_css', true );
					if ( ! empty( $elementor_css ) ) {
						update_post_meta( $new_post_id, '_elementor_css', $elementor_css );
					}
				}

				// Delete old post
				wp_delete_post( $section_post_id, true );
				$section_post_id = $new_post_id;
			}
		} else {
			// Update existing post
			wp_update_post(
				array(
					'ID'         => $section_post_id,
					'post_title' => $section_title,
				)
			);
		}

		// Set template type meta
		$type_map = array(
			'header'       => 'type_header',
			'footer'       => 'type_footer',
			'before_footer' => 'type_before_footer',
			'404'          => 'custom', // 404 is custom type
		);
		$ehf_template_type = isset( $type_map[ $section_type ] ) ? $type_map[ $section_type ] : 'custom';
		update_post_meta( $section_post_id, 'ehf_template_type', $ehf_template_type );
		update_post_meta( $section_post_id, 'ebi_template_type', $section_type );

		// Save target rules (from header-footer-elementor format)
		$target_locations = isset( $options['target_locations'] ) ? $options['target_locations'] : array();
		$target_exclusion = isset( $options['target_exclusion'] ) ? $options['target_exclusion'] : array();
		$target_users = isset( $options['target_users'] ) ? $options['target_users'] : array();
		$enable_canvas = isset( $options['enable_canvas'] ) && $options['enable_canvas'] ? '1' : '';

		// Default: Entire Website for header/footer
		if ( empty( $target_locations ) ) {
			$target_locations = array(
				'rule' => array( 'basic-global' ),
			);
		}

		update_post_meta( $section_post_id, 'ehf_target_include_locations', $target_locations );

		if ( ! empty( $target_exclusion ) ) {
			update_post_meta( $section_post_id, 'ehf_target_exclude_locations', $target_exclusion );
		}

		if ( ! empty( $target_users ) ) {
			update_post_meta( $section_post_id, 'ehf_target_user_roles', $target_users );
		}

		if ( ! empty( $enable_canvas ) ) {
			update_post_meta( $section_post_id, 'display-on-canvas-template', $enable_canvas );
		}

		// Mark as imported
		update_post_meta( $section_post_id, 'ebi_imported', '1' );
		update_post_meta( $section_post_id, 'ebi_template_name', $template_name );

		// If Elementor Pro is active, set conditions for header/footer
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) && class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			$conditions = array();

			if ( 'header' === $section_type ) {
				$conditions = array(
					array(
						'type'   => 'general',
						'name'   => 'entire_site',
						'sub'    => '',
						'sub_id' => '',
					),
				);
			} elseif ( 'footer' === $section_type || 'before_footer' === $section_type ) {
				$conditions = array(
					array(
						'type'   => 'general',
						'name'   => 'entire_site',
						'sub'    => '',
						'sub_id' => '',
					),
				);
			} elseif ( '404' === $section_type ) {
				$conditions = array(
					array(
						'type'   => 'general',
						'name'   => '404',
						'sub'    => '',
						'sub_id' => '',
					),
				);
			}

			if ( ! empty( $conditions ) ) {
				update_post_meta( $section_post_id, '_elementor_conditions', $conditions );
			}
		}

		// Regenerate CSS
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return array(
			'section_id' => $section_post_id,
			'edit_url'   => admin_url( 'post.php?post=' . $section_post_id . '&action=edit' ),
		);
	}

	/**
	 * Import Elementor library template as section
	 *
	 * @param int    $template_id Elementor library template post ID.
	 * @param string $section_type Section type.
	 * @param string $title Section title.
	 * @param array  $options Additional options.
	 * @return array|WP_Error
	 */
	private function import_library_template_as_section( $template_id, $section_type, $title, $options = array() ) {
		// Get the library template post
		$template_post = get_post( $template_id );
		if ( ! $template_post || 'elementor_library' !== $template_post->post_type ) {
			return new \WP_Error( 'invalid_template', __( 'Geçersiz Elementor şablonu.', 'elementor-bulk-importer' ) );
		}

		// Use provided title or default
		$section_names = array(
			'header'       => __( 'Header', 'elementor-bulk-importer' ),
			'footer'       => __( 'Footer', 'elementor-bulk-importer' ),
			'before_footer' => __( 'Before Footer', 'elementor-bulk-importer' ),
			'404'          => __( '404 Page', 'elementor-bulk-importer' ),
		);

		$section_title = ! empty( $title ) ? $title : ( isset( $section_names[ $section_type ] ) ? $section_names[ $section_type ] : ucfirst( $section_type ) );

		// Check if elementor-hf post type exists
		$post_type = post_type_exists( 'elementor-hf' ) ? 'elementor-hf' : 'elementor_library';

		// Create new post
		$section_post_id = wp_insert_post(
			array(
				'post_title'  => $section_title,
				'post_type'   => $post_type,
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $section_post_id ) ) {
			return $section_post_id;
		}

		// Get Elementor content from library template
		$elementor_content = get_post_meta( $template_id, '_elementor_data', true );

		if ( ! empty( $elementor_content ) ) {
			// Translate if needed
			if ( isset( $options['translation'] ) && $options['translation']['enabled'] ) {
				$translation_api = isset( $options['translation']['api_id'] ) ? $options['translation']['api_id'] : 'mymemory';
				$target_lang = isset( $options['translation']['target_lang'] ) ? $options['translation']['target_lang'] : 'tr';

				$elementor_data = json_decode( $elementor_content, true );
				if ( $elementor_data && is_array( $elementor_data ) ) {
					$translator = EBI_Translator::get_instance();
					$translated_data = array();

				foreach ( $elementor_data as $element ) {
					$translated_element = $translator->translate_elementor_content( $element, $target_lang, $translation_api );
					$translated_data[] = $translated_element;
				}

				// Use JSON_UNESCAPED_UNICODE to preserve Turkish characters
				$elementor_content = wp_json_encode( $translated_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
			}

			// Copy Elementor data
			update_post_meta( $section_post_id, '_elementor_data', $elementor_content );
			update_post_meta( $section_post_id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $section_post_id, '_elementor_version', \Elementor\DB::DB_VERSION );
			update_post_meta( $section_post_id, '_elementor_pro_version', defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '' );

			// Copy page settings
			$page_settings = get_post_meta( $template_id, '_elementor_page_settings', true );
			if ( ! empty( $page_settings ) ) {
				update_post_meta( $section_post_id, '_elementor_page_settings', $page_settings );
			}
		}

		// Set template type meta
		$type_map = array(
			'header'       => 'type_header',
			'footer'       => 'type_footer',
			'before_footer' => 'type_before_footer',
			'404'          => 'custom',
		);
		$ehf_template_type = isset( $type_map[ $section_type ] ) ? $type_map[ $section_type ] : 'custom';
		update_post_meta( $section_post_id, 'ehf_template_type', $ehf_template_type );
		update_post_meta( $section_post_id, 'ebi_template_type', $section_type );

		// Save target rules
		$target_locations = isset( $options['target_locations'] ) ? $options['target_locations'] : array();
		$target_exclusion = isset( $options['target_exclusion'] ) ? $options['target_exclusion'] : array();
		$target_users = isset( $options['target_users'] ) ? $options['target_users'] : array();
		$enable_canvas = isset( $options['enable_canvas'] ) && $options['enable_canvas'] ? '1' : '';

		if ( empty( $target_locations ) ) {
			$target_locations = array(
				'rule' => array( 'basic-global' ),
			);
		}

		update_post_meta( $section_post_id, 'ehf_target_include_locations', $target_locations );

		if ( ! empty( $target_exclusion ) ) {
			update_post_meta( $section_post_id, 'ehf_target_exclude_locations', $target_exclusion );
		}

		if ( ! empty( $target_users ) ) {
			update_post_meta( $section_post_id, 'ehf_target_user_roles', $target_users );
		}

		if ( ! empty( $enable_canvas ) ) {
			update_post_meta( $section_post_id, 'display-on-canvas-template', $enable_canvas );
		}

		// Mark as imported
		update_post_meta( $section_post_id, 'ebi_imported', '1' );
		update_post_meta( $section_post_id, 'ebi_template_name', $template_post->post_title );

		return array(
			'section_id' => $section_post_id,
			'edit_url'   => admin_url( 'post.php?post=' . $section_post_id . '&action=edit' ),
		);
	}
}

