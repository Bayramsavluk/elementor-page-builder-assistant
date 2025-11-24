<?php
/**
 * Frontend functionality
 *
 * @package Elementor_Bulk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Frontend class
 */
class EBI_Frontend {

	/**
	 * Instance
	 *
	 * @var EBI_Frontend
	 */
	private static $instance = null;

	/**
	 * Elementor instance
	 *
	 * @var \Elementor\Plugin
	 */
	private static $elementor_instance = null;

	/**
	 * Get instance
	 *
	 * @return EBI_Frontend
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
		// Check if Elementor is loaded
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		self::$elementor_instance = \Elementor\Plugin::instance();

		// Initialize frontend hooks
		add_action( 'wp', array( $this, 'hooks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Run all the Actions / Filters.
	 *
	 * @return void
	 */
	public function hooks() {
		if ( $this->header_enabled() ) {
			// Replace header.php.
			add_action( 'get_header', array( $this, 'option_override_header' ) );

			add_action( 'wp_body_open', array( $this, 'get_header_content' ) );
			add_action( 'ebi_fallback_header', array( $this, 'get_header_content' ) );
		}

		if ( $this->before_footer_enabled() ) {
			add_action( 'wp_footer', array( $this, 'get_before_footer_content' ), 20 );
		}

		if ( $this->footer_enabled() ) {
			add_action( 'wp_footer', array( $this, 'get_footer_content' ), 50 );
		}

		// Elementor Canvas compatibility
		if ( $this->header_enabled() || $this->footer_enabled() ) {
			// Action `elementor/page_templates/canvas/before_content` is introduced in Elementor Version 1.4.1.
			if ( version_compare( ELEMENTOR_VERSION, '1.4.1', '>=' ) ) {
				if ( $this->header_enabled() ) {
					add_action( 'elementor/page_templates/canvas/before_content', array( $this, 'render_header' ) );
				}
			} else {
				if ( $this->header_enabled() ) {
					add_action( 'wp_head', array( $this, 'render_header' ) );
				}
			}

			// Action `elementor/page_templates/canvas/after_content` is introduced in Elementor Version 1.9.0.
			if ( version_compare( ELEMENTOR_VERSION, '1.9.0', '>=' ) ) {
				if ( $this->footer_enabled() ) {
					add_action( 'elementor/page_templates/canvas/after_content', array( $this, 'render_footer' ) );
				}
				if ( $this->before_footer_enabled() ) {
					// check if current page template is Elementor Canvas.
					if ( 'elementor_canvas' == get_page_template_slug() ) {
						$before_footer_id = $this->get_before_footer_id();
						$override_canvas_template = get_post_meta( $before_footer_id, 'display-on-canvas-template', true );
						if ( '1' == $override_canvas_template ) {
							add_action( 'elementor/page_templates/canvas/after_content', array( $this, 'get_before_footer_content' ), 9 );
						}
					}
				}
			} else {
				if ( $this->footer_enabled() ) {
					add_action( 'wp_footer', array( $this, 'render_footer' ) );
				}
			}
		}
	}

	/**
	 * Force full width CSS for the header.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function force_fullwidth() {
		$css = '
		.force-stretched-header {
			width: 100vw;
			position: relative;
			margin-left: -50vw;
			left: 50%;
		}';

		if ( true === $this->header_enabled() ) {
			// Hide default theme header - only hide theme's original header structure
			// Our custom header is rendered via wp_body_open, so it won't match these selectors
			$css .= '
			header#masthead {
				display: none;
			}';
		}

		if ( true === $this->footer_enabled() ) {
			$css .= '
			footer#colophon,
			.site-footer,
			.footer-wrapper,
			.main-footer {
				display: none !important;
			}';
		}

		wp_add_inline_style( 'ebi-frontend-style', $css );
	}

	/**
	 * Function overriding the header in the wp_body_open way.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function option_override_header() {
		$templates   = array();
		$templates[] = 'header.php';
		
		// Capture header output to remove <header> tags while keeping wp_head working
		ob_start();
		locate_template( $templates, true );
		$header_output = ob_get_clean();
		
		// Remove <header> opening and closing tags and their content
		// This regex matches <header...> ... </header> including all attributes and nested content
		$header_output = preg_replace( '/<header[^>]*>.*?<\/header>/is', '', $header_output );
		
		// Also handle self-closing header tags if any
		$header_output = preg_replace( '/<header[^>]*\/>/is', '', $header_output );
		
		// Output the cleaned header (wp_head is already in <head>, so it's preserved)
		echo $header_output;

		if ( ! did_action( 'wp_body_open' ) ) {
			echo '<div class="force-stretched-header">';
			do_action( 'ebi_fallback_header' );
			echo '</div>';
		}
	}

	/**
	 * Render the header if display template on elementor canvas is enabled
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_header() {
		$header_id = $this->get_header_id();
		if ( ! $header_id ) {
			return;
		}

		$override_canvas_template = get_post_meta( $header_id, 'display-on-canvas-template', true );

		if ( '1' == $override_canvas_template ) {
			$this->get_header_content();
		}
	}

	/**
	 * Render the footer if display template on elementor canvas is enabled
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_footer() {
		$footer_id = $this->get_footer_id();
		if ( ! $footer_id ) {
			return;
		}

		$override_canvas_template = get_post_meta( $footer_id, 'display-on-canvas-template', true );

		if ( '1' == $override_canvas_template ) {
			$this->get_footer_content();
		}
	}

	/**
	 * Enqueue styles and scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'ebi-frontend-style', EBI_URL . 'assets/css/frontend.css', array(), EBI_VERSION );

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::instance();
			if ( method_exists( $elementor->frontend, 'enqueue_styles' ) ) {
				$elementor->frontend->enqueue_styles();
			}
		}

		if ( class_exists( '\ElementorPro\Plugin' ) ) {
			$elementor_pro = \ElementorPro\Plugin::instance();
			if ( method_exists( $elementor_pro, 'enqueue_styles' ) ) {
				$elementor_pro->enqueue_styles();
			}
		}

		if ( $this->header_enabled() ) {
			$header_id = $this->get_header_id();
			if ( $header_id ) {
				if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
					$css_file = new \Elementor\Core\Files\CSS\Post( $header_id );
				} elseif ( class_exists( '\Elementor\Post_CSS_File' ) ) {
					$css_file = new \Elementor\Post_CSS_File( $header_id );
				}

				if ( isset( $css_file ) ) {
					$css_file->enqueue();
				}
			}
		}

		if ( $this->footer_enabled() ) {
			$footer_id = $this->get_footer_id();
			if ( $footer_id ) {
				if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
					$css_file = new \Elementor\Core\Files\CSS\Post( $footer_id );
				} elseif ( class_exists( '\Elementor\Post_CSS_File' ) ) {
					$css_file = new \Elementor\Post_CSS_File( $footer_id );
				}

				if ( isset( $css_file ) ) {
					$css_file->enqueue();
				}
			}
		}

		if ( $this->before_footer_enabled() ) {
			$before_footer_id = $this->get_before_footer_id();
			if ( $before_footer_id ) {
				if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
					$css_file = new \Elementor\Core\Files\CSS\Post( $before_footer_id );
				} elseif ( class_exists( '\Elementor\Post_CSS_File' ) ) {
					$css_file = new \Elementor\Post_CSS_File( $before_footer_id );
				}
				if ( isset( $css_file ) ) {
					$css_file->enqueue();
				}
			}
		}

		if ( $this->header_enabled() || $this->footer_enabled() ) {
			$this->force_fullwidth();
		}
	}

	/**
	 * Adds classes to the body tag conditionally.
	 *
	 * @param  array $classes array with class names for the body tag.
	 *
	 * @return array          array with class names for the body tag.
	 */
	public function body_class( $classes ) {
		if ( $this->header_enabled() ) {
			$classes[] = 'ebi-header';
		}

		if ( $this->footer_enabled() ) {
			$classes[] = 'ebi-footer';
		}

		return $classes;
	}

	/**
	 * Prints the Header content.
	 *
	 * @return void
	 */
	public function get_header_content() {
		$header_id = $this->get_header_id();
		if ( ! $header_id || ! self::$elementor_instance ) {
			return;
		}

		$header_content = self::$elementor_instance->frontend->get_builder_content_for_display( $header_id );
		echo $header_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Prints the Footer content.
	 *
	 * @return void
	 */
	public function get_footer_content() {
		$footer_id = $this->get_footer_id();
		if ( ! $footer_id || ! self::$elementor_instance ) {
			return;
		}

		echo "<div class='footer-width-fixer'>";
		echo self::$elementor_instance->frontend->get_builder_content_for_display( $footer_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	/**
	 * Prints the Before Footer content.
	 *
	 * @return void
	 */
	public function get_before_footer_content() {
		$before_footer_id = $this->get_before_footer_id();
		if ( ! $before_footer_id || ! self::$elementor_instance ) {
			return;
		}

		echo "<div class='footer-width-fixer'>";
		echo self::$elementor_instance->frontend->get_builder_content_for_display( $before_footer_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	/**
	 * Checks if Header is enabled.
	 *
	 * @return bool True if header is enabled. False if header is not enabled
	 */
	public function header_enabled() {
		$header_id = $this->get_header_id();
		$status    = false;

		if ( '' !== $header_id && false !== $header_id ) {
			$status = true;
		}

		return apply_filters( 'ebi_header_enabled', $status );
	}

	/**
	 * Checks if Footer is enabled.
	 *
	 * @return bool True if footer is enabled. False if footer is not enabled.
	 */
	public function footer_enabled() {
		$footer_id = $this->get_footer_id();
		$status    = false;

		if ( '' !== $footer_id && false !== $footer_id ) {
			$status = true;
		}

		return apply_filters( 'ebi_footer_enabled', $status );
	}

	/**
	 * Checks if Before Footer is enabled.
	 *
	 * @return bool True if before footer is enabled. False if before footer is not enabled.
	 */
	public function before_footer_enabled() {
		$before_footer_id = $this->get_before_footer_id();
		$status           = false;

		if ( '' !== $before_footer_id && false !== $before_footer_id ) {
			$status = true;
		}

		return apply_filters( 'ebi_before_footer_enabled', $status );
	}

	/**
	 * Get Header ID
	 *
	 * @return (String|boolean) header id if it is set else returns false.
	 */
	public function get_header_id() {
		$template_id = $this->get_template_id( 'type_header' );

		if ( '' === $template_id ) {
			$template_id = false;
		}

		return apply_filters( 'ebi_get_header_id', $template_id );
	}

	/**
	 * Get Footer ID
	 *
	 * @return (String|boolean) footer id if it is set else returns false.
	 */
	public function get_footer_id() {
		$template_id = $this->get_template_id( 'type_footer' );

		if ( '' === $template_id ) {
			$template_id = false;
		}

		return apply_filters( 'ebi_get_footer_id', $template_id );
	}

	/**
	 * Get Before Footer ID
	 *
	 * @return String|boolean before footer id if it is set else returns false.
	 */
	public function get_before_footer_id() {
		$template_id = $this->get_template_id( 'type_before_footer' );

		if ( '' === $template_id ) {
			$template_id = false;
		}

		return apply_filters( 'ebi_get_before_footer_id', $template_id );
	}

	/**
	 * Get header or footer template id based on the meta query.
	 *
	 * @param  String $type Type of the template header/footer.
	 *
	 * @return Mixed       Returns the header or footer template id if found, else returns string ''.
	 */
	private function get_template_id( $type ) {
		// IMPORTANT: Only return templates imported by our plugin (ebi_imported = '1')
		// This prevents conflicts with header-footer-elementor plugin
		
		// Try to use our own Target Rules class, fallback to header-footer-elementor if available
		if ( class_exists( '\EBI\Lib\EBI_Target_Rules_Fields' ) ) {
			$target_rules = \EBI\Lib\EBI_Target_Rules_Fields::get_instance();
		} elseif ( class_exists( '\HFE\Lib\Astra_Target_Rules_Fields' ) ) {
			$target_rules = \HFE\Lib\Astra_Target_Rules_Fields::get_instance();
		} else {
			// Fallback: simple query without target rules
			return $this->get_template_id_simple( $type );
		}

		$option = array(
			'location'  => 'ehf_target_include_locations',
			'exclusion' => 'ehf_target_exclude_locations',
			'users'     => 'ehf_target_user_roles',
		);

		$hfe_templates = $target_rules->get_posts_by_conditions( 'elementor-hf', $option );

		foreach ( $hfe_templates as $template ) {
			$template_id = absint( $template['id'] );
			
			// Only return templates imported by our plugin
			if ( get_post_meta( $template_id, 'ebi_imported', true ) !== '1' ) {
				continue;
			}
			
			if ( get_post_meta( $template_id, 'ehf_template_type', true ) === $type ) {
				if ( function_exists( 'pll_current_language' ) ) {
					if ( pll_current_language( 'slug' ) == pll_get_post_language( $template_id, 'slug' ) ) {
						return $template_id;
					}
				} else {
					return $template_id;
				}
			}
		}

		return '';
	}

	/**
	 * Simple fallback method to get template ID without target rules
	 *
	 * @param string $type Template type.
	 * @return string|false Template ID or false
	 */
	private function get_template_id_simple( $type ) {
		$args = array(
			'post_type'      => 'elementor-hf',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => 'ehf_template_type',
					'value' => $type,
				),
				// Only return templates imported by our plugin
				array(
					'key'   => 'ebi_imported',
					'value' => '1',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			return $query->posts[0]->ID;
		}

		return '';
	}
}

