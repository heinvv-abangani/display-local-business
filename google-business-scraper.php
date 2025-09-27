<?php
/**
 * Plugin Name: Google Business Scraper
 * Description: Fetches local Google Business Profiles using the Google Places API.
 * Version: 1.0.0
 * Author: Jan van Vlastuin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GBS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GBS_DB_VERSION', '1.0' );

require_once GBS_PLUGIN_DIR . 'includes/class-gbs-admin.php';
require_once GBS_PLUGIN_DIR . 'includes/class-gbs-api.php';
require_once GBS_PLUGIN_DIR . 'includes/class-gbs-db.php';
require_once GBS_PLUGIN_DIR . 'includes/class-gbs-list-table.php';

function gbs_init() {
	new GBS_Admin();
}
add_action( 'plugins_loaded', 'gbs_init' );

function gbs_activate() {
	GBS_DB::create_table();
	add_option( 'gbs_db_version', GBS_DB_VERSION );
}
register_activation_hook( __FILE__, 'gbs_activate' );