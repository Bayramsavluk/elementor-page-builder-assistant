<?php
/**
 * Plugin Name: Elementor Page Builder Assistant
 * Plugin URI: https://github.com/bayramsavluk/elementor-page-builder-assistant
 * Description: Streamline your Elementor workflow by bulk importing template kits to pages and header/footer sections with built-in translation support.
 * Version: 1.0.0
 * Author: bayramsavluk
 * Author URI: https://t.me/bayramsavluk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elementor-bulk-importer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.0
 * Elementor tested up to: 3.20.0
 * Donate link: https://www.patreon.com/bayramsavluk
 *
 * @package Elementor_Bulk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'EBI_VERSION', '1.0.0' );
define( 'EBI_FILE', __FILE__ );
define( 'EBI_DIR', plugin_dir_path( __FILE__ ) );
define( 'EBI_URL', plugin_dir_url( __FILE__ ) );
define( 'EBI_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Freemius Integration
 */
if ( ! function_exists( 'ebi_fs' ) ) {
    // Create a helper function for easy SDK access.
    function ebi_fs() {
        global $ebi_fs;

        if ( ! isset( $ebi_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';

            $ebi_fs = fs_dynamic_init( array(
                'id'                  => '21947',
                'slug'                => 'elementor-bulk-importer',
                'type'                => 'plugin',
                'public_key'          => 'pk_4af6c1f55d8a937a9d2d4ae2c185b',
                'is_premium'          => false,
                'premium_suffix'      => 'PRO',
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'anonymous_mode'      => true,
                'menu'                => array(
                    'slug'           => 'ebi-settings',
                    'first-path'     => 'admin.php?page=ebi-settings',
                    'support'        => false,
                ),
            ) );
        }

        return $ebi_fs;
    }

    // Init Freemius.
    ebi_fs();
    // Signal that SDK was initiated.
    do_action( 'ebi_fs_loaded' );
}

/**
 * Main plugin class
 */
class Elementor_Bulk_Importer {

	/**
	 * Instance
	 *
	 * @var Elementor_Bulk_Importer
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Elementor_Bulk_Importer
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
		$this->init();
	}

	/**
	 * Initialize plugin
	 */
	private function init() {
		// Check if Elementor is installed and activated
		add_action( 'admin_init', array( $this, 'check_dependencies' ) );

		// Add settings link on plugins page
		add_filter( 'plugin_action_links_' . EBI_BASENAME, array( $this, 'add_settings_link' ) );

		// Load Target Rules library (bağımsız)
		require_once EBI_DIR . 'includes/lib/target-rule/class-ebi-target-rules-fields.php';
		
		// Define PRO version constant using Freemius
		if ( ! defined( 'EBI_PRO_VERSION' ) ) {
			define( 'EBI_PRO_VERSION', ebi_fs()->can_use_premium_code() );
		}
		
		// Load PRO helper
		require_once EBI_DIR . 'includes/ebi-pro-helper.php';

		// Load plugin files
		require_once EBI_DIR . 'includes/class-admin.php';
		require_once EBI_DIR . 'includes/class-api.php';
		require_once EBI_DIR . 'includes/class-importer.php';
		require_once EBI_DIR . 'includes/class-frontend.php';
		require_once EBI_DIR . 'includes/class-translator.php';

		// Initialize components
		EBI_Admin::get_instance();
		EBI_API::get_instance();
		EBI_Importer::get_instance();
		EBI_Frontend::get_instance();
	}

	/**
	 * Check if Elementor is installed
	 */
	public function check_dependencies() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', array( $this, 'elementor_missing_notice' ) );
			return;
		}
	}

	/**
	 * Admin notice for missing Elementor
	 */
	public function elementor_missing_notice() {
		$message = sprintf(
			esc_html__( 'Elementor Page Builder Assistant requires %s to be installed and activated.', 'elementor-bulk-importer' ),
			'<strong>' . esc_html__( 'Elementor', 'elementor-bulk-importer' ) . '</strong>'
		);
		printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', $message );
	}
	
	/**
	 * Add settings link on plugins page
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=ebi-settings' ) ) . '">' . esc_html__( 'Settings', 'elementor-bulk-importer' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}

/**
 * Initialize plugin
 */
function ebi_init() {
	Elementor_Bulk_Importer::get_instance();
}

add_action( 'plugins_loaded', 'ebi_init' );

