<?php
/**
 * Plugin Name:       EcoCash Donations
 * Plugin URI:        https://developers.ecocash.co.zw
 * Description:       Accept EcoCash donations on WordPress via the official EcoCash Open API (C2B instant payments). No aggregator needed.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Getup&Glo
 * License:           GPL-2.0-or-later
 * Text Domain:       ecocash-donations
 */

defined( 'ABSPATH' ) || exit;

define( 'ECOCASH_DONATIONS_VERSION', '1.1.0' );
define( 'ECOCASH_DONATIONS_FILE', __FILE__ );
define( 'ECOCASH_DONATIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECOCASH_DONATIONS_URL', plugin_dir_url( __FILE__ ) );
define(
	'ECOCASH_DONATIONS_API_BASE',
	'https://developers.ecocash.co.zw/api/ecocash_pay'
);

require_once ECOCASH_DONATIONS_DIR . 'includes/class-ecocash-api.php';
require_once ECOCASH_DONATIONS_DIR . 'includes/class-ecocash-emails.php';
require_once ECOCASH_DONATIONS_DIR . 'includes/class-ecocash-donations.php';
require_once ECOCASH_DONATIONS_DIR . 'includes/class-ecocash-admin.php';

register_activation_hook(
	__FILE__,
	array( 'Ecocash_Donations', 'activate' )
);

/**
 * Flush CPT rewrite rules on deactivation.
 */
function ecocash_donations_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ecocash_donations_deactivate' );
