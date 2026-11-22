<?php
/**
 * Removes the plugin's settings when it is deleted.
 *
 * @package Bmailu
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'bmailu_settings' );
