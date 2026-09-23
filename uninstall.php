<?php
/**
 * Uninstall cleanup for EcoCash Donations.
 *
 * @package EcoCash_Donations
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove settings.
delete_option( 'ecocash_api_key' );
delete_option( 'ecocash_environment' );
delete_option( 'ecocash_currency' );
delete_option( 'ecocash_test_number' );
delete_option( 'ecocash_notify_admin' );
delete_option( 'ecocash_admin_email' );

// Remove donation records (posts + meta).
$donations = get_posts(
	array(
		'post_type'   => 'ecocash_donation',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

foreach ( $donations as $donation_id ) {
	wp_delete_post( $donation_id, true );
}
