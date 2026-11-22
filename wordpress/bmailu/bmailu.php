<?php
/**
 * Plugin Name:       Bmailu
 * Plugin URI:        https://bmailu.com/docs/
 * Description:       Sends all WordPress email through the Bmailu Email API instead of the server's mail() function.
 * Version:           0.1.0
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Bmailu
 * Author URI:        https://bmailu.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bmailu
 *
 * @package Bmailu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BMAILU_VERSION', '0.1.0' );
define( 'BMAILU_OPTION', 'bmailu_settings' );

require_once __DIR__ . '/includes/class-bmailu-mailer.php';

add_filter( 'pre_wp_mail', array( 'Bmailu_Mailer', 'pre_wp_mail' ), 10, 2 );

if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-bmailu-admin.php';
	Bmailu_Admin::init( plugin_basename( __FILE__ ) );
}
