<?php
  /*
Plugin Name: Psygraph
Plugin URI: http://psygraph.com
Description: This plugin integrates with the Psygraph mobile app (an app that tracks your meditation, breathing, and mindfulness) to visualize your data in WordPress.  The provided shortcodes can do things like generate progress charts, show the history of meditation sessions, and allow playback of recorded audio notes.
Version: 0.8.6
Author: Alec Rogers
Author URI: http://arborrhythms.com
License: http://creativecommons.org/licenses/by-sa/4.0/
  */


// activate/deactivate plugin
require_once(plugin_dir_path(__FILE__)."/pg_db.php");
require_once(plugin_dir_path(__FILE__)."/pg_settings.php");

register_activation_hook( __FILE__, 'pg_activate' );
register_deactivation_hook( __FILE__, 'pg_deactivate' );
register_uninstall_hook( __FILE__, 'pg_uninstall' );

// remove any data when a user is deleted
add_action('delete_user', 'pg_deleteUserCB');

// add shortcode 
require_once(plugin_dir_path(__FILE__)."/pg_shortcode.php");
add_shortcode('pg_page',   'pg_pageShortcode');
add_shortcode('pg_events', 'pg_eventsShortcode');
add_shortcode('pg_link',   'pg_linkShortcode');

// add xml-rpc methods for Psygraph server
require_once(plugin_dir_path(__FILE__)."/pg_xmlrpcMethods.php");
require_once(plugin_dir_path(__FILE__)."/pg_wp_functions.php");
add_filter('xmlrpc_methods', 'pg_xmlrpcMethods' );

// add a settings page
require_once(plugin_dir_path(__FILE__)."/pg_settings.php");
add_action('admin_menu', 'pg_settings_add_page');
add_action('admin_init', 'pg_settings_init');

$pg_plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$pg_plugin", 'pg_settings_link' ); 

// process a query var instead of creating a post for each user
function pg_query_vars($aVars) {
    $aVars[] = "pg_username";
    return $aVars;
}
add_filter('query_vars', 'pg_query_vars');

// hook pg_rewrite_rule into rewrite_rules_array
function pg_init() {
    $page = pg_settingsValue("page");
    add_rewrite_rule('^'.$page.'/([^/]+)/?', 'index.php?pagename=psygraph_template&pg_username=$matches[1]', 'top');
    wp_register_style('psygraph', plugins_url('psygraph.css',__FILE__ ));
    wp_register_script( 'psygraph', plugins_url('psygraph.js',__FILE__ ));
}
add_action('init', 'pg_init');

// use the registered psygraph js and css
function pg_enqueue_scripts() {
    wp_enqueue_style('psygraph');
    wp_enqueue_script('psygraph');
    wp_enqueue_script('jquery');
}

add_action('wp_enqueue_scripts', 'pg_enqueue_scripts');

// make sure that we can upload M4A files
//add_filter('upload_mimes', 'pg_upload_types', 1, 1);
//function pg_upload_types($existing_mimes=array()){
//    $existing_mimes['m4a'] = 'audio/m4a';
//    return $existing_mimes;
//}

?>