<?php
/**
 * Admin functionality
 *
 * @package Elementor_Bulk_Importer
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin class
 */
class EBI_Admin {

	/**
	 * Instance
	 *
	 * @var EBI_Admin
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return EBI_Admin
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
		add_action( 'init', array( $this, 'header_footer_posttype' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'add_meta_boxes', array( $this, 'ehf_register_metabox' ) );
		add_action( 'save_post', array( $this, 'ehf_save_meta' ) );
		add_action( 'admin_notices', array( $this, 'location_notice' ) );
	add_action( 'template_redirect', array( $this, 'block_template_frontend' ) );
		add_filter( 'single_template', array( $this, 'load_canvas_template' ) );
		
		// Quick Edit için AJAX handler - WordPress core'dan sonra çalışsın
		add_action( 'wp_ajax_inline-save', array( $this, 'ajax_inline_save' ), 20 );
		
			if ( is_admin() ) {
			// Column filters - sadece bizim sayfamızda çalışsın (çakışmayı önlemek için)
			add_action( 'current_screen', array( $this, 'setup_column_filters' ) );
			
			// Render columns - her zaman çalışsın ama sadece ebi_imported olan postlar için
			add_action( 'manage_elementor-hf_posts_custom_column', array( $this, 'render_shortcode_column' ), 5, 2 );
			add_action( 'manage_elementor-hf_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
			add_action( 'admin_footer', array( $this, 'add_header_footer_modal' ) );
			
			// Pages custom columns and modal
			add_filter( 'manage_page_posts_columns', array( $this, 'page_column_headings' ) );
			add_action( 'manage_page_posts_custom_column', array( $this, 'page_column_content' ), 10, 2 );
			add_action( 'admin_footer-edit.php', array( $this, 'add_pages_modal' ) );
			
		}
		
		add_action( 'admin_notices', array( $this, 'hide_admin_notices' ), 1 );
		add_action( 'all_admin_notices', array( $this, 'hide_admin_notices' ), 1 );
		
		// Disable Elementor admin top bar on our Header & Footer page
		add_filter(
			'elementor/admin-top-bar/is-active',
			array( $this, 'disable_elementor_admin_top_bar' ),
			10,
			2
		);
		
	// Filter posts query to show only our imported templates on our custom page
		add_action( 'current_screen', array( $this, 'maybe_filter_hf_posts_query' ) );
		
		// Hide our templates from Ultimate Addons (header-footer-elementor) page
		add_action( 'current_screen', array( $this, 'maybe_hide_from_ultimate_addons' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
	add_menu_page(
			__( 'Elementor Page Builder Assistant', 'elementor-bulk-importer' ),
			__( 'Elementor Import', 'elementor-bulk-importer' ),
			'manage_options',
			'ebi-settings',
			array( $this, 'render_pages_page' ),
			'dashicons-upload',
			30
		);

	add_submenu_page(
			'ebi-settings',
			__( 'Pages', 'elementor-bulk-importer' ),
			__( 'Pages', 'elementor-bulk-importer' ),
			'manage_options',
			'ebi-settings',
			array( $this, 'render_pages_page' )
		);

		// Header & Footer custom page - using custom URL to prevent conflicts
		add_submenu_page(
			'ebi-settings',
			__( 'Header/Footer Builder', 'elementor-bulk-importer' ),
			__( 'Header & Footer', 'elementor-bulk-importer' ),
			'edit_pages',
			'ebi-header-footer',
			array( $this, 'render_header_footer_list_page' ),
			11
		);
		
		// Settings page
		add_submenu_page(
			'ebi-settings',
			__( 'Settings', 'elementor-bulk-importer' ),
			__( 'Settings', 'elementor-bulk-importer' ),
			'manage_options',
			'ebi-settings-page',
			array( $this, 'render_settings_page' )
		);
		
		
	}


	/**
	 * Render pages page
	 */
	public function render_pages_page() {
		// Include list table class
		if ( ! class_exists( 'EBI_Pages_List_Table' ) ) {
			require_once EBI_DIR . 'includes/class-pages-list-table.php';
		}

		// Create list table instance
		$list_table = new EBI_Pages_List_Table();
		
		// Process bulk action first (before prepare_items)
		$list_table->process_bulk_action();
		
		// Then prepare items
		$list_table->prepare_items();

		?>
		<div class="wrap ebi-admin-page">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<a href="#" class="page-title-action ebi-add-new-page"><?php esc_html_e( 'Add New', 'elementor-bulk-importer' ); ?></a>
			<hr class="wp-header-end">

		<?php
		// Get current post status for tabs - simplified version (only All, Draft, Trash)
		$current_status = isset( $_GET['post_status'] ) ? sanitize_text_field( $_GET['post_status'] ) : 'all';
		
		// Get page counts
		$page_counts = wp_count_posts( 'page' );
		$publish_count = isset( $page_counts->publish ) ? $page_counts->publish : 0;
		$draft_count = isset( $page_counts->draft ) ? $page_counts->draft : 0;
		$trash_count = isset( $page_counts->trash ) ? $page_counts->trash : 0;
		$all_count = $publish_count + $draft_count; // Include only published and drafts in All
		?>

		<ul class="subsubsub">
			<li class="all">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ebi-settings' ) ); ?>" class="<?php echo 'all' === $current_status || empty( $current_status ) ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'elementor-bulk-importer' ); ?> <span class="count">(<?php echo esc_html( $all_count ); ?>)</span>
				</a>
			</li>
			<?php if ( $draft_count > 0 || 'draft' === $current_status ) : ?>
				<li class="draft">
					 | <a href="<?php echo esc_url( add_query_arg( 'post_status', 'draft', admin_url( 'admin.php?page=ebi-settings' ) ) ); ?>" class="<?php echo 'draft' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Draft', 'elementor-bulk-importer' ); ?> <span class="count">(<?php echo esc_html( $draft_count ); ?>)</span>
					</a>
				</li>
			<?php endif; ?>
			<?php if ( $trash_count > 0 || 'trash' === $current_status ) : ?>
				<li class="trash">
					 | <a href="<?php echo esc_url( add_query_arg( 'post_status', 'trash', admin_url( 'admin.php?page=ebi-settings' ) ) ); ?>" class="<?php echo 'trash' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Trash', 'elementor-bulk-importer' ); ?> <span class="count">(<?php echo esc_html( $trash_count ); ?>)</span>
					</a>
				</li>
			<?php endif; ?>
		</ul>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ebi-settings' ) ); ?>">
				<input type="hidden" name="page" value="ebi-settings">
				<?php
				// Preserve GET parameters in hidden fields for POST form
				if ( isset( $_GET['post_status'] ) && 'all' !== $_GET['post_status'] ) {
					echo '<input type="hidden" name="post_status" value="' . esc_attr( sanitize_text_field( $_GET['post_status'] ) ) . '">';
				}
				if ( isset( $_GET['m'] ) ) {
					echo '<input type="hidden" name="m" value="' . esc_attr( sanitize_text_field( $_GET['m'] ) ) . '">';
				}
					$list_table->search_box( __( 'Search Pages', 'elementor-bulk-importer' ), 'post' );
				$list_table->display();
				?>
			</form>

			<!-- Quick Edit Form - WordPress formatı -->
			<?php
			// Render WordPress Quick Edit form
			// Use WordPress WP_Posts_List_Table class inline_edit method
			if ( ! class_exists( 'WP_Posts_List_Table' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
			}
			
			// Create a temporary list table instance to render the inline edit form
			$GLOBALS['hook_suffix'] = 'edit-page';
			$wp_list_table = _get_list_table( 'WP_Posts_List_Table', array( 'screen' => 'edit-page' ) );
			
			// Override the _args to match our custom table
			$wp_list_table->_args = array(
				'singular' => 'page',
				'plural'   => 'pages',
				'ajax'     => false,
			);
			
			// Render the inline edit form
			$wp_list_table->inline_edit();
			unset( $GLOBALS['hook_suffix'] );
			?>

			<!-- Add New Page Modal (multi-row) -->
			<?php
			// Get enabled translation APIs for Pages modal
			$translation_settings = get_option( 'ebi_translation_settings', array() );
			$enabled_apis_pages = array();
			if ( isset( $translation_settings['apis'] ) ) {
				foreach ( $translation_settings['apis'] as $api_id => $api_data ) {
					if ( isset( $api_data['enabled'] ) && $api_data['enabled'] ) {
						$api_names = array(
							'libretranslate' => 'LibreTranslate',
							'mymemory' => 'MyMemory Translation',
							'deepl' => 'DeepL API',
							'microsoft' => 'Microsoft Translator',
							'yandex' => 'Yandex Translate',
							'argostranslate' => 'Argos Translate',
						);
						$enabled_apis_pages[ $api_id ] = isset( $api_names[ $api_id ] ) ? $api_names[ $api_id ] : $api_id;
					}
				}
			}
			?>
			<div id="ebi-add-page-modal" class="ebi-modal" style="display:none;" 
			     data-enabled-apis="<?php echo esc_attr( wp_json_encode( $enabled_apis_pages ) ); ?>"
			     data-is-pro="<?php echo EBI_PRO_VERSION ? '1' : '0'; ?>">
				<div class="ebi-modal-content" style="max-width: 1200px;">
					<div class="ebi-modal-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
						<h2 style="margin:0;"><?php esc_html_e( 'Add New Page', 'elementor-bulk-importer' ); ?></h2>
						<div>
							<button type="button" class="button ebi-add-page-row"><?php esc_html_e( 'Add Row', 'elementor-bulk-importer' ); ?></button>
							<span class="ebi-modal-close" style="margin-left:8px;cursor:pointer;">&times;</span>
						</div>
					</div>
					<div class="ebi-modal-body">
						<form id="ebi-add-page-form">
							<div id="ebi-page-rows" class="ebi-page-rows" style="display:flex;flex-direction:column;gap:10px;"></div>
							<!-- Hidden prototypes for cloning -->
							<div id="ebi-page-hidden-prototypes" style="display:none;">
								<div class="ebi-proto-parent">
									<?php
									wp_dropdown_pages( array(
										'name'             => 'ebi_proto_parent',
										'id'               => 'ebi-proto-parent',
										'show_option_none' => __( 'None', 'elementor-bulk-importer' ),
										'option_none_value' => '0',
										'class'            => 'regular-text',
									) );
									?>
								</div>
								<div class="ebi-proto-ptype">
									<select id="ebi-proto-template-type" class="regular-text">
									<option value="default"><?php esc_html_e( 'Default Template', 'elementor-bulk-importer' ); ?></option>
										<option value="elementor_canvas"><?php esc_html_e( 'Elementor Canvas', 'elementor-bulk-importer' ); ?></option>
										<option value="elementor_header_footer"><?php esc_html_e( 'Elementor Full Width', 'elementor-bulk-importer' ); ?></option>
										<?php
										$theme_templates = get_page_templates();
										foreach ( $theme_templates as $template_name => $template_filename ) {
											echo '<option value="' . esc_attr( $template_filename ) . '">' . esc_html( $template_name ) . ' (' . esc_html( __( 'Theme', 'elementor-bulk-importer' ) ) . ')</option>';
										}
										?>
									</select>
								</div>
							</div>
							<div class="ebi-modal-actions" style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Add All', 'elementor-bulk-importer' ); ?></button>
								<button type="button" class="button ebi-modal-cancel"><?php esc_html_e( 'Cancel', 'elementor-bulk-importer' ); ?></button>
							</div>
						</form>
						<div id="ebi-page-messages"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}


	/**
	 * Render header/footer list page
	 */
	public function render_header_footer_list_page() {
		global $typenow, $wp_list_table, $post_type;
		
		// Set post type globally
		$typenow = 'elementor-hf';
		$post_type = 'elementor-hf';
		
		// Set $_GET['post_type'] so WordPress knows what post type we're working with
		if ( ! isset( $_GET['post_type'] ) ) {
			$_GET['post_type'] = 'elementor-hf';
		}
		
		// Ensure list table class is loaded
		if ( ! class_exists( 'WP_Posts_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
		}
		
		if ( ! class_exists( 'WP_Screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		}
		
		if ( ! function_exists( 'convert_to_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}

		// Create screen object for list table
		$screen = get_current_screen();
		if ( ! $screen || 'edit-elementor-hf' !== $screen->id ) {
			// Create screen manually using convert_to_screen
			$screen = convert_to_screen( 'edit-elementor-hf' );
			if ( $screen ) {
				$screen->post_type = 'elementor-hf';
				$screen->id = 'edit-elementor-hf';
				set_current_screen( $screen );
			}
		}
		
		// Ensure we have a valid screen
		if ( ! $screen ) {
			$screen = get_current_screen();
		}
		
		// Get WordPress posts list table
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
		
		if ( ! function_exists( '_get_list_table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/list-table.php';
		}
		
		if ( function_exists( '_get_list_table' ) ) {
			$wp_list_table = _get_list_table( 'WP_Posts_List_Table', array( 'screen' => $screen ) );
		} else {
			// Fallback: instantiate directly
			$wp_list_table = new WP_Posts_List_Table( array( 'screen' => $screen ) );
		}
		
		// Set global for WordPress hooks
		$GLOBALS['wp_list_table'] = $wp_list_table;
		
		// Customize bulk actions dropdown
		add_filter( 'bulk_actions-edit-elementor-hf', array( $this, 'filter_hf_bulk_actions' ) );
		
		// Process bulk actions - custom handler for our page
		$this->process_hf_bulk_actions();
		
		// Prepare items
		$wp_list_table->prepare_items();
		
		// Get current post status
		$post_status = isset( $_GET['post_status'] ) ? sanitize_text_field( $_GET['post_status'] ) : '';
		
		?>
		<div class="wrap ebi-admin-page">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Header & Footer', 'elementor-bulk-importer' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=elementor-hf' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'elementor-bulk-importer' ); ?></a>
			<a href="#" class="page-title-action ebi-add-new-hf"><?php esc_html_e( 'Add from Template', 'elementor-bulk-importer' ); ?></a>
			<hr class="wp-header-end">

		<?php
		// Get post status counts - only for ebi_imported posts (All, Draft, Trash)
		$ebi_post_counts = array();
		foreach ( array( 'publish', 'draft', 'trash' ) as $status ) {
			$count_query = new WP_Query( array(
				'post_type'      => 'elementor-hf',
				'post_status'    => $status,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => 'ebi_imported',
						'value'   => '1',
						'compare' => '=',
					),
				),
			) );
			$ebi_post_counts[ $status ] = $count_query->found_posts;
		}
		
		$publish_count = isset( $ebi_post_counts['publish'] ) ? $ebi_post_counts['publish'] : 0;
		$draft_count = isset( $ebi_post_counts['draft'] ) ? $ebi_post_counts['draft'] : 0;
		$trash_count = isset( $ebi_post_counts['trash'] ) ? $ebi_post_counts['trash'] : 0;
		$all_count = $publish_count + $draft_count; // Include only published and drafts in All
		?>
		
		<ul class="subsubsub">
			<li class="all">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ebi-header-footer' ) ); ?>" class="<?php echo empty( $post_status ) ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'elementor-bulk-importer' ); ?> <span class="count">(<?php echo esc_html( $all_count ); ?>)</span>
				</a>
			</li>
			<?php if ( $draft_count > 0 || 'draft' === $post_status ) : ?>
				<li class="draft">
					 | <a href="<?php echo esc_url( add_query_arg( 'post_status', 'draft', admin_url( 'admin.php?page=ebi-header-footer' ) ) ); ?>" class="<?php echo 'draft' === $post_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Draft', 'elementor-bulk-importer' ); ?> <span class="count">(<?php echo esc_html( $draft_count ); ?>)</span>
					</a>
				</li>
			<?php endif; ?>
			<?php if ( $trash_count > 0 || 'trash' === $post_status ) : ?>
				<li class="trash">
					 | <a href="<?php echo esc_url( add_query_arg( 'post_status', 'trash', admin_url( 'admin.php?page=ebi-header-footer' ) ) ); ?>" class="<?php echo 'trash' === $post_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Trash', 'elementor-bulk-importer' ); ?> <span class="count">(<?php echo esc_html( $trash_count ); ?>)</span>
					</a>
				</li>
			<?php endif; ?>
		</ul>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ebi-header-footer' ) ); ?>">
				<input type="hidden" name="page" value="ebi-header-footer">
				<?php
				// Preserve GET parameters
				if ( isset( $_GET['post_status'] ) && 'all' !== $_GET['post_status'] ) {
					echo '<input type="hidden" name="post_status" value="' . esc_attr( sanitize_text_field( $_GET['post_status'] ) ) . '">';
				}
				if ( isset( $_GET['m'] ) ) {
					echo '<input type="hidden" name="m" value="' . esc_attr( sanitize_text_field( $_GET['m'] ) ) . '">';
				}
				wp_nonce_field( 'bulk-posts', '_wpnonce', false );
				$wp_list_table->search_box( __( 'Search', 'elementor-bulk-importer' ), 'post' );
				$wp_list_table->display();
				?>
			</form>
			
			<!-- Quick Edit Form - WordPress formatı -->
			<?php
			// WordPress'in Quick Edit form'unu render et
			$wp_list_table->inline_edit();
			?>
		</div>
		<?php
	}

	/**
	 * Filter bulk actions for Header & Footer page
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public function filter_hf_bulk_actions( $actions ) {
		$post_status = isset( $_GET['post_status'] ) ? sanitize_text_field( $_GET['post_status'] ) : '';
		
		if ( 'trash' === $post_status ) {
			// Trash mode
			return array(
				'untrash' => __( 'Restore', 'elementor-bulk-importer' ),
				'delete'  => __( 'Delete Permanently', 'elementor-bulk-importer' ),
			);
		} else {
			// Normal mode
			return array(
				'trash' => __( 'Move to Trash', 'elementor-bulk-importer' ),
			);
		}
	}
	
	/**
	 * Process bulk actions for Header & Footer page
	 */
	private function process_hf_bulk_actions() {
		// Check if bulk action submitted
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}
		
		check_admin_referer( 'bulk-posts', '_wpnonce' );
		
		$action = '';
		if ( isset( $_POST['action'] ) && -1 != $_POST['action'] ) {
			$action = sanitize_text_field( $_POST['action'] );
		} elseif ( isset( $_POST['action2'] ) && -1 != $_POST['action2'] ) {
			$action = sanitize_text_field( $_POST['action2'] );
		}
		
		if ( empty( $action ) || ! isset( $_POST['post'] ) ) {
			return;
		}
		
		$post_ids = array_map( 'intval', (array) $_POST['post'] );
		
		if ( empty( $post_ids ) ) {
			return;
		}
		
		// Base redirect URL
		$redirect_url = admin_url( 'admin.php' );
		$redirect_url = add_query_arg( 'page', 'ebi-header-footer', $redirect_url );
		
		// Preserve post_status
		if ( isset( $_GET['post_status'] ) && ! empty( $_GET['post_status'] ) ) {
			$redirect_url = add_query_arg( 'post_status', sanitize_text_field( $_GET['post_status'] ), $redirect_url );
		}
		
		switch ( $action ) {
			case 'trash':
				if ( current_user_can( 'delete_posts' ) ) {
					$trashed = 0;
					foreach ( $post_ids as $post_id ) {
						if ( wp_trash_post( $post_id ) ) {
							$trashed++;
						}
					}
					if ( $trashed > 0 ) {
						$redirect_url = add_query_arg( 'trashed', $trashed, $redirect_url );
					}
				}
				break;
				
			case 'untrash':
				if ( current_user_can( 'delete_posts' ) ) {
					$untrashed = 0;
					foreach ( $post_ids as $post_id ) {
						if ( wp_untrash_post( $post_id ) ) {
							$untrashed++;
						}
					}
					if ( $untrashed > 0 ) {
						$redirect_url = add_query_arg( 'untrashed', $untrashed, $redirect_url );
						$redirect_url = remove_query_arg( 'post_status', $redirect_url );
					}
				}
				break;
				
			case 'delete':
				if ( current_user_can( 'delete_posts' ) ) {
					$deleted = 0;
					foreach ( $post_ids as $post_id ) {
						if ( wp_delete_post( $post_id, true ) ) {
							$deleted++;
						}
					}
					if ( $deleted > 0 ) {
						$redirect_url = add_query_arg( 'deleted', $deleted, $redirect_url );
						$redirect_url = remove_query_arg( 'post_status', $redirect_url );
					}
				}
				break;
		}
		
		wp_redirect( $redirect_url );
		exit;
	}
	
	/**
	 * Get imported pages
	 *
	 * @return array
	 */
	private function get_imported_pages() {
		// Get all pages, similar to Header & Footer
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$result = array();
		foreach ( $pages as $page ) {
			$template_name = get_post_meta( $page->ID, 'ebi_template_name', true );
			$parent_id = wp_get_post_parent_id( $page->ID );
			$page_template = get_post_meta( $page->ID, '_wp_page_template', true );
			
			$result[] = array(
				'id'            => $page->ID,
				'title'         => $page->post_title,
				'template_name' => $template_name ? $template_name : '—',
				'parent'        => $parent_id,
				'page_template' => empty( $page_template ) || 'default' === $page_template ? __( 'Varsayılan şablon', 'elementor-bulk-importer' ) : $page_template,
				'date'          => get_the_date( '', $page->ID ),
				'status'        => $page->post_status,
			);
		}

		return $result;
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts( $hook ) {
		global $pagenow, $post_type;
		
		// Load on our plugin pages
		// WordPress hook format: toplevel_page_ebi-settings or elementor-import_page_ebi-xxx
		if ( strpos( $hook, 'ebi-settings' ) !== false || 
		     strpos( $hook, 'ebi-header-footer' ) !== false ||
		     strpos( $hook, 'ebi-settings-page' ) !== false ) {
			
			wp_enqueue_style(
				'ebi-admin-style',
				EBI_URL . 'assets/css/admin.css',
				array(),
				EBI_VERSION
			);

			wp_enqueue_script(
				'ebi-admin-script',
				EBI_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				EBI_VERSION,
				true
			);

			wp_localize_script(
				'ebi-admin-script',
				'ebiAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'ebi-nonce' ),
					'strings'  => array(
						'success'      => __( 'Başarılı!', 'elementor-bulk-importer' ),
						'error'        => __( 'Hata oluştu!', 'elementor-bulk-importer' ),
						'loading'      => __( 'Yükleniyor...', 'elementor-bulk-importer' ),
						'selectTemplate' => __( 'Lütfen bir şablon seçin.', 'elementor-bulk-importer' ),
					),
				)
			);
		}

		// Load scripts for Pages edit screen
		$screen = get_current_screen();
		if ( 'edit.php' == $pagenow && $screen && 'page' === $screen->post_type ) {
		wp_enqueue_style(
			'ebi-admin-style',
			EBI_URL . 'assets/css/admin.css',
			array(),
			EBI_VERSION
		);

		wp_enqueue_script(
			'ebi-admin-script',
			EBI_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			EBI_VERSION,
			true
		);

			wp_localize_script(
				'ebi-admin-script',
				'ebiAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'ebi-nonce' ),
					'strings'  => array(
						'success'      => __( 'Başarılı!', 'elementor-bulk-importer' ),
						'error'        => __( 'Hata oluştu!', 'elementor-bulk-importer' ),
						'loading'      => __( 'Yükleniyor...', 'elementor-bulk-importer' ),
						'selectTemplate' => __( 'Lütfen bir şablon seçin.', 'elementor-bulk-importer' ),
					),
				)
			);

			// Load WordPress inline edit scripts for quick edit functionality
			wp_enqueue_script( 'inline-edit-post' );
		}

		// Load scripts for our custom pages
		if ( strpos( $hook, 'ebi-settings' ) !== false ) {
			// WordPress'in standart list table script'lerini yükle
			wp_enqueue_script( 'wp-lists' );
			wp_enqueue_script( 'inline-edit-post' );
			
			// Ensure admin styles are loaded
			wp_enqueue_style( 'wp-admin' );
			
			// Inline edit için gerekli localization
			wp_localize_script( 'inline-edit-post', 'inlineEditL10n', array(
				'error'      => __( 'Hata oluştu.' ),
				'ntdeltitle' => __( 'Öğeyi kalıcı olarak sil' ),
				'notitle'    => __( '(başlıksız)' ),
				'comma'      => _x( ',', 'tag delimiter' ),
			) );
			
			// WordPress'in inline-edit-post.js'inin page post type için çalışması için
			// Screen ID'yi ayarla
			add_filter( 'current_screen', array( $this, 'set_screen_for_inline_edit' ), 999 );
		}

		// Load Header & Footer admin styles and scripts - birebir header-footer-elementor'dan
		$screen = get_current_screen();
		if ( ( 'elementor-hf' == $post_type && ( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) ) || ( 'edit.php' == $pagenow && $screen && 'edit-elementor-hf' == $screen->id ) || ( strpos( $hook, 'ebi-header-footer' ) !== false ) ) {
			wp_enqueue_style(
				'ebi-hf-admin-style',
				EBI_URL . 'assets/css/hf-admin.css',
				array(),
				EBI_VERSION
			);
			
			wp_enqueue_script(
				'ebi-hf-admin-script',
				EBI_URL . 'assets/js/hf-admin.js',
				array( 'jquery' ),
				EBI_VERSION,
				true
			);
			
			// Load main admin script for modal functionality (both edit.php and our custom page)
			if ( ( 'edit.php' == $pagenow && $screen && 'edit-elementor-hf' == $screen->id ) || ( strpos( $hook, 'ebi-header-footer' ) !== false ) ) {
				wp_enqueue_style(
					'ebi-admin-style',
					EBI_URL . 'assets/css/admin.css',
					array(),
					EBI_VERSION
				);

				wp_enqueue_script(
					'ebi-admin-script',
					EBI_URL . 'assets/js/admin.js',
					array( 'jquery' ),
					EBI_VERSION,
					true
				);

		wp_localize_script(
			'ebi-admin-script',
			'ebiAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ebi-nonce' ),
				'strings'  => array(
					'success'      => __( 'Başarılı!', 'elementor-bulk-importer' ),
					'error'        => __( 'Hata oluştu!', 'elementor-bulk-importer' ),
					'loading'      => __( 'Yükleniyor...', 'elementor-bulk-importer' ),
					'selectTemplate' => __( 'Lütfen bir şablon seçin.', 'elementor-bulk-importer' ),
				),
			)
		);
				
				// Load WordPress inline edit scripts
				wp_enqueue_script( 'wp-lists' );
				wp_enqueue_script( 'inline-edit-post' );
				
				// Inline edit için gerekli localization
				wp_localize_script( 'inline-edit-post', 'inlineEditL10n', array(
					'error'      => __( 'Hata oluştu.' ),
					'ntdeltitle' => __( 'Öğeyi kalıcı olarak sil' ),
					'notitle'    => __( '(başlıksız)' ),
					'comma'      => _x( ',', 'tag delimiter' ),
				) );
			}
			
			// Load Target Rules assets if header-footer-elementor exists
			// Load Target Rule assets - use our own class first, fallback to header-footer-elementor
			if ( class_exists( '\EBI\Lib\EBI_Target_Rules_Fields' ) ) {
				\EBI\Lib\EBI_Target_Rules_Fields::get_instance()->admin_styles();
			} elseif ( class_exists( '\HFE\Lib\Astra_Target_Rules_Fields' ) ) {
				\HFE\Lib\Astra_Target_Rules_Fields::get_instance()->admin_styles();
			}
		}
	}

	/**
	 * Register Post type for Elementor Header & Footer Builder templates
	 * Birebir header-footer-elementor'dan kopyalandı
	 *
	 * @return void
	 */
	public function header_footer_posttype() {
		$labels = array(
			'name'               => esc_html__( 'Elementor Header & Footer Builder', 'elementor-bulk-importer' ),
			'singular_name'      => esc_html__( 'Elementor Header & Footer Builder', 'elementor-bulk-importer' ),
			'menu_name'          => esc_html__( 'Elementor Header & Footer Builder', 'elementor-bulk-importer' ),
			'name_admin_bar'     => esc_html__( 'Elementor Header & Footer Builder', 'elementor-bulk-importer' ),
			'add_new'            => esc_html__( 'Add New', 'elementor-bulk-importer' ),
			'add_new_item'       => esc_html__( 'Add New', 'elementor-bulk-importer' ),
			'new_item'           => esc_html__( 'New Template', 'elementor-bulk-importer' ),
			'edit_item'          => esc_html__( 'Edit Template', 'elementor-bulk-importer' ),
			'view_item'          => esc_html__( 'View Template', 'elementor-bulk-importer' ),
			'all_items'          => esc_html__( 'View All', 'elementor-bulk-importer' ),
			'search_items'       => esc_html__( 'Search Templates', 'elementor-bulk-importer' ),
			'parent_item_colon'  => esc_html__( 'Parent Templates:', 'elementor-bulk-importer' ),
			'not_found'          => esc_html__( 'No Templates found.', 'elementor-bulk-importer' ),
			'not_found_in_trash' => esc_html__( 'No Templates found in Trash.', 'elementor-bulk-importer' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'exclude_from_search' => true,
			'capability_type'     => 'post',
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-editor-kitchensink',
			'supports'            => array( 'title', 'thumbnail', 'elementor' ),
			'menu_position'       => 5,
			'capabilities'        => array(
				'edit_post'              => 'manage_options',
				'read_post'              => 'read',
				'delete_post'            => 'manage_options',
				'edit_posts'             => 'manage_options',
				'edit_others_posts'      => 'manage_options',
				'publish_posts'          => 'manage_options',
				'read_private_posts'     => 'manage_options',
				'delete_posts'           => 'manage_options',
				'delete_others_posts'    => 'manage_options',
				'delete_private_posts'   => 'manage_options',
				'delete_published_posts' => 'manage_options',
				'create_posts'           => 'manage_options',
			),
		);

		register_post_type( 'elementor-hf', $args );
	}


	/**
	 * Register meta box(es).
	 * Birebir header-footer-elementor'dan kopyalandı
	 *
	 * @return void
	 */
	public function ehf_register_metabox() {
		add_meta_box(
			'ehf-meta-box',
			__( 'Elementor Header & Footer Builder Options', 'elementor-bulk-importer' ),
			array(
				$this,
				'efh_metabox_render',
			),
			'elementor-hf',
			'normal',
			'high'
		);
	}

	/**
	 * Render Meta field.
	 * Birebir header-footer-elementor'dan kopyalandı
	 *
	 * @param array $post Currennt post object which is being displayed.
	 * @return void
	 */
	public function efh_metabox_render( $post ) {
		$values            = get_post_custom( $post->ID );
		$template_type     = isset( $values['ehf_template_type'] ) ? esc_attr( sanitize_text_field( $values['ehf_template_type'][0] ) ) : '';
		$display_on_canvas = isset( $values['display-on-canvas-template'] ) ? true : false;

		// We'll use this nonce field later on when saving.
		wp_nonce_field( 'ehf_meta_nounce', 'ehf_meta_nounce' );
		?>
		<table class="hfe-options-table widefat">
			<tbody>
				<tr class="hfe-options-row type-of-template">
					<td class="hfe-options-row-heading">
						<label for="ehf_template_type"><?php esc_html_e( 'Type of Template', 'elementor-bulk-importer' ); ?></label>
					</td>
					<td class="hfe-options-row-content">
						<select name="ehf_template_type" id="ehf_template_type">
							<option value="" <?php selected( $template_type, '' ); ?>><?php esc_html_e( 'Select Option', 'elementor-bulk-importer' ); ?></option>
							<option value="type_header" <?php selected( $template_type, 'type_header' ); ?>><?php esc_html_e( 'Header', 'elementor-bulk-importer' ); ?></option>
							<option value="type_before_footer" <?php selected( $template_type, 'type_before_footer' ); ?>><?php esc_html_e( 'Before Footer', 'elementor-bulk-importer' ); ?></option>
							<option value="type_footer" <?php selected( $template_type, 'type_footer' ); ?>><?php esc_html_e( 'Footer', 'elementor-bulk-importer' ); ?></option>
							<option value="custom" <?php selected( $template_type, 'custom' ); ?>><?php esc_html_e( 'Custom Block', 'elementor-bulk-importer' ); ?></option>
						</select>
					</td>
				</tr>

				<?php $this->display_rules_tab(); ?>
				<tr class="hfe-options-row hfe-shortcode">
					<td class="hfe-options-row-heading">
						<label for="ehf_template_type"><?php esc_html_e( 'Shortcode', 'elementor-bulk-importer' ); ?></label>
						<i class="hfe-options-row-heading-help dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Copy this shortcode and paste it into your post, page, or text widget content.', 'elementor-bulk-importer' ); ?>">
						</i>
					</td>
					<td class="hfe-options-row-content">
						<span class="hfe-shortcode-col-wrap">
							<input type="text" onfocus="this.select();" readonly="readonly" value="[hfe_template id='<?php echo esc_attr( $post->ID ); ?>']" class="hfe-large-text code">
						</span>
					</td>
				</tr>
				<tr class="hfe-options-row enable-for-canvas">
					<td class="hfe-options-row-heading">
						<label for="display-on-canvas-template">
							<?php esc_html_e( 'Enable Layout for Elementor Canvas Template?', 'elementor-bulk-importer' ); ?>
						</label>
						<i class="hfe-options-row-heading-help dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Enabling this option will display this layout on pages using Elementor Canvas Template.', 'elementor-bulk-importer' ); ?>"></i>
					</td>
					<td class="hfe-options-row-content">
						<input type="checkbox" id="display-on-canvas-template" name="display-on-canvas-template" value="1" <?php checked( $display_on_canvas, true ); ?> />
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Markup for Display Rules Tabs.
	 * Birebir header-footer-elementor'dan kopyalandı
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function display_rules_tab() {
		// Try to use our own Target Rules class first, fallback to header-footer-elementor if available
		$target_rules_class = null;
		if ( class_exists( '\EBI\Lib\EBI_Target_Rules_Fields' ) ) {
			$target_rules_class = '\EBI\Lib\EBI_Target_Rules_Fields';
		} elseif ( class_exists( '\HFE\Lib\Astra_Target_Rules_Fields' ) ) {
			$target_rules_class = '\HFE\Lib\Astra_Target_Rules_Fields';
		}

		if ( $target_rules_class ) {
			$target_rules_class::get_instance()->admin_styles();
			
			$include_locations = get_post_meta( get_the_id(), 'ehf_target_include_locations', true );
			$exclude_locations = get_post_meta( get_the_id(), 'ehf_target_exclude_locations', true );
			$users             = get_post_meta( get_the_id(), 'ehf_target_user_roles', true );
			?>
			<tr class="bsf-target-rules-row hfe-options-row">
				<td class="bsf-target-rules-row-heading hfe-options-row-heading">
					<label><?php esc_html_e( 'Display On', 'elementor-bulk-importer' ); ?></label>
					<i class="bsf-target-rules-heading-help dashicons dashicons-editor-help"
						title="<?php echo esc_attr__( 'Add locations for where this template should appear.', 'elementor-bulk-importer' ); ?>"></i>
				</td>
				<td class="bsf-target-rules-row-content hfe-options-row-content">
					<?php
					$target_rules_class::target_rule_settings_field(
						'bsf-target-rules-location',
						array(
							'title'          => __( 'Display Rules', 'elementor-bulk-importer' ),
							'value'          => '[{"type":"basic-global","specific":null}]',
							'tags'           => 'site,enable,target,pages',
							'rule_type'      => 'display',
							'add_rule_label' => __( 'Add Display Rule', 'elementor-bulk-importer' ),
						),
						$include_locations
					);
					?>
				</td>
			</tr>
			<tr class="bsf-target-rules-row hfe-options-row">
				<td class="bsf-target-rules-row-heading hfe-options-row-heading">
					<label><?php esc_html_e( 'Do Not Display On', 'elementor-bulk-importer' ); ?></label>
					<i class="bsf-target-rules-heading-help dashicons dashicons-editor-help"
						title="<?php echo esc_attr__( 'Add locations for where this template should not appear.', 'elementor-bulk-importer' ); ?>"></i>
				</td>
				<td class="bsf-target-rules-row-content hfe-options-row-content">
					<?php
					$target_rules_class::target_rule_settings_field(
						'bsf-target-rules-exclusion',
						array(
							'title'          => __( 'Exclude On', 'elementor-bulk-importer' ),
							'value'          => '[]',
							'tags'           => 'site,enable,target,pages',
							'add_rule_label' => __( 'Add Exclusion Rule', 'elementor-bulk-importer' ),
							'rule_type'      => 'exclude',
						),
						$exclude_locations
					);
					?>
				</td>
			</tr>
			<tr class="bsf-target-rules-row hfe-options-row">
				<td class="bsf-target-rules-row-heading hfe-options-row-heading">
					<label><?php esc_html_e( 'User Roles', 'elementor-bulk-importer' ); ?></label>
					<i class="bsf-target-rules-heading-help dashicons dashicons-editor-help" title="<?php echo esc_attr__( 'Display custom template based on user role.', 'elementor-bulk-importer' ); ?>"></i>
				</td>
				<td class="bsf-target-rules-row-content hfe-options-row-content">
					<?php
					$target_rules_class::target_user_role_settings_field(
						'bsf-target-rules-users',
						array(
							'title'          => __( 'Users', 'elementor-bulk-importer' ),
							'value'          => '[]',
							'tags'           => 'site,enable,target,pages',
							'add_rule_label' => __( 'Add User Rule', 'elementor-bulk-importer' ),
						),
						$users
					);
					?>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Save meta field.
	 * Birebir header-footer-elementor'dan kopyalandı
	 *
	 * @param  POST $post_id Currennt post object which is being displayed.
	 *
	 * @return Void
	 */
	public function ehf_save_meta( $post_id ) {

		// Bail if we're doing an auto save.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// if our nonce isn't there, or we can't verify it, bail.
		if ( ! isset( $_POST['ehf_meta_nounce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ehf_meta_nounce'] ) ), 'ehf_meta_nounce' ) ) {
			return;
		}

		// if our current user can't edit this post, bail.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Save target rules - use our own class first, fallback to header-footer-elementor
		$target_rules_class = null;
		if ( class_exists( '\EBI\Lib\EBI_Target_Rules_Fields' ) ) {
			$target_rules_class = '\EBI\Lib\EBI_Target_Rules_Fields';
		} elseif ( class_exists( '\HFE\Lib\Astra_Target_Rules_Fields' ) ) {
			$target_rules_class = '\HFE\Lib\Astra_Target_Rules_Fields';
		}

		if ( $target_rules_class ) {
			$target_locations = $target_rules_class::get_format_rule_value( $_POST, 'bsf-target-rules-location' );
			$target_exclusion = $target_rules_class::get_format_rule_value( $_POST, 'bsf-target-rules-exclusion' );
			$target_users     = array();

			if ( isset( $_POST['bsf-target-rules-users'] ) ) {
				$target_users = array_map( 'sanitize_text_field', wp_unslash( $_POST['bsf-target-rules-users'] ) );
			}

			update_post_meta( $post_id, 'ehf_target_include_locations', $target_locations );
			update_post_meta( $post_id, 'ehf_target_exclude_locations', $target_exclusion );
			update_post_meta( $post_id, 'ehf_target_user_roles', $target_users );
		}

		if ( isset( $_POST['ehf_template_type'] ) ) {
			update_post_meta( $post_id, 'ehf_template_type', sanitize_text_field( wp_unslash( $_POST['ehf_template_type'] ) ) );
		}

		if ( isset( $_POST['display-on-canvas-template'] ) ) {
			update_post_meta( $post_id, 'display-on-canvas-template', sanitize_text_field( wp_unslash( $_POST['display-on-canvas-template'] ) ) );
		} else {
			delete_post_meta( $post_id, 'display-on-canvas-template' );
		}
	}

	/**
	 * Display notice when editing the header or footer when there is one more of similar layout is active on the site.
	 * Birebir header-footer-elementor'dan kopyalandı
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function location_notice() {
		global $pagenow;
		global $post;

		if ( 'post.php' != $pagenow || ! is_object( $post ) || 'elementor-hf' != $post->post_type ) {
			return;
		}

		$template_type = get_post_meta( $post->ID, 'ehf_template_type', true );

		if ( '' !== $template_type ) {
			// Get template ID function would need to be implemented or use header-footer-elementor's function
			// For now, we'll skip this notice functionality as it requires integration with header-footer-elementor
		}
	}

	/**
	 * Don't display the elementor Elementor Header & Footer Builder templates on the frontend for non edit_posts capable users.
	 * Birebir header-footer-elementor'dan kopyalandı
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function block_template_frontend() {
		if ( is_singular( 'elementor-hf' ) && ! current_user_can( 'edit_posts' ) ) {
			wp_redirect( site_url(), 301 );
			die;
		}
	}

	/**
	 * Single template function which will choose our template
	 * Birebir header-footer-elementor'dan kopyalandı
	 *
	 * @since  1.0.1
	 *
	 * @param  string $single_template Single template.
	 * @return string
	 */
	public function load_canvas_template( $single_template ) {
		global $post;

		if ( 'elementor-hf' == $post->post_type ) {
			$elementor_2_0_canvas = ELEMENTOR_PATH . '/modules/page-templates/templates/canvas.php';

			if ( file_exists( $elementor_2_0_canvas ) ) {
				return $elementor_2_0_canvas;
			} else {
				return ELEMENTOR_PATH . '/includes/page-templates/canvas.php';
			}
		}

		return $single_template;
	}

	/**
	 * Setup column filters - sadece bizim sayfamızda çalışsın
	 */
	public function setup_column_filters() {
		$screen = get_current_screen();
		
		// Sadece bizim ebi-header-footer sayfasında veya elementor-hf edit sayfasında sütunları ekle
		if ( $screen && ( 
			strpos( $screen->id, 'ebi-header-footer' ) !== false ||
			( isset( $_GET['page'] ) && 'ebi-header-footer' === $_GET['page'] )
		) ) {
			// Sütun başlıklarını ekle - priority 20 ile geç çalışsın (Ultimate Addons'tan sonra)
			add_filter( 'manage_elementor-hf_posts_columns', array( $this, 'column_headings' ), 20 );
		}
	}

	/**
	 * Display shortcode in template list column.
	 * Sadece bizim import ettiğimiz template'ler için göster
	 *
	 * @param array $column template list column.
	 * @param int   $post_id post id.
	 * @return void
	 */
	public function render_shortcode_column( $column, $post_id ) {
		// Sadece bizim import ettiğimiz template'ler için göster
		if ( 'shortcode' !== $column ) {
			return;
		}
		
		// Eğer ebi_imported değilse, boş bırak (Ultimate Addons kendi şablonlarını gösterecek)
		if ( get_post_meta( $post_id, 'ebi_imported', true ) !== '1' ) {
			return;
		}
		
		?>
		<span class="hfe-shortcode-col-wrap">
			<input type="text" onfocus="this.select();" readonly="readonly" value="[hfe_template id='<?php echo esc_attr( $post_id ); ?>']" class="hfe-large-text code">
		</span>
		<?php
	}

	/**
	 * Adds or removes list table column headings.
	 * Hem Shortcode hem Display Rules sütunlarını ekler
	 *
	 * @param array $columns Array of columns.
	 * @return array
	 */
	public function column_headings( $columns ) {
		// Eğer shortcode veya display_rules zaten varsa (Ultimate Addons tarafından eklenmiş), çıkalım
		if ( isset( $columns['shortcode'] ) || isset( $columns['elementor_hf_display_rules'] ) ) {
			return $columns;
		}
		
		$date_column = isset( $columns['date'] ) ? $columns['date'] : '';
		
		if ( isset( $columns['date'] ) ) {
			unset( $columns['date'] );
		}

		// Shortcode sütununu ekle
		$columns['shortcode'] = __( 'Shortcode', 'elementor-bulk-importer' );
		
		// Display Rules sütununu ekle
		$columns['elementor_hf_display_rules'] = __( 'Display Rules', 'elementor-bulk-importer' );
		
		if ( $date_column ) {
			$columns['date'] = $date_column;
		}

		return $columns;
	}

	/**
	 * Adds the custom list table column content.
	 * Sadece bizim import ettiğimiz template'ler için göster
	 *
	 * @since 1.2.0
	 * @param array $column Name of column.
	 * @param int   $post_id Post id.
	 * @return void
	 */
	public function column_content( $column, $post_id ) {
		// Sadece bizim import ettiğimiz template'ler için göster
		if ( 'elementor_hf_display_rules' !== $column ) {
			return;
		}
		
		// Eğer ebi_imported değilse, boş bırak (Ultimate Addons kendi display rules'ını gösterecek)
		if ( get_post_meta( $post_id, 'ebi_imported', true ) !== '1' ) {
			return;
		}

		if ( 'elementor_hf_display_rules' === $column ) {
			
			// Use our own class first, fallback to header-footer-elementor
			$target_rules_class = null;
			if ( class_exists( '\EBI\Lib\EBI_Target_Rules_Fields' ) ) {
				$target_rules_class = '\EBI\Lib\EBI_Target_Rules_Fields';
			} elseif ( class_exists( '\HFE\Lib\Astra_Target_Rules_Fields' ) ) {
				$target_rules_class = '\HFE\Lib\Astra_Target_Rules_Fields';
			}

			if ( $target_rules_class ) {
				$locations = get_post_meta( $post_id, 'ehf_target_include_locations', true );
				if ( ! empty( $locations ) ) {
					echo '<div class="ast-advanced-headers-location-wrap" style="margin-bottom: 5px;">';
					echo '<strong>Display: </strong>';
					$this->column_display_location_rules( $locations, $target_rules_class );
					echo '</div>';
				}

				$locations = get_post_meta( $post_id, 'ehf_target_exclude_locations', true );
				if ( ! empty( $locations ) ) {
					echo '<div class="ast-advanced-headers-exclusion-wrap" style="margin-bottom: 5px;">';
					echo '<strong>Exclusion: </strong>';
					$this->column_display_location_rules( $locations, $target_rules_class );
					echo '</div>';
				}

				$users = get_post_meta( $post_id, 'ehf_target_user_roles', true );
				if ( isset( $users ) && is_array( $users ) ) {
					if ( isset( $users[0] ) && ! empty( $users[0] ) ) {
						$user_label = array();
						foreach ( $users as $user ) {
							$user_label[] = $target_rules_class::get_user_by_key( $user );
						}
						echo '<div class="ast-advanced-headers-users-wrap">';
						echo '<strong>Users: </strong>';
						echo esc_html( join( ', ', $user_label ) );
						echo '</div>';
					}
				}
			}
		}
	}

	/**
	 * Get Markup of Location rules for Display rule column.
	 * Birebir header-footer-elementor'dan kopyalandı
	 *
	 * @param array  $locations Array of locations.
	 * @param string $target_rules_class Target rules class name.
	 * @return void
	 */
	public function column_display_location_rules( $locations, $target_rules_class = null ) {
		
		if ( ! $target_rules_class ) {
			// Fallback: try to find the class
			if ( class_exists( '\EBI\Lib\EBI_Target_Rules_Fields' ) ) {
				$target_rules_class = '\EBI\Lib\EBI_Target_Rules_Fields';
			} elseif ( class_exists( '\HFE\Lib\Astra_Target_Rules_Fields' ) ) {
				$target_rules_class = '\HFE\Lib\Astra_Target_Rules_Fields';
			} else {
				return;
			}
		}

		$location_label = array();
		if ( is_array( $locations ) && is_array( $locations['rule'] ) && isset( $locations['rule'] ) ) {
			$index = array_search( 'specifics', $locations['rule'] );
			if ( false !== $index && ! empty( $index ) ) {
				unset( $locations['rule'][ $index ] );
			}
		}

		if ( isset( $locations['rule'] ) && is_array( $locations['rule'] ) ) {
			foreach ( $locations['rule'] as $location ) {
				$location_label[] = $target_rules_class::get_location_by_key( $location );
			}
		}
		if ( isset( $locations['specific'] ) && is_array( $locations['specific'] ) ) {
			foreach ( $locations['specific'] as $location ) {
				$location_label[] = $target_rules_class::get_location_by_key( $location );
			}
		}

		echo esc_html( join( ', ', $location_label ) );
	}

	/**
	 * Hide admin notices on the custom settings page.
	 * Birebir header-footer-elementor'dan kopyalandı
	 *
	 * @since 2.2.1
	 * @return void
	 */
	public function hide_admin_notices() {
		$screen                = get_current_screen();
		$pages_to_hide_notices = array(
			'edit-elementor-hf',     // Edit screen for elementor-hf post type.
			'elementor-hf',          // New post screen for elementor-hf post type.
		);

		if ( in_array( $screen->id, $pages_to_hide_notices ) || 'toplevel_page_hfe' === $screen->id ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

	/**
	 * Get imported header/footer templates
	 *
	 * @return array
	 */
	private function get_imported_hf_templates() {
		$templates = get_posts(
			array(
				'post_type'      => 'elementor-hf',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		$result = array();
		foreach ( $templates as $template ) {
			$result[] = array(
				'id'    => $template->ID,
				'title' => $template->post_title,
				'date'  => get_the_date( '', $template->ID ),
			);
		}

		return $result;
	}

	/**
	 * Display rules column content helper
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function ebi_column_display_rules_content( $post_id ) {
		$this->column_content( 'elementor_hf_display_rules', $post_id );
	}

	/**
	 * Shortcode column content helper
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function ebi_column_shortcode_content( $post_id ) {
		$this->render_shortcode_column( 'shortcode', $post_id );
	}

	/**
	 * Display rules tab for modal (stub - uses display_rules_tab if header-footer-elementor exists)
	 *
	 * @return void
	 */
	private function ebi_display_rules_tab() {
		// This will be used in modal, but for now we can use the same function
		if ( class_exists( '\HFE\Lib\Astra_Target_Rules_Fields' ) ) {
			// This would need post ID context which we don't have in modal
			// For now, leave empty and handle via JS
		}
	}

	/**
	 * Add Header & Footer modal to admin footer
	 *
	 * @return void
	 */
	/**
	 * Page column headings
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function page_column_headings( $columns ) {
		// Insert custom columns before date
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			if ( 'date' === $key ) {
				$new_columns['template_kit'] = __( 'Şablon (Template Kit)', 'elementor-bulk-importer' );
				$new_columns['parent']      = __( 'Üst öge', 'elementor-bulk-importer' );
				$new_columns['template']    = __( 'Şablon', 'elementor-bulk-importer' );
			}
			$new_columns[ $key ] = $value;
		}
		return $new_columns;
	}

	/**
	 * Page column content
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Post ID.
	 */
	public function page_column_content( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'template_kit':
				$template_name = get_post_meta( $post_id, 'ebi_template_name', true );
				echo $template_name ? esc_html( $template_name ) : '—';
				break;
			case 'parent':
				$parent_id = wp_get_post_parent_id( $post_id );
				if ( $parent_id ) {
					$parent = get_post( $parent_id );
					if ( $parent ) {
						echo '<a href="' . esc_url( get_edit_post_link( $parent_id ) ) . '">' . esc_html( $parent->post_title ) . '</a>';
					}
				} else {
					echo '—';
				}
				break;
			case 'template':
				$page_template = get_post_meta( $post_id, '_wp_page_template', true );
				if ( empty( $page_template ) || 'default' === $page_template ) {
					echo esc_html__( 'Varsayılan şablon', 'elementor-bulk-importer' );
				} else {
					echo esc_html( $page_template );
				}
				break;
		}
	}

	/**
	 * Add Pages modal
	 */
	public function add_pages_modal() {
		$screen = get_current_screen();
		if ( ! $screen || 'page' !== $screen->post_type || 'edit' !== $screen->base ) {
			return;
		}
		?>
		<!-- Add New Page Modal (multi-row) for edit.php -->
		<div id="ebi-add-page-modal-edit" class="ebi-modal" style="display:none;" 
		     data-is-pro="<?php echo EBI_PRO_VERSION ? '1' : '0'; ?>">
			<div class="ebi-modal-content" style="max-width: 1200px;">
				<div class="ebi-modal-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
					<h2 style="margin:0;">&nbsp;<?php esc_html_e( 'Yeni Sayfa Ekle', 'elementor-bulk-importer' ); ?></h2>
					<div>
						<button type="button" class="button ebi-add-page-row-edit"><?php esc_html_e( 'Yeni Alan', 'elementor-bulk-importer' ); ?></button>
						<span class="ebi-modal-close" style="margin-left:8px;cursor:pointer;">&times;</span>
					</div>
				</div>
				<div class="ebi-modal-body">
					<form id="ebi-add-page-form-edit">
						<div id="ebi-page-rows-edit" class="ebi-page-rows" style="display:flex;flex-direction:column;gap:10px;"></div>
						<!-- Hidden prototypes for cloning (edit) -->
						<div id="ebi-page-hidden-prototypes-edit" style="display:none;">
							<div class="ebi-proto-parent">
								<?php
								wp_dropdown_pages( array(
									'name'             => 'ebi_proto_parent_edit',
									'id'               => 'ebi-proto-parent-edit',
									'show_option_none' => __( 'None', 'elementor-bulk-importer' ),
									'option_none_value' => '0',
									'class'            => 'regular-text',
								) );
								?>
							</div>
							<div class="ebi-proto-ptype">
								<select id="ebi-proto-template-type-edit" class="regular-text">
									<option value="default"><?php esc_html_e( 'Varsayılan şablon', 'elementor-bulk-importer' ); ?></option>
									<option value="elementor_canvas"><?php esc_html_e( 'Elementor Canvas', 'elementor-bulk-importer' ); ?></option>
									<option value="elementor_header_footer"><?php esc_html_e( 'Elementor Tam Genişlik', 'elementor-bulk-importer' ); ?></option>
									<?php
									$theme_templates = get_page_templates();
									foreach ( $theme_templates as $template_name => $template_filename ) {
										echo '<option value="' . esc_attr( $template_filename ) . '">' . esc_html( $template_name ) . ' (' . esc_html( __( 'Tema', 'elementor-bulk-importer' ) ) . ')</option>';
									}
									?>
								</select>
							</div>
						</div>
						<div class="ebi-modal-actions" style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Tümünü Ekle', 'elementor-bulk-importer' ); ?></button>
							<button type="button" class="button ebi-modal-cancel"><?php esc_html_e( 'İptal', 'elementor-bulk-importer' ); ?></button>
						</div>
					</form>
					<div id="ebi-page-messages-edit"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Set screen for inline edit to work properly
	 */
	public function set_screen_for_inline_edit( $screen ) {
		if ( strpos( $screen->id, 'ebi-settings' ) !== false ) {
			// WordPress'in inline-edit-post.js'inin page post type için çalışması için
			// Screen'i edit-page olarak ayarla
			$screen->post_type = 'page';
			$screen->id = 'edit-page';
		}
		return $screen;
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Settings kaydetme işlemi
		if ( isset( $_POST['ebi_save_settings'] ) && check_admin_referer( 'ebi_settings_nonce', 'ebi_settings_nonce' ) ) {
			$this->save_settings();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ayarlar başarıyla kaydedildi.', 'elementor-bulk-importer' ) . '</p></div>';
		}
		
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'translation';
		$saved_settings = get_option( 'ebi_translation_settings', array() );
		$default_api = isset( $saved_settings['default_api'] ) ? $saved_settings['default_api'] : 'libretranslate';
		
		?>
		<div class="wrap ebi-settings-page">
			<h1><?php esc_html_e( 'Ayarlar', 'elementor-bulk-importer' ); ?></h1>
			
		<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ebi-settings-page', 'tab' => 'translation' ), admin_url( 'admin.php' ) ) ); ?>" 
			   class="nav-tab <?php echo 'translation' === $current_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Dil Ayarları', 'elementor-bulk-importer' ); ?>
				<?php if ( ! EBI_PRO_VERSION ) : ?>
					<span class="ebi-pro-badge">PRO</span>
				<?php endif; ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ebi-settings-page', 'tab' => 'about' ), admin_url( 'admin.php' ) ) ); ?>" 
			   class="nav-tab <?php echo 'about' === $current_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'About', 'elementor-bulk-importer' ); ?>
			</a>
		</nav>
			
			<?php if ( 'about' === $current_tab ) : ?>
				<?php $this->render_about_tab(); ?>
			<?php else : ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'ebi_settings_nonce', 'ebi_settings_nonce' ); ?>
					
					<?php if ( 'translation' === $current_tab ) : ?>
						<?php $this->render_translation_settings_tab( $saved_settings, $default_api ); ?>
						<?php if ( EBI_PRO_VERSION ) : ?>
							<p class="submit">
								<input type="submit" name="ebi_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Ayarları Kaydet', 'elementor-bulk-importer' ); ?>">
							</p>
						<?php endif; ?>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render about tab
	 */
	private function render_about_tab() {
		?>
		<div class="ebi-support-content" style="max-width: 800px; margin: 20px 0;">
			<div class="ebi-support-box" style="background: #fff; padding: 30px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
				<h2 style="margin-top: 0;"><?php esc_html_e( 'About Plugin', 'elementor-bulk-importer' ); ?></h2>
				<p><strong><?php esc_html_e( 'Version:', 'elementor-bulk-importer' ); ?></strong> <?php echo esc_html( EBI_VERSION ); ?></p>
				<p><strong><?php esc_html_e( 'Author:', 'elementor-bulk-importer' ); ?></strong> <a href="https://bayramsavluk.com" target="_blank">Bayram Şavluk</a></p>
				<p><strong><?php esc_html_e( 'Telegram:', 'elementor-bulk-importer' ); ?></strong> <a href="https://t.me/bayramsavluk" target="_blank">@bayramsavluk</a></p>
				<p><strong><?php esc_html_e( 'GitHub:', 'elementor-bulk-importer' ); ?></strong> <a href="https://github.com/bayramsavluk/elementor-page-builder-assistant" target="_blank">Repository</a></p>
				<p><?php esc_html_e( 'Streamline your Elementor workflow by bulk importing template kits to pages and header/footer sections.', 'elementor-bulk-importer' ); ?></p>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Render translation settings tab
	 */
	private function render_translation_settings_tab( $saved_settings, $default_api ) {
		?>
		<?php if ( ! EBI_PRO_VERSION ) : ?>
			<!-- PRO Upgrade Box -->
			<div class="ebi-get-pro-card" style="max-width: 800px; margin: 30px 0;">
				<h3><?php esc_html_e( 'Upgrade to PRO', 'elementor-bulk-importer' ); ?></h3>
				<p><?php esc_html_e( 'Translation features are available in PRO version. Unlock powerful automatic translation with multiple APIs and language support.', 'elementor-bulk-importer' ); ?></p>
				<ul class="ebi-pro-features-list">
					<li>5 Translation APIs (MyMemory, LibreTranslate, DeepL, Microsoft, Yandex)</li>
					<li>70+ Languages Support (English, Turkish, German, French, Spanish, Italian, Portuguese, Russian, Arabic, Chinese, Japanese, Korean, Hindi, and many more)</li>
					<li>Smart Content Detection - Preserves Elementor structure while translating</li>
					<li>Translate templates during import automatically</li>
					<li>Lifetime Updates & Priority Support</li>
				</ul>
				<p style="margin-top: 20px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ebi-settings-pricing' ) ); ?>" target="_blank" class="button button-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3); padding: 10px 20px; font-size: 14px; border-radius: 8px;">
						<?php esc_html_e( 'Get PRO Version', 'elementor-bulk-importer' ); ?>
					</a>
				</p>
			</div>
		<?php else : ?>
		
		<?php
		$apis = array(
			'libretranslate' => array(
				'name' => 'LibreTranslate',
				'description' => __( 'Açık kaynak çeviri servisi. Self-hosted veya API kullanımı. 100+ dil desteği.', 'elementor-bulk-importer' ),
				'requires_key' => false,
				'key_placeholder' => __( 'Self-hosted için API URL girin (opsiyonel)', 'elementor-bulk-importer' ),
			),
			'mymemory' => array(
				'name' => 'MyMemory Translation',
				'description' => __( 'API key olmadan: Günde 100 istek (anonim). Email ile: Günde 1,000 istek. API key ile: Daha yüksek limitler. 103 dil desteği.', 'elementor-bulk-importer' ),
				'requires_key' => false,
				'key_placeholder' => __( 'API Key (opsiyonel) - Key olmadan da kullanılabilir', 'elementor-bulk-importer' ),
			),
			'deepl' => array(
				'name' => 'DeepL API',
				'description' => __( 'Yüksek kalite. Ücretsiz: 500,000 karakter/ay.', 'elementor-bulk-importer' ),
				'requires_key' => true,
				'key_placeholder' => __( 'API Key gerekli', 'elementor-bulk-importer' ),
			),
			'microsoft' => array(
				'name' => 'Microsoft Translator (Azure)',
				'description' => __( 'Azure üzerinden. Ücretsiz: 2M karakter/ay.', 'elementor-bulk-importer' ),
				'requires_key' => true,
				'key_placeholder' => __( 'Azure API Key gerekli', 'elementor-bulk-importer' ),
			),
			'yandex' => array(
				'name' => 'Yandex Translate',
				'description' => __( '10M karakter/gün ücretsiz. API key gerekli.', 'elementor-bulk-importer' ),
				'requires_key' => true,
				'key_placeholder' => __( 'API Key gerekli', 'elementor-bulk-importer' ),
			),
			'argostranslate' => array(
				'name' => 'Argos Translate',
				'description' => __( 'LibreTranslate tabanlı açık kaynak. Self-hosted.', 'elementor-bulk-importer' ),
				'requires_key' => false,
				'key_placeholder' => __( 'API URL (opsiyonel)', 'elementor-bulk-importer' ),
			),
		);
		
		?>
		<table class="form-table">
			<thead>
				<tr>
					<th width="150"><?php esc_html_e( 'API', 'elementor-bulk-importer' ); ?></th>
					<th width="100"><?php esc_html_e( 'Aktif', 'elementor-bulk-importer' ); ?></th>
					<th><?php esc_html_e( 'API Key / URL', 'elementor-bulk-importer' ); ?></th>
					<th width="120"><?php esc_html_e( 'Varsayılan', 'elementor-bulk-importer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $apis as $api_id => $api ) : 
					$is_enabled = isset( $saved_settings['apis'][ $api_id ]['enabled'] ) ? $saved_settings['apis'][ $api_id ]['enabled'] : false;
					$api_key = isset( $saved_settings['apis'][ $api_id ]['key'] ) ? $saved_settings['apis'][ $api_id ]['key'] : '';
					$is_default = ( $default_api === $api_id );
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $api['name'] ); ?></strong>
						<p class="description"><?php echo esc_html( $api['description'] ); ?></p>
					</td>
					<td>
						<input type="checkbox" 
						       name="ebi_translation_settings[apis][<?php echo esc_attr( $api_id ); ?>][enabled]" 
						       value="1" 
						       <?php checked( $is_enabled, true ); ?>
						       class="ebi-api-enabled">
					</td>
					<td>
						<?php if ( $api['requires_key'] || ! empty( $api['key_placeholder'] ) ) : ?>
							<input type="text" 
							       name="ebi_translation_settings[apis][<?php echo esc_attr( $api_id ); ?>][key]" 
							       value="<?php echo esc_attr( $api_key ); ?>" 
							       class="regular-text" 
							       placeholder="<?php echo esc_attr( $api['key_placeholder'] ); ?>"
							       <?php echo $api['requires_key'] && ! $is_enabled ? 'disabled' : ''; ?>>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'API key gerekmez', 'elementor-bulk-importer' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<input type="radio" 
						       name="ebi_translation_settings[default_api]" 
						       value="<?php echo esc_attr( $api_id ); ?>"
						       <?php checked( $is_default, true ); ?>
						       <?php echo ! $is_enabled ? 'disabled' : ''; ?>
						       class="ebi-default-api">
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<style>
			.ebi-settings-page .form-table {
				width: 100%;
				margin-top: 20px;
			}
			.ebi-settings-page .form-table thead th {
				background: #f9f9f9;
				padding: 10px;
				font-weight: 600;
			}
			.ebi-settings-page .form-table tbody td {
				padding: 15px 10px;
				vertical-align: top;
			}
			.ebi-settings-page .form-table .description {
				margin-top: 5px;
				font-size: 12px;
				color: #666;
			}
		</style>
		
		<script>
		jQuery(document).ready(function($) {
			// Aktif olmayan API'ler için varsayılan seçeneği devre dışı bırak
			$('.ebi-api-enabled').on('change', function() {
				var $row = $(this).closest('tr');
				var $defaultRadio = $row.find('.ebi-default-api');
				var $keyInput = $row.find('input[type="text"]');
				
				if ($(this).is(':checked')) {
					$defaultRadio.prop('disabled', false);
					$keyInput.prop('disabled', false);
				} else {
					$defaultRadio.prop('disabled', true);
					if ($keyInput.data('required')) {
						$keyInput.prop('disabled', true);
					}
				}
			});
			
			// Varsayılan seçildiğinde o API'yi otomatik aktif et
			$('.ebi-default-api').on('change', function() {
				if ($(this).is(':checked')) {
					var $row = $(this).closest('tr');
					$row.find('.ebi-api-enabled').prop('checked', true).trigger('change');
				}
			});
			
			// Sayfa yüklendiğinde aktif olmayan API'lerin radio butonlarını devre dışı bırak
			$('.ebi-api-enabled').each(function() {
				if (!$(this).is(':checked')) {
					$(this).closest('tr').find('.ebi-default-api').prop('disabled', true);
				}
			});
		});
		</script>
		<?php endif; // End EBI_PRO_VERSION check ?>
		<?php
	}

	/**
	 * Save settings
	 */
	private function save_settings() {
		if ( isset( $_POST['ebi_translation_settings'] ) ) {
			$settings = $_POST['ebi_translation_settings'];
			
			// Sanitize settings
			$sanitized_settings = array(
				'default_api' => isset( $settings['default_api'] ) ? sanitize_text_field( $settings['default_api'] ) : 'libretranslate',
				'apis' => array(),
			);
			
			if ( isset( $settings['apis'] ) && is_array( $settings['apis'] ) ) {
				foreach ( $settings['apis'] as $api_id => $api_data ) {
					$sanitized_settings['apis'][ sanitize_key( $api_id ) ] = array(
						'enabled' => isset( $api_data['enabled'] ) ? true : false,
						'key' => isset( $api_data['key'] ) ? sanitize_text_field( $api_data['key'] ) : '',
					);
				}
			}
			
			// Varsayılan API aktif değilse, ilk aktif API'yi varsayılan yap
			if ( isset( $sanitized_settings['apis'][ $sanitized_settings['default_api'] ]['enabled'] ) && 
			     ! $sanitized_settings['apis'][ $sanitized_settings['default_api'] ]['enabled'] ) {
				foreach ( $sanitized_settings['apis'] as $api_id => $api_data ) {
					if ( $api_data['enabled'] ) {
						$sanitized_settings['default_api'] = $api_id;
						break;
					}
				}
			}
			
			update_option( 'ebi_translation_settings', $sanitized_settings );
		}
	}

	/**
	 * AJAX handler for inline edit (Quick Edit)
	 * WordPress'in inline-edit-post.js'i için - WordPress core'un beklediği format
	 */
	public function ajax_inline_save() {
		// WordPress'in inline-edit-post.js'i için standart AJAX handler
		// Sadece page post type'ı için çalışsın
		if ( ! isset( $_POST['post_type'] ) || 'page' !== $_POST['post_type'] ) {
			// WordPress core'un handler'ına bırak
			return;
		}

		// WordPress core'un inline-save action'ını kullan
		// Bizim özel handler'ımız sadece page template'i güncellemek için
		check_ajax_referer( 'inlineeditnonce', '_inline_edit' );

		$post_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;
		if ( ! $post_id ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

		// Update page template (WordPress core post'u zaten güncelliyor)
		if ( isset( $_POST['page_template'] ) ) {
			update_post_meta( $post_id, '_wp_page_template', sanitize_text_field( $_POST['page_template'] ) );
		}

		// WordPress core'un handler'ı devam etsin
		return;
	}

	/**
	 * Filter posts query to show only our imported templates on our custom page
	 *
	 * @param WP_Screen $screen Current screen object.
	 * @return void
	 */
	public function maybe_filter_hf_posts_query( $screen ) {
		// Only filter on our custom Header & Footer page
		if ( ! $screen || strpos( $screen->id, 'ebi-header-footer' ) === false ) {
			return;
		}
		
		// Add meta query to show only our imported templates
		add_filter( 'parse_query', array( $this, 'filter_hf_posts_by_imported' ) );
	}
	
	/**
	 * Filter posts query to show only imported templates
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function filter_hf_posts_by_imported( $query ) {
		// Only filter on admin and our custom page
		if ( ! is_admin() || ! isset( $_GET['page'] ) || 'ebi-header-footer' !== $_GET['page'] ) {
			return;
		}
		
		// Only filter elementor-hf post type queries
		if ( ! isset( $query->query_vars['post_type'] ) || 'elementor-hf' !== $query->query_vars['post_type'] ) {
			return;
		}
		
		// Add meta query to show only templates imported by our plugin
		$meta_query = isset( $query->query_vars['meta_query'] ) ? $query->query_vars['meta_query'] : array();
		
		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}
		
		// Ensure we have a relation key
		if ( ! isset( $meta_query['relation'] ) && ! empty( $meta_query ) ) {
			$meta_query = array( 'relation' => 'AND' ) + $meta_query;
		}
		
		// Add our filter - only show templates imported by our plugin
		$meta_query[] = array(
			'key'     => 'ebi_imported',
			'value'   => '1',
			'compare' => '=',
		);
		
		$query->query_vars['meta_query'] = $meta_query;
		
		// Remove filter after use to avoid affecting other queries
		remove_filter( 'parse_query', array( $this, 'filter_hf_posts_by_imported' ) );
	}

	/**
	 * Hide our templates from Ultimate Addons (header-footer-elementor) edit page
	 *
	 * @param WP_Screen $screen Current screen object.
	 * @return void
	 */
	public function maybe_hide_from_ultimate_addons( $screen ) {
		// Sadece edit-elementor-hf sayfasında ve bizim sayfamız değilse
		if ( ! $screen || 'edit-elementor-hf' !== $screen->id ) {
			return;
		}
		
		// Eğer bizim ebi-header-footer sayfamız ise, filtreleme yapma
		if ( isset( $_GET['page'] ) && 'ebi-header-footer' === $_GET['page'] ) {
			return;
		}
		
		// Ultimate Addons'un kendi edit.php?post_type=elementor-hf sayfasında bizim template'leri gizle
		add_filter( 'parse_query', array( $this, 'hide_ebi_templates_from_ultimate_addons' ) );
	}
	
	/**
	 * Filter to hide EBI templates from Ultimate Addons page
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function hide_ebi_templates_from_ultimate_addons( $query ) {
		// Sadece admin ve elementor-hf post type için
		if ( ! is_admin() || ! isset( $query->query_vars['post_type'] ) || 'elementor-hf' !== $query->query_vars['post_type'] ) {
			return;
		}
		
		// Eğer bizim sayfamızsa, filtreleme yapma
		if ( isset( $_GET['page'] ) && 'ebi-header-footer' === $_GET['page'] ) {
			return;
		}
		
		// Bizim template'leri gizle - ebi_imported meta'si OLMAYAN veya '1' olmayan postları göster
		$meta_query = isset( $query->query_vars['meta_query'] ) ? $query->query_vars['meta_query'] : array();
		
		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}
		
		// Add relation if needed
		if ( ! isset( $meta_query['relation'] ) && ! empty( $meta_query ) ) {
			$meta_query = array( 'relation' => 'AND' ) + $meta_query;
		}
		
		// Hide EBI imported templates - ebi_imported olmayan veya değeri '1' olmayan
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => 'ebi_imported',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'ebi_imported',
				'value'   => '1',
				'compare' => '!=',
			),
		);
		
		$query->query_vars['meta_query'] = $meta_query;
		
		// Remove filter after use
		remove_filter( 'parse_query', array( $this, 'hide_ebi_templates_from_ultimate_addons' ) );
	}
	
	/**
	 * Disable Elementor admin top bar on our custom pages
	 *
	 * @param bool       $is_active Whether the admin top bar is active.
	 * @param WP_Screen $current_screen Current screen object.
	 * @return bool
	 */
	public function disable_elementor_admin_top_bar( $is_active, $current_screen ) {
		// Disable on our custom Header & Footer page
		if ( $current_screen && (
			strpos( $current_screen->id, 'ebi-header-footer' ) !== false ||
			strpos( $current_screen->id, 'ebi-settings' ) !== false
		) ) {
			return false;
		}
		
		// Also disable on elementor-hf post type pages when accessed via our custom page
		if ( isset( $_GET['page'] ) && 'ebi-header-footer' === $_GET['page'] ) {
			return false;
		}
		
		return $is_active;
	}

	/**
	 * Add Header & Footer modal to admin footer
	 */
	public function add_header_footer_modal() {
		global $hook_suffix;
		$screen = get_current_screen();
		
		// Show modal on both edit-elementor-hf screen and our custom ebi-header-footer page
		$is_our_page = false;
		
		if ( $screen ) {
			if ( 'edit-elementor-hf' === $screen->id || 'ebi-header-footer' === $screen->id || strpos( $screen->id, 'ebi-header-footer' ) !== false ) {
				$is_our_page = true;
			}
		}
		
		// Also check hook_suffix as fallback
		if ( ! $is_our_page && isset( $hook_suffix ) && 'ebi-header-footer' === $hook_suffix ) {
			$is_our_page = true;
		}
		
		if ( ! $is_our_page ) {
			return;
		}
		?>
		<!-- Add New HF Modal -->
		<div id="ebi-add-hf-modal" class="ebi-modal" style="display:none;">
			<div class="ebi-modal-content ebi-modal-large">
				<div class="ebi-modal-header">
					<h2><?php esc_html_e( 'Add New Header/Footer', 'elementor-bulk-importer' ); ?></h2>
					<span class="ebi-modal-close">&times;</span>
				</div>
				<div class="ebi-modal-body">
					<form id="ebi-add-hf-form">
						<div class="ebi-form-group">
							<label for="ebi-hf-title"><?php esc_html_e( 'Name', 'elementor-bulk-importer' ); ?> <span class="required">*</span></label>
							<input type="text" id="ebi-hf-title" name="hf_title" class="regular-text" required>
						</div>
						<div class="ebi-form-group">
							<label for="ebi-hf-type"><?php esc_html_e( 'Type of Template', 'elementor-bulk-importer' ); ?> <span class="required">*</span></label>
							<select id="ebi-hf-type" name="hf_type" class="regular-text" required>
								<option value=""><?php esc_html_e( 'Select...', 'elementor-bulk-importer' ); ?></option>
								<option value="header"><?php esc_html_e( 'Header', 'elementor-bulk-importer' ); ?></option>
								<option value="footer"><?php esc_html_e( 'Footer', 'elementor-bulk-importer' ); ?></option>
								<option value="before_footer"><?php esc_html_e( 'Before Footer', 'elementor-bulk-importer' ); ?></option>
								<option value="404"><?php esc_html_e( '404 Page', 'elementor-bulk-importer' ); ?></option>
							</select>
						</div>
						<div class="ebi-form-group ebi-template-select-wrapper" style="display:none;">
							<label for="ebi-hf-template"><?php esc_html_e( 'Template', 'elementor-bulk-importer' ); ?> <span class="required">*</span></label>
							<select id="ebi-hf-template" name="hf_template" class="ebi-template-select" required>
								<option value=""><?php esc_html_e( 'Select template...', 'elementor-bulk-importer' ); ?></option>
							</select>
						</div>
						
						<div class="ebi-form-group" style="margin-top: 15px;">
							<label>
								<input type="checkbox" id="ebi-hf-enable-canvas" name="hf_enable_canvas" value="1">
								<?php esc_html_e( 'Enable Layout for Elementor Canvas Template?', 'elementor-bulk-importer' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Enabling this option will display this layout on pages using Elementor Canvas Template.', 'elementor-bulk-importer' ); ?></p>
						</div>

						<?php
			// Translation options - for Header/Footer
						$translation_settings = get_option( 'ebi_translation_settings', array() );
						$enabled_apis = array();
						if ( isset( $translation_settings['apis'] ) ) {
							foreach ( $translation_settings['apis'] as $api_id => $api_data ) {
								if ( isset( $api_data['enabled'] ) && $api_data['enabled'] ) {
									$api_names = array(
										'libretranslate' => 'LibreTranslate',
										'mymemory' => 'MyMemory Translation',
										'deepl' => 'DeepL API',
										'microsoft' => 'Microsoft Translator',
										'yandex' => 'Yandex Translate',
										'argostranslate' => 'Argos Translate',
									);
									$enabled_apis[ $api_id ] = isset( $api_names[ $api_id ] ) ? $api_names[ $api_id ] : $api_id;
								}
							}
						}
						$default_api = isset( $translation_settings['default_api'] ) ? $translation_settings['default_api'] : 'libretranslate';
						?>

				<div class="ebi-form-group" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
					<?php if ( ! EBI_PRO_VERSION ) : ?>
						<div style="background: #f0f6fc; border: 1px solid #0073aa; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
							<p style="margin: 0 0 10px; color: #0073aa; font-weight: 600;">
								<span style="font-size: 18px;">🔒</span> <?php esc_html_e( 'Translation is a PRO Feature', 'elementor-bulk-importer' ); ?>
							</p>
							<p style="margin: 0 0 10px; font-size: 14px;">
									<?php esc_html_e( 'Upgrade to PRO to unlock automatic translation with 5 APIs and 70+ languages!', 'elementor-bulk-importer' ); ?>
							</p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ebi-settings-pricing' ) ); ?>" target="_blank" class="button button-primary">
								<?php esc_html_e( 'Get PRO Version', 'elementor-bulk-importer' ); ?> →
							</a>
						</div>
					<?php else : ?>
					<label style="display: block; margin-bottom: 10px;">
						<input type="checkbox" id="ebi-hf-enable-translation" name="hf_enable_translation" value="1">
						<strong><?php esc_html_e( 'Enable translation?', 'elementor-bulk-importer' ); ?></strong>
					</label>
					
					<div id="ebi-hf-translation-options" style="display: none; margin-left: 25px; margin-top: 10px;">
								<?php if ( ! empty( $enabled_apis ) ) : ?>
									<label for="ebi-hf-translation-api"><?php esc_html_e( 'Translation API:', 'elementor-bulk-importer' ); ?></label>
									<select id="ebi-hf-translation-api" name="hf_translation_api" class="regular-text">
										<?php foreach ( $enabled_apis as $api_id => $api_name ) : ?>
											<option value="<?php echo esc_attr( $api_id ); ?>" <?php selected( $api_id, $default_api ); ?>>
												<?php echo esc_html( $api_name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<p class="description" style="color: #d63638; margin-bottom: 10px;">
										<strong><?php esc_html_e( 'Warning:', 'elementor-bulk-importer' ); ?></strong> 
										<?php esc_html_e( 'To use translation feature, you must first', 'elementor-bulk-importer' ); ?> 
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=ebi-settings-page&tab=translation' ) ); ?>" target="_blank">
											<?php esc_html_e( 'enable at least one translation API from the Settings page.', 'elementor-bulk-importer' ); ?>
										</a>
									</p>
									<input type="hidden" id="ebi-hf-translation-api" name="hf_translation_api" value="">
								<?php endif; ?>
								
								<label for="ebi-hf-translation-target-lang" style="display: block; margin-top: 10px;">
									<?php esc_html_e( 'Target language:', 'elementor-bulk-importer' ); ?>
								</label>
								<select id="ebi-hf-translation-target-lang" name="hf_translation_target_lang" class="regular-text" <?php echo empty( $enabled_apis ) ? 'disabled' : ''; ?>>
									<option value="en">English</option>
									<option value="tr">Türkçe (Turkish)</option>
									<option value="de">Deutsch (German)</option>
									<option value="fr">Français (French)</option>
									<option value="es">Español (Spanish)</option>
									<option value="it">Italiano (Italian)</option>
									<option value="pt">Português (Portuguese)</option>
									<option value="ru">Русский (Russian)</option>
									<option value="ar">العربية (Arabic)</option>
									<option value="zh">中文 (Chinese)</option>
									<option value="ja">日本語 (Japanese)</option>
									<option value="ko">한국어 (Korean)</option>
									<option value="hi">हिन्दी (Hindi)</option>
									<option value="nl">Nederlands (Dutch)</option>
									<option value="pl">Polski (Polish)</option>
									<option value="sv">Svenska (Swedish)</option>
									<option value="no">Norsk (Norwegian)</option>
									<option value="da">Dansk (Danish)</option>
									<option value="fi">Suomi (Finnish)</option>
									<option value="el">Ελληνικά (Greek)</option>
									<option value="cs">Čeština (Czech)</option>
									<option value="ro">Română (Romanian)</option>
									<option value="hu">Magyar (Hungarian)</option>
									<option value="uk">Українська (Ukrainian)</option>
									<option value="bg">Български (Bulgarian)</option>
									<option value="hr">Hrvatski (Croatian)</option>
									<option value="sk">Slovenčina (Slovak)</option>
									<option value="sl">Slovenščina (Slovenian)</option>
									<option value="sr">Српски (Serbian)</option>
									<option value="th">ไทย (Thai)</option>
									<option value="vi">Tiếng Việt (Vietnamese)</option>
									<option value="id">Bahasa Indonesia (Indonesian)</option>
									<option value="ms">Bahasa Melayu (Malay)</option>
									<option value="fa">فارسی (Persian)</option>
									<option value="he">עברית (Hebrew)</option>
									<option value="ur">اردو (Urdu)</option>
									<option value="bn">বাংলা (Bengali)</option>
									<option value="ta">தமிழ் (Tamil)</option>
									<option value="te">తెలుగు (Telugu)</option>
									<option value="mr">मराठी (Marathi)</option>
									<option value="pa">ਪੰਜਾਬੀ (Punjabi)</option>
									<option value="gu">ગુજરાતી (Gujarati)</option>
									<option value="kn">ಕನ್ನಡ (Kannada)</option>
									<option value="ml">മലയാളം (Malayalam)</option>
									<option value="si">සිංහල (Sinhala)</option>
									<option value="km">ខ្មែរ (Khmer)</option>
									<option value="lo">ລາວ (Lao)</option>
									<option value="my">မြန်မာ (Burmese)</option>
									<option value="ka">ქართული (Georgian)</option>
									<option value="hy">Հայերեն (Armenian)</option>
									<option value="az">Azərbaycan (Azerbaijani)</option>
									<option value="kk">Қазақ (Kazakh)</option>
									<option value="uz">Oʻzbek (Uzbek)</option>
									<option value="et">Eesti (Estonian)</option>
									<option value="lv">Latviešu (Latvian)</option>
									<option value="lt">Lietuvių (Lithuanian)</option>
									<option value="is">Íslenska (Icelandic)</option>
									<option value="ga">Gaeilge (Irish)</option>
									<option value="sq">Shqip (Albanian)</option>
									<option value="mk">Македонски (Macedonian)</option>
									<option value="bs">Bosanski (Bosnian)</option>
									<option value="mt">Malti (Maltese)</option>
									<option value="cy">Cymraeg (Welsh)</option>
									<option value="eu">Euskara (Basque)</option>
									<option value="ca">Català (Catalan)</option>
									<option value="gl">Galego (Galician)</option>
									<option value="af">Afrikaans</option>
									<option value="sw">Kiswahili (Swahili)</option>
									<option value="am">አማርኛ (Amharic)</option>
									<option value="ne">नेपाली (Nepali)</option>
								</select>
					</div>
					<?php endif; ?>
				</div>

					<div class="ebi-modal-actions" style="margin-top: 20px;">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'elementor-bulk-importer' ); ?></button>
							<button type="button" class="button ebi-modal-cancel"><?php esc_html_e( 'Cancel', 'elementor-bulk-importer' ); ?></button>
						</div>
					</form>
					<div id="ebi-hf-messages"></div>
				</div>
			</div>
		</div>
		<?php
	}
}

