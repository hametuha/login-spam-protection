<?php
/**
 * Plugin Name: Login Spam Protection
 * Plugin URI: https://wordpress.org/plugin/login-spam-protection
 * Description: A WordPress plguin to protect login screen.
 * Author: Takahashi_Fumiki
 * Version: nightly
 * Author URI: https://hametuha.co.jp
 * Text Domain: lsp
 * Domain Path: /languages/
 * License: GPL3 or Later
 */

// Don't allow plugin to be loaded directory.
defined( 'ABSPATH' ) || die( 'Do not load directly.' );


// Add action after plugins are loaded.
add_action( 'plugins_loaded', 'lsp_setup_after_plugins_loaded', 11 );

/**
 * Start plugin
 *
 * @ignore
 */
function lsp_setup_after_plugins_loaded() {
	// Add i18n for here for other plugins.
	load_plugin_textdomain( 'lsp', false, basename( __DIR__ ) . '/languages' );
	// Load autoloader.
	require_once  __DIR__ . '/vendor/autoload.php';
	// Call bootstrap.
	\Hametuha\LoginSpamProtection\Bootstrap::get_instance();
}
