<?php
/**
 * PRO Version Helper Functions
 *
 * @package Elementor_Bulk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if PRO version is active
 *
 * @return bool
 */
function ebi_is_pro() {
	return defined( 'EBI_PRO_VERSION' ) && EBI_PRO_VERSION;
}

/**
 * Get PRO upgrade URL
 *
 * @return string
 */
function ebi_get_upgrade_url() {
	return 'https://t.me/bayramsavluk';
}

/**
 * Display PRO feature notice
 *
 * @param string $feature Feature name.
 * @return void
 */
function ebi_pro_feature_notice( $feature = '' ) {
	if ( ebi_is_pro() ) {
		return;
	}
	
	$message = $feature 
		? sprintf( __( '%s is a PRO feature.', 'ea-page-builder-assistant' ), $feature )
		: __( 'This is a PRO feature.', 'ea-page-builder-assistant' );
	
	printf(
		'<div class="notice notice-info"><p>%s <a href="%s" target="_blank">%s</a></p></div>',
		esc_html( $message ),
		esc_url( ebi_get_upgrade_url() ),
		esc_html__( 'Upgrade to PRO', 'ea-page-builder-assistant' )
	);
}
