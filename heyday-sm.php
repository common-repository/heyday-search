<?php
/*
Plugin Name: HeyDay Search
Plugin URI: https://heyday.io/
Description: Generates a product feed link for and performs necessary installation actions.
Version: 1.0.2
Author: HeyDay Search
Author URI: https://heyday.io/about.html
License: GPL2
License URI: https://heyday.io/terms.html
Text Domain: heyday-search
*/

if (!defined('ABSPATH')) {
    exit; 
}

require_once plugin_dir_path(__FILE__) . 'includes/install.php';
require_once plugin_dir_path(__FILE__) . 'includes/feed.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings-page.php';

register_activation_hook(__FILE__, 'heyday_search_more_plugin_install');
register_deactivation_hook(__FILE__, 'heyday_search_more_deactivate');
register_uninstall_hook(__FILE__, 'heyday_wc_uninstall_xml_feed');

add_action('wp_enqueue_scripts', 'heyday_merchant_feed_enqueue_script');

function heyday_flush_rewrite_rules() {
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'heyday_flush_rewrite_rules');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
?>
 