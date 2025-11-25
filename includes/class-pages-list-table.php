<?php
/**
 * Custom Pages List Table
 *
 * @package Elementor_Bulk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Load WordPress list table class
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom Pages List Table Class
 */
class EBI_Pages_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'sayfa',
				'plural'   => 'sayfalar',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'           => '<input type="checkbox" />',
			'title'        => __( 'Sayfa Adı', 'ea-page-builder-assistant' ),
			'template_kit' => __( 'Şablon (Template Kit)', 'ea-page-builder-assistant' ),
			'parent'       => __( 'Üst öge', 'ea-page-builder-assistant' ),
			'template'     => __( 'Şablon', 'ea-page-builder-assistant' ),
			'date'         => __( 'Tarih', 'ea-page-builder-assistant' ),
		);

		return $columns;
	}

	/**
	 * Get sortable columns
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
			'date'  => array( 'date', true ),
		);
	}

	/**
	 * Column default
	 *
	 * @param object $item        Item.
	 * @param string $column_name Column name.
	 * @return mixed
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'template_kit':
				$template_name = get_post_meta( $item->ID, 'ebi_template_name', true );
				return $template_name ? esc_html( $template_name ) : '—';
			case 'parent':
				$parent_id = wp_get_post_parent_id( $item->ID );
				if ( $parent_id ) {
					$parent = get_post( $parent_id );
					if ( $parent ) {
						return '<a href="' . esc_url( get_edit_post_link( $parent_id ) ) . '">' . esc_html( $parent->post_title ) . '</a>';
					}
				}
				return '—';
			case 'template':
				$page_template = get_post_meta( $item->ID, '_wp_page_template', true );
				if ( empty( $page_template ) || 'default' === $page_template ) {
					return __( 'Varsayılan şablon', 'ea-page-builder-assistant' );
				}
				return esc_html( $page_template );
			case 'date':
				$post_timestamp = strtotime( $item->post_date );
				$time_ago = sprintf(
					/* translators: %s: human-readable time difference */
					__( '%s önce', 'ea-page-builder-assistant' ),
					human_time_diff( $post_timestamp, current_time( 'timestamp' ) )
				);
				$full_date = mysql2date( __( 'Y/m/d g:i:s a', 'ea-page-builder-assistant' ), $item->post_date );
				// Status yazısını kaldırıyoruz - sadece tarih gösterilecek
				return '<abbr title="' . esc_attr( $full_date ) . '">' . esc_html( $time_ago ) . '</abbr>';
			default:
				return '—';
		}
	}

	/**
	 * Column checkbox
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="page[]" value="%s" />',
			$item->ID
		);
	}

	/**
	 * Column title
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_title( $item ) {
		$edit_link = get_edit_post_link( $item->ID );
		$title     = _draft_or_post_title( $item->ID );

		$actions = array();
		
		$actions['edit'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $edit_link ),
			esc_attr( sprintf( __( '“%s” düzenle', 'ea-page-builder-assistant' ), $title ) ),
			__( 'Düzenle', 'ea-page-builder-assistant' )
		);

		// Elementor ile düzenle
		if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) ) {
			$elementor_edit_link = '';
			try {
				$document = \Elementor\Plugin::$instance->documents->get( $item->ID, false );
				if ( $document && $document->is_built_with_elementor() ) {
					$elementor_edit_link = $document->get_edit_url();
				} else {
					// Elementor ile düzenle linkini oluştur
					$elementor_edit_link = admin_url( 'post.php?post=' . $item->ID . '&action=elementor' );
				}
				
				if ( $elementor_edit_link ) {
					$actions['elementor'] = sprintf(
						'<a href="%s" aria-label="%s">%s</a>',
						esc_url( $elementor_edit_link ),
						esc_attr( sprintf( __( '“%s” Elementor ile düzenle', 'ea-page-builder-assistant' ), $title ) ),
						__( 'Elementor ile düzenle', 'ea-page-builder-assistant' )
					);
				}
			} catch ( Exception $e ) {
				// Elementor edit link oluşturulamadı, sessizce devam et
			}
		}

		$post_type_object = get_post_type_object( $item->post_type );
		
		// Trash durumunda farklı action'lar
		if ( 'trash' === $item->post_status ) {
			if ( current_user_can( 'delete_post', $item->ID ) ) {
				$actions['untrash'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					esc_url( wp_nonce_url( admin_url( sprintf( 'post.php?post=%d&action=untrash', $item->ID ) ), 'untrash-post_' . $item->ID ) ),
					esc_attr( sprintf( __( '"%s" geri yükle', 'ea-page-builder-assistant' ), $title ) ),
					__( 'Geri Yükle', 'ea-page-builder-assistant' )
				);
				$actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					esc_url( get_delete_post_link( $item->ID, '', true ) ),
					esc_attr( sprintf( __( '"%s" kalıcı olarak sil', 'ea-page-builder-assistant' ), $title ) ),
					__( 'Kalıcı olarak sil', 'ea-page-builder-assistant' )
				);
			}
		} else {
			// Normal durumda action'lar
			if ( current_user_can( 'edit_post', $item->ID ) ) {
				$actions['inline hide-if-no-js'] = sprintf(
					'<button type="button" class="button-link editinline" aria-label="%s" aria-expanded="false">%s</button>',
					esc_attr( sprintf( __( '"%s" (Quick Edit)', 'ea-page-builder-assistant' ), $title ) ),
					__( 'Hızlı Düzenle', 'ea-page-builder-assistant' )
				);
			}

			if ( current_user_can( 'delete_post', $item->ID ) ) {
				$actions['trash'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					esc_url( get_delete_post_link( $item->ID ) ),
					esc_attr( sprintf( __( 'Move "%s" to trash', 'ea-page-builder-assistant' ), $title ) ),
					__( 'Trash', 'ea-page-builder-assistant' )
				);
			}
		}

		if ( is_post_type_viewable( $post_type_object ) && 'trash' !== $item->post_status ) {
			$actions['view'] = sprintf(
				'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
				esc_url( get_permalink( $item->ID ) ),
				esc_attr( sprintf( __( 'View "%s"', 'ea-page-builder-assistant' ), $title ) ),
				__( 'View', 'ea-page-builder-assistant' )
			);
		}

		// Status yazısını kaldırıyoruz - sadece başlık ve actions gösterilecek
		// WordPress'in inline-edit-post.js'inin değerleri okuması için gerekli format
		$post = get_post( $item->ID );
		$post_status = $post->post_status;
		$post_date = $post->post_date;
		$post_author = $post->post_author;
		$post_parent = $post->post_parent;
		$page_template = get_post_meta( $item->ID, '_wp_page_template', true );
		$menu_order = $post->menu_order;
		
		$output = sprintf(
			'<div class="locked-info"><span class="locked-avatar"></span> <span class="locked-text"></span></div>' .
			'<strong><a class="row-title" href="%s" aria-label="%s">%s</a></strong>',
			esc_url( $edit_link ),
			esc_attr( sprintf( __( 'Edit "%s"', 'ea-page-builder-assistant' ), $title ) ),
			esc_html( $title )
		);

		$output .= $this->row_actions( $actions );
		
		// WordPress'in inline-edit-post.js'inin değerleri okuması için gerekli hidden div
		// Format: WordPress'in WP_Posts_List_Table::column_title() metodundaki gibi
		$output .= sprintf(
			'<div class="hidden" id="inline_%1$d">
				<div class="post_title">%2$s</div>
				<div class="post_name">%3$s</div>
				<div class="post_author">%4$s</div>
				<div class="comment_status">%5$s</div>
				<div class="ping_status">%6$s</div>
				<div class="_status">%7$s</div>
				<div class="jj">%8$s</div>
				<div class="mm">%9$s</div>
				<div class="aa">%10$s</div>
				<div class="hh">%11$s</div>
				<div class="mn">%12$s</div>
				<div class="ss">%13$s</div>
				<div class="post_parent">%14$s</div>
				<div class="page_template">%15$s</div>
				<div class="menu_order">%16$s</div>
			</div>',
			$item->ID,
			esc_html( $title ),
			esc_html( $post->post_name ),
			esc_attr( $post_author ),
			esc_attr( $post->comment_status ),
			esc_attr( $post->ping_status ),
			esc_attr( $post_status ),
			esc_attr( mysql2date( 'd', $post_date, false ) ),
			esc_attr( mysql2date( 'm', $post_date, false ) ),
			esc_attr( mysql2date( 'Y', $post_date, false ) ),
			esc_attr( mysql2date( 'H', $post_date, false ) ),
			esc_attr( mysql2date( 'i', $post_date, false ) ),
			esc_attr( mysql2date( 's', $post_date, false ) ),
			esc_attr( $post_parent ),
			esc_attr( $page_template ? $page_template : 'default' ),
			esc_attr( $menu_order )
		);

		return $output;
	}

	/**
	 * Get bulk actions
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array();
		$post_status = isset( $_GET['post_status'] ) ? sanitize_text_field( $_GET['post_status'] ) : '';

	if ( 'trash' === $post_status ) {
			// In trash: Restore and Delete Permanently
			if ( current_user_can( 'delete_posts' ) ) {
				$actions['untrash'] = __( 'Restore', 'ea-page-builder-assistant' );
				$actions['delete'] = __( 'Delete Permanently', 'ea-page-builder-assistant' );
			}
		} else {
			// Normal: only Move to Trash
			if ( current_user_can( 'delete_posts' ) ) {
				$actions['trash'] = __( 'Move to Trash', 'ea-page-builder-assistant' );
			}
		}

		return $actions;
	}

	/**
	 * Process bulk action
	 */
	public function process_bulk_action() {
		// Handle bulk actions - check for action2 as well (fallback button)
		$action = '';
		if ( isset( $_POST['action'] ) && -1 != $_POST['action'] ) {
			$action = sanitize_text_field( $_POST['action'] );
		} elseif ( isset( $_POST['action2'] ) && -1 != $_POST['action2'] ) {
			$action = sanitize_text_field( $_POST['action2'] );
		}

		if ( ! empty( $action ) && isset( $_POST['page'] ) && is_array( $_POST['page'] ) ) {
			check_admin_referer( 'bulk-sayfalar' );
			$pages = array_map( 'intval', $_POST['page'] );
			
			// Base redirect URL
			$redirect_url = admin_url( 'admin.php' );
			$redirect_url = add_query_arg( 'page', 'ebi-settings', $redirect_url );
			
			// Preserve post_status if set
			if ( isset( $_GET['post_status'] ) && ! empty( $_GET['post_status'] ) ) {
				$redirect_url = add_query_arg( 'post_status', sanitize_text_field( $_GET['post_status'] ), $redirect_url );
			}

			switch ( $action ) {
				case 'trash':
					if ( current_user_can( 'delete_posts' ) ) {
						$trashed = 0;
						foreach ( $pages as $page_id ) {
							if ( wp_trash_post( $page_id ) ) {
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
						foreach ( $pages as $page_id ) {
							if ( wp_untrash_post( $page_id ) ) {
								$untrashed++;
							}
						}
					if ( $untrashed > 0 ) {
							$redirect_url = add_query_arg( 'untrashed', $untrashed, $redirect_url );
							// Return to normal list after restore
							$redirect_url = remove_query_arg( 'post_status', $redirect_url );
						}
					}
					break;

				case 'delete':
					if ( current_user_can( 'delete_posts' ) ) {
						$deleted = 0;
						foreach ( $pages as $page_id ) {
							if ( wp_delete_post( $page_id, true ) ) {
								$deleted++;
							}
						}
					if ( $deleted > 0 ) {
							$redirect_url = add_query_arg( 'deleted', $deleted, $redirect_url );
							// Exit trash list after permanent delete
							$redirect_url = remove_query_arg( 'post_status', $redirect_url );
						}
					}
					break;
			}

			wp_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Prepare items
	 */
	public function prepare_items() {
		// Process bulk action first
		$this->process_bulk_action();

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Get pages
		$per_page     = $this->get_items_per_page( 'edit_pages_per_page', 20 );
		$current_page = $this->get_pagenum();

		$args = array(
			'post_type'      => 'page',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'date',
			'order'          => isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC',
		);

		// Handle post status - trash dahil
		if ( isset( $_GET['post_status'] ) && 'all' !== $_GET['post_status'] ) {
			$args['post_status'] = sanitize_text_field( $_GET['post_status'] );
		} else {
			$args['post_status'] = 'any';
		}
		
		// Trash status'ünü de dahil et
		if ( isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status'] ) {
			$args['post_status'] = 'trash';
		}

		// Handle search
		if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
			$args['s'] = sanitize_text_field( $_GET['s'] );
		}

		// Handle month filter
		if ( isset( $_GET['m'] ) && ! empty( $_GET['m'] ) && 0 !== (int) $_GET['m'] ) {
			$m = absint( $_GET['m'] );
			$args['year'] = intval( substr( (string) $m, 0, 4 ) );
			$args['monthnum'] = intval( substr( (string) $m, 4, 2 ) );
		}

		$query = new WP_Query( $args );

		$this->items = $query->posts;

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => $query->max_num_pages,
			)
		);
	}

	/**
	 * Display tablenav
	 *
	 * @param string $which Which tablenav.
	 */
	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-' . $this->_args['plural'], '_wpnonce', false );
		}
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<?php if ( $this->has_items() ) : ?>
				<div class="alignleft actions bulkactions">
					<?php $this->bulk_actions( $which ); ?>
				</div>
			<?php endif; ?>
			<?php
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>
			<br class="clear" />
		</div>
		<?php
	}

	/**
	 * Override display to use WordPress standard format
	 * Form dışarıda olduğu için sadece table kısmını render ediyoruz
	 * Search box render_pages_page() metodunda yapılıyor, burada tekrar çağırmayalım
	 */
	public function display() {
		$this->display_tablenav( 'top' );
		?>
		<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
			<thead>
				<tr>
					<?php $this->print_column_headers(); ?>
				</tr>
			</thead>

			<tbody id="the-list"<?php
			if ( $this->is_singular ) {
				echo " data-wp-lists='list:" . esc_attr( $this->_args['singular'] ) . "'";
			}
			?>>
				<?php $this->display_rows_or_placeholder(); ?>
			</tbody>

			<tfoot>
				<tr>
					<?php $this->print_column_headers( false ); ?>
				</tr>
			</tfoot>
		</table>
		<?php
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Extra tablenav
	 *
	 * @param string $which Which tablenav.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php
			// Filter by date dropdown (simplified)
			$months = $this->get_months_dropdown();
			if ( ! empty( $months ) ) {
				?>
				<select name="m">
					<option value="0"><?php esc_html_e( 'Tüm tarihler', 'ea-page-builder-assistant' ); ?></option>
					<?php echo $months; ?>
				</select>
				<input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php esc_attr_e( 'Süz', 'ea-page-builder-assistant' ); ?>">
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Get months dropdown for filtering
	 *
	 * @return string
	 */
	private function get_months_dropdown() {
		global $wpdb;

		$months = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
				FROM $wpdb->posts
				WHERE post_type = %s
				ORDER BY post_date DESC",
				'page'
			)
		);

		if ( empty( $months ) ) {
			return '';
		}

		$output = '';
		$current_m = isset( $_GET['m'] ) ? (int) $_GET['m'] : 0;

		foreach ( $months as $arc_row ) {
			$month_value = $arc_row->year . zeroise( $arc_row->month, 2 );
			$month_label = sprintf(
				/* translators: 1: month name, 2: 4-digit year */
				__( '%1$s %2$d', 'ea-page-builder-assistant' ),
				$GLOBALS['wp_locale']->get_month( $arc_row->month ),
				$arc_row->year
			);
			$selected = selected( $current_m, $month_value, false );
			$output .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $month_value ), $selected, esc_html( $month_label ) );
		}

		return $output;
	}

	/**
	 * No items message
	 */
	public function no_items() {
		esc_html_e( 'Henüz sayfa eklenmemiş.', 'ea-page-builder-assistant' );
	}

	/**
	 * Display rows
	 */
	public function display_rows() {
		foreach ( $this->items as $item ) {
			$this->single_row( $item );
		}
	}

	/**
	 * Single row output - WordPress formatına uygun hale getiriyoruz
	 * WordPress'in inline-edit-post.js script'i için gerekli format
	 * Header & Footer sayfasındaki birebir aynı format
	 *
	 * @param object $item Item.
	 */
	public function single_row( $item ) {
		$user = get_userdata( get_current_user_id() );
		$author = ( $item->post_author == $user->ID ) ? 'self' : 'other';
		$classes = 'iedit author-' . $author . ' level-0 post-' . $item->ID . ' type-page status-' . $item->post_status . ' format-standard hentry';
		
		// WordPress'in inline-edit-post.js'inin beklediği data attribute'ları
		$data_attributes = sprintf(
			'data-post-id="%d" data-post-type="page" data-post-status="%s"',
			$item->ID,
			esc_attr( $item->post_status )
		);
		?>
		<tr id="post-<?php echo esc_attr( $item->ID ); ?>" class="<?php echo esc_attr( $classes ); ?>" <?php echo $data_attributes; ?>>
			<?php $this->single_row_columns( $item ); ?>
		</tr>
		<?php
	}
}

