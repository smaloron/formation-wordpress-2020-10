<?php
/**
Plugin Name: Regen. Thumbs
Plugin URI: https://github.com/froger-me/regen-thumbs
Description: Regenerate post thumbnails with a single click on the post edit screen (Classic Editor).
Version: 1.1
Author: Alexandre Froger
Author URI: https://froger.me/
Text Domain: regen-thumbs
Domain Path: /languages
WC tested up to: 3.3.4
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! defined( 'REGEN_THUMBS_PLUGIN_PATH' ) ) {
	define( 'REGEN_THUMBS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'REGEN_THUMBS_PLUGIN_URL' ) ) {
	define( 'REGEN_THUMBS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

function regen_thumbs_run() {
	require_once dirname( __FILE__ ) . '/inc/class-regen-thumbs.php';

	$regen_thumbs = new Regen_Thumbs();
}
add_action( 'plugins_loaded', 'regen_thumbs_run', 10, 0 );
