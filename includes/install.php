<?php

if (!defined('ABSPATH')) {
    exit; 
}

function heyday_force_update_option($option_name, $new_value) {
    delete_option($option_name);

    if (false === update_option($option_name, $new_value)) {
        add_option($option_name, $new_value);
    }

    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

function heyday_force_delete_option($option_name) {
    delete_option($option_name);

    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

function heyday_search_more_plugin_install() {
    if (!class_exists('WooCommerce')) {
        update_option('heyday_merchant_feed_err', 'This plugin requires WooCommerce to be installed and active.');
        return;
    }

    if (get_option('heyday_merchant_feed_affid') && get_option('heyday_merchant_feed_pass') && get_option('heyday_merchant_feed_email')) {
        add_action('wp_enqueue_scripts', 'heyday_merchant_feed_enqueue_script');
        return;
    }
    $admin_email = get_option('admin_email');
    $site_url = get_site_url();
    $parsed_url = parse_url($site_url);
    $domain = $parsed_url['host'];
    $admin_user = get_user_by('email', $admin_email);
    $admin_name = substr($admin_user->display_name, 0, 30); 

    if (strlen($admin_name) < 3) {
        $admin_name = 'Admin';
    }

    $password = str_replace([' ','&','#','%'], '!', wp_generate_password(8, true, false));

    $request_json = array(
        "action" => 1000,
        "uName" => $admin_email,
        "password" => $password,
        "contactName" => $admin_name,
        "successiveAction" => array(
            "action" => 1017,
            "domainName" => $domain,
            "searchScoreType" => 2,
            "credentials" => array(
                "uName" => $admin_email,
                "password" => $password
            ),
        ),
        "affType" => 0,
        "click_src" => "wordPress"
    );

    $response = wp_remote_post('https://admin.heyday.io/panWbPush/OP', array(
        'body' => json_encode($request_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'headers' => array('Content-Type' => 'application/json'),
    ));

    if (is_wp_error($response)) {
        update_option('heyday_merchant_feed_err', 'Failed to communicate with the server.');
        return;
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    heyday_force_update_option('heyday_merchant_feed_email', $admin_email);
    if (isset($data['affId'])) {
        heyday_force_update_option('heyday_merchant_feed_affid', $data['affId']);
        heyday_force_update_option('heyday_merchant_feed_pass', $password);
        heyday_force_update_option('heyday_merchant_feed_just_installed', true);
    } else if (isset($data['error'])) {
        heyday_force_update_option('heyday_merchant_feed_err', $data['error']);
    } else {
        heyday_force_update_option('heyday_merchant_feed_err', 'Failed to create user. Please contact us.');
    }

    add_action('wp_enqueue_scripts', 'heyday_merchant_feed_enqueue_script');
}

function heyday_merchant_feed_enqueue_script() {
    $site_url = get_site_url();
    $parsed_url = parse_url($site_url);
    $domain = $parsed_url['host'];
    $affid = get_option('heyday_merchant_feed_affid');
    if ($affid) {
        $script_url = 'https://cdn.heyday.io/cstmst/heyDayMain.js?affId=' . esc_attr($affid) . '&d=' . esc_attr($domain);
        wp_register_script('heyday_sm_wp', $script_url, array(), null, false);
        wp_enqueue_script('heyday_sm_wp');
    }
}

function heyday_search_more_deactivate() {
    remove_action('wp_enqueue_scripts', 'heyday_merchant_feed_enqueue_script');
    heyday_force_delete_option('heyday_merchant_feed_err');
    heyday_force_delete_option('heyday_merchant_feed_just_installed');
}

function heyday_wc_uninstall_xml_feed() {
    $affId = get_option('heyday_merchant_feed_affid');
    $admin_email = get_option('admin_email');
    $password = get_option('heyday_merchant_feed_pass');
    $site_url = get_site_url();
    $parsed_url = parse_url($site_url);
    $domain = isset($parsed_url['host']) ? esc_html($parsed_url['host']) : '';

    if($affId && $admin_email && $password){
        $backend_hostName = 'admin.heyday.io';
        $accessToken = '';
        $login_request_json = array(
            "action" => 1,
            "credentials" => array(
                "uName" => $admin_email,
                "password" => $password
            ),
        );

        $login_response = wp_remote_post('https://' . $backend_hostName . '/panWbPush/', array(
            'body' => json_encode($login_request_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'headers' => array('Content-Type' => 'application/json'),
        ));

        // Log the response
        if (is_wp_error($login_response)) {
            heyday_force_update_option('heyday_merchant_feed_err', 'Login error: Failed to login.');
            heyday_force_delete_option('heyday_merchant_feed_just_installed');
            return;
        }

        $login_response_body = wp_remote_retrieve_body($login_response);

        $login_data = json_decode($login_response_body, true);

        if (isset($login_data['accessToken'])) {
            $accessToken = $login_data['accessToken'];
            $delete_user_req = array(
                "action" => 1026,
                "host" => $domain,
            );

            $uri = '?c=1&accessToken=' . $accessToken . '&uName=' . $admin_email;
            $delete_user_response = wp_remote_post('https://' . $backend_hostName . '/panWbPush/' . $uri, array(
                'body' => json_encode($delete_user_req, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'headers' => array('Content-Type' => 'application/json'),
            ));

            // Log the delete user response
            if (is_wp_error($delete_user_response)) {
                echo "error";
            } else {
                $delete_user_response_body = wp_remote_retrieve_body($delete_user_response);
            }
        }
    }

    // Clean up options
    heyday_force_delete_option('heyday_merchant_feed_err');
    heyday_force_delete_option('heyday_merchant_feed_just_installed');
    heyday_force_delete_option('heyday_merchant_feed_affid');
    heyday_force_delete_option('heyday_set_meta_data');
    heyday_force_delete_option('heyday_merchant_feed_email');
    heyday_force_delete_option('heyday_merchant_feed_pass');
}

?>
