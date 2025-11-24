<?php
/**
 * License Validation Class
 *
 * @package Elementor_Bulk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if PRO license is valid
 *
 * @return bool
 */
function ebi_check_license() {
	// Safety check - return false if WordPress functions not loaded yet
	if ( ! function_exists( 'get_option' ) ) {
		return false;
	}
	
	$license_key = get_option( 'ebi_license_key', '' );
	
	if ( empty( $license_key ) ) {
		return false;
	}
	
	// Check if license is already validated (cached for 24 hours)
	$cached_status = get_transient( 'ebi_license_status' );
	if ( false !== $cached_status ) {
		return (bool) $cached_status;
	}
	
	// Validate license with remote server
	$is_valid = ebi_validate_license_remote( $license_key );
	
	// Cache the result for 24 hours (86400 seconds)
	set_transient( 'ebi_license_status', $is_valid ? 1 : 0, 86400 );
	
	return $is_valid;
}

/**
 * Validate license with remote server
 *
 * @param string $license_key License key.
 * @return bool
 */
function ebi_validate_license_remote( $license_key ) {
	// License validation server URL
	$api_url = 'https://license.strikesnake.com/api/validate.php';
	
	$site_url = get_site_url();
	$domain = parse_url( $site_url, PHP_URL_HOST );
	
	$response = wp_remote_post( $api_url, array(
		'timeout' => 15,
		'body' => array(
			'license_key' => $license_key,
			'domain' => $domain,
			'product' => 'elementor-page-builder-assistant',
		),
	) );
	
	if ( is_wp_error( $response ) ) {
		// Network error - allow grace period
		return ebi_check_grace_period();
	}
	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	
	if ( isset( $data['valid'] ) && $data['valid'] === true ) {
		// Update last check time
		update_option( 'ebi_license_last_check', time() );
		return true;
	}
	
	return false;
}

/**
 * Check if we're in grace period (7 days after last successful check)
 *
 * @return bool
 */
function ebi_check_grace_period() {
	$last_check = get_option( 'ebi_license_last_check', 0 );
	
	if ( empty( $last_check ) ) {
		return false;
	}
	
	// Grace period: 7 days (604800 seconds)
	$grace_period = 604800;
	
	return ( time() - $last_check ) < $grace_period;
}

/**
 * Generate license key hash for validation
 *
 * @param string $license_key License key.
 * @param string $domain Domain.
 * @return string
 */
function ebi_generate_license_hash( $license_key, $domain ) {
	$salt = 'ebi_secure_salt_2025'; // Change this to your own salt
	return hash( 'sha256', $license_key . $domain . $salt );
}

/**
 * Deactivate license
 *
 * @return bool
 */
function ebi_deactivate_license() {
	$license_key = get_option( 'ebi_license_key', '' );
	
	if ( empty( $license_key ) ) {
		return false;
	}
	
	// Notify server about deactivation
	$api_url = 'https://license.strikesnake.com/api/deactivate.php';
	$site_url = get_site_url();
	$domain = parse_url( $site_url, PHP_URL_HOST );
	
	wp_remote_post( $api_url, array(
		'timeout' => 10,
		'body' => array(
			'license_key' => $license_key,
			'domain' => $domain,
		),
	) );
	
	// Clear local data
	delete_option( 'ebi_license_key' );
	delete_option( 'ebi_license_last_check' );
	delete_transient( 'ebi_license_status' );
	
	return true;
}

/**
 * License Admin Page
 */
class EBI_License_Admin {
	
	/**
	 * Instance
	 */
	private static $instance = null;
	
	/**
	 * Get instance
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
		add_action( 'admin_init', array( $this, 'handle_license_form' ) );
	}
	
	/**
	 * Handle license activation/deactivation
	 */
	public function handle_license_form() {
		// Activate license
		if ( isset( $_POST['ebi_activate_license'] ) && check_admin_referer( 'ebi_license_nonce', 'ebi_license_nonce' ) ) {
			$license_key = isset( $_POST['ebi_license_key'] ) ? sanitize_text_field( $_POST['ebi_license_key'] ) : '';
			
			if ( ! empty( $license_key ) ) {
				update_option( 'ebi_license_key', $license_key );
				delete_transient( 'ebi_license_status' );
				
				// Force check
				$is_valid = ebi_check_license();
				
				if ( $is_valid ) {
					add_settings_error( 'ebi_license', 'license_activated', __( 'License activated successfully!', 'elementor-bulk-importer' ), 'success' );
					set_transient( 'ebi_license_message', array( 'type' => 'success', 'message' => __( 'License activated successfully!', 'elementor-bulk-importer' ) ), 30 );
				} else {
					delete_option( 'ebi_license_key' );
					add_settings_error( 'ebi_license', 'license_invalid', __( 'Invalid license key. Please check and try again.', 'elementor-bulk-importer' ), 'error' );
					set_transient( 'ebi_license_message', array( 'type' => 'error', 'message' => __( 'Invalid license key. Please check and try again.', 'elementor-bulk-importer' ) ), 30 );
				}
				
				// Redirect to refresh page
				wp_safe_redirect( add_query_arg( array( 'page' => 'ebi-settings-page', 'tab' => 'license' ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}
		
		// Deactivate license
		if ( isset( $_POST['ebi_deactivate_license'] ) && check_admin_referer( 'ebi_license_nonce', 'ebi_license_nonce' ) ) {
			ebi_deactivate_license();
			add_settings_error( 'ebi_license', 'license_deactivated', __( 'License deactivated successfully.', 'elementor-bulk-importer' ), 'success' );
			set_transient( 'ebi_license_message', array( 'type' => 'success', 'message' => __( 'License deactivated successfully.', 'elementor-bulk-importer' ) ), 30 );
			
			// Redirect to refresh page
			wp_safe_redirect( add_query_arg( array( 'page' => 'ebi-settings-page', 'tab' => 'license' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}
	
	/**
	 * Render license settings tab
	 */
	public static function render_license_tab() {
		$license_key = get_option( 'ebi_license_key', '' );
		$is_active = EBI_PRO_VERSION;
		
		// Check for transient message
		$message = get_transient( 'ebi_license_message' );
		if ( $message ) {
			delete_transient( 'ebi_license_message' );
			echo '<div class="notice notice-' . esc_attr( $message['type'] ) . ' is-dismissible"><p>' . esc_html( $message['message'] ) . '</p></div>';
		}
		
		settings_errors( 'ebi_license' );
		
		?>
		<div class="ebi-license-container">
			<?php if ( $is_active ) : ?>
				<!-- Active License -->
				<div class="ebi-license-card ebi-license-active-card">
					<div class="ebi-license-header">
						<span class="ebi-license-icon" style="color: #46b450; font-size: 28px;">✓</span>
						<h2><?php esc_html_e( 'PRO Version Active', 'elementor-bulk-importer' ); ?></h2>
					</div>
					
					<div class="ebi-license-info">
						<div class="ebi-license-key-display">
							<strong><?php esc_html_e( 'License Key:', 'elementor-bulk-importer' ); ?></strong>
							<code><?php echo esc_html( substr( $license_key, 0, 10 ) . '•••••••••' . substr( $license_key, -5 ) ); ?></code>
						</div>
						<p class="ebi-license-message">
							<?php esc_html_e( 'All PRO features are now available!', 'elementor-bulk-importer' ); ?>
						</p>
					</div>
					
					<form method="post" style="margin-top: 20px;">
						<?php wp_nonce_field( 'ebi_license_nonce', 'ebi_license_nonce' ); ?>
						<button type="submit" name="ebi_deactivate_license" class="button" style="color: #dc3545; border-color: #dc3545; padding: 8px 16px; border-radius: 8px;" 
						        onmouseover="this.style.background='#dc3545'; this.style.color='#fff';" 
						        onmouseout="this.style.background='#fff'; this.style.color='#dc3545';" 
						        onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to deactivate this license?', 'elementor-bulk-importer' ); ?>');">
							<?php esc_html_e( 'Deactivate License', 'elementor-bulk-importer' ); ?>
						</button>
					</form>
				</div>
			<?php else : ?>
				<!-- Inactive License -->
				<div class="ebi-license-card ebi-license-inactive-card">
					<div class="ebi-license-header">
						<span class="ebi-license-icon" style="color: #856404; font-size: 28px;">🔒</span>
						<h2><?php esc_html_e( 'Activate PRO License', 'elementor-bulk-importer' ); ?></h2>
					</div>
					
					<p class="ebi-license-description">
						<?php esc_html_e( 'Enter your license key below to unlock all PRO features including automatic translation with 5 APIs and 70+ languages support.', 'elementor-bulk-importer' ); ?>
					</p>
					
					<form method="post">
						<?php wp_nonce_field( 'ebi_license_nonce', 'ebi_license_nonce' ); ?>
						
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="ebi_license_key"><?php esc_html_e( 'License Key', 'elementor-bulk-importer' ); ?></label>
								</th>
								<td>
									<input type="text" 
									       id="ebi_license_key" 
									       name="ebi_license_key" 
									       value="<?php echo esc_attr( $license_key ); ?>" 
									       class="regular-text"
									       placeholder="XXXX-XXXX-XXXX-XXXX">
									<p class="description">
										<?php esc_html_e( 'Enter your 19-character license key in the format: XXXX-XXXX-XXXX-XXXX', 'elementor-bulk-importer' ); ?>
									</p>
								</td>
							</tr>
						</table>
						
						<p class="submit">
							<button type="submit" name="ebi_activate_license" class="button button-primary">
								<?php esc_html_e( 'Activate License', 'elementor-bulk-importer' ); ?>
							</button>
						</p>
					</form>
				</div>
				
				<!-- Get PRO Box -->
				<div class="ebi-get-pro-card">
					<h3><?php esc_html_e( 'Upgrade to PRO', 'elementor-bulk-importer' ); ?></h3>
					<p><?php esc_html_e( 'Unlock powerful translation features and boost your productivity:', 'elementor-bulk-importer' ); ?></p>
					<ul class="ebi-pro-features-list">
						<li>5 Translation APIs (MyMemory, LibreTranslate, DeepL, Microsoft, Yandex)</li>
					<li>70+ Languages Support</li>
						<li>Smart Content Detection</li>
						<li>Lifetime Updates & Support</li>
					</ul>
					<p style="margin-top: 20px;">
						<a href="https://t.me/bayramsavluk" target="_blank" class="button button-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3); padding: 10px 20px; font-size: 14px; border-radius: 8px;">
							<?php esc_html_e( 'Contact via Telegram', 'elementor-bulk-importer' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

// Initialize license admin
EBI_License_Admin::get_instance();
