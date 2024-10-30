<?php

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(plugin_dir_path(__FILE__) . 'install.php')) {
    include_once(plugin_dir_path(__FILE__) . 'install.php');
}

add_action('admin_menu', 'heyday_add_settings_page');

function heyday_add_settings_page() {
    add_menu_page(
        'HeyDay Search More Settings',
        'HeyDay Search More',
        'manage_options',
        'heyday-merchant-feed',
        'heyday_render_settings_page'
    );
}

function heyday_render_settings_page() {
    $site_url = esc_url(get_site_url());
    $parsed_url = parse_url($site_url);
    $domain = isset($parsed_url['host']) ? esc_html($parsed_url['host']) : '';
    $feed_link = esc_url($site_url . '/wp-json/heyday-search/v1/items-pagination');

    $error_message = esc_html(get_option('heyday_merchant_feed_err'));
    $affid = esc_html(get_option('heyday_merchant_feed_affid'));
    $email = esc_html(get_option('admin_email'));
    $password = esc_html(get_option('heyday_merchant_feed_pass'));

    $redirect_url = esc_url('https://admin.heyday.io/autoLogin?p=' . urlencode($password) . '&u=' . urlencode($email) . '&platform=wordPress&pDomain=' . urlencode($domain) . '&pFeedUrl=' . urlencode($feed_link));

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('HeyDay Search More Plugin Settings', 'heyday-search'); ?></h1>
        <div id="heyday_content">
            <img src="<?php echo esc_url(plugins_url('assets/img/HeyDay_Logo.png', __DIR__)); ?>" alt="hdy-logo" id="hdy-logo"/>
            <div class="heyday-welcome-div"><?php esc_html_e('Hey, Welcome to', 'heyday-search'); ?> <span class="heyday-welcome-span"><?php esc_html_e('Heyday', 'heyday-search'); ?></span>🚀</div>

            <?php if ($affid && $email && $password): ?>
                <h2><?php esc_html_e('Your Admin Dashboard User Information', 'heyday-search'); ?></h2>
                <p>
                    <strong><?php esc_html_e('Products Feed:', 'heyday-search'); ?></strong> 
                    <a href="<?php echo esc_url($feed_link); ?>" target="_blank"><?php echo esc_html($feed_link); ?></a>
                </p>
                <p><strong><?php esc_html_e('AffID:', 'heyday-search'); ?></strong> <?php echo esc_html($affid); ?></p>
                <p><strong><?php esc_html_e('Email:', 'heyday-search'); ?></strong> <?php echo esc_html($email); ?></p>
                <p><strong><?php esc_html_e('Password:', 'heyday-search'); ?></strong> <?php echo esc_html($password); ?></p>
                <h2><?php esc_html_e('Redirect to HeyDay Admin Dashboard', 'heyday-search'); ?></h2>
                <p><?php esc_html_e('Click the button below to go to your HeyDay Admin Dashboard.', 'heyday-search'); ?></p>
                <button id="heyday-redirect-button" class="button button-primary"><?php esc_html_e('Go to HeyDay Admin Dashboard', 'heyday-search'); ?></button>
                <input type="hidden" id="heyday-redirect-url" value="<?php echo esc_url($redirect_url); ?>" />

            <?php else: ?>
                <?php if ($error_message === "username taken"): ?>
                    <div class="notice notice-warning">
                        <p><?php esc_html_e('We see that this email is already taken. If it is you, please provide your password below. If you installed this plugin in another WordPress store, please log in to the WP admin of that store, navigate to HeyDay Search More in the side menu, and copy the password from the settings page.', 'heyday-search'); ?></p>
                        <form method="POST" action="">
                            <input type="password" name="heyday_password" placeholder="<?php esc_html_e('Enter your password', 'heyday-search'); ?>" required />
                            <input type="hidden" name="heyday_email" value="<?php echo esc_attr($email); ?>" />
                            <button type="submit" class="button button-primary"><?php esc_html_e('Submit', 'heyday-search'); ?></button>
                        </form>
                    </div>

                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['heyday_password'])) {
                        $password = sanitize_text_field($_POST['heyday_password']);

                        $request_json = array(
                            "action" => 1,
                            "credentials" => array(
                                "uName" => $email,
                                "password" => $password
                            )
                        );

                        $response = wp_remote_post('https://admin.heyday.io/panWbPush/', array(
                            'body' => json_encode($request_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            'headers' => array('Content-Type' => 'application/json'),
                        ));

                        if (is_wp_error($response)) {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Error: Could not connect to the API.', 'heyday-search') . '</p></div>';
                            return;
                        } else {
                            $body = wp_remote_retrieve_body($response);
                            $resp = json_decode($body, true);
                            $objKey = !empty($resp['affDefault']) ? array_keys($resp['affDefault'])[0] : null;
                            $affId = isset($resp['affDefault'][$objKey]['id']) ? $resp['affDefault'][$objKey]['id'] : null;

                            if ($affId) {
                                heyday_force_update_option('heyday_merchant_feed_affid', $affId);
                                heyday_force_update_option('heyday_merchant_feed_pass', $password);
                                heyday_force_update_option('heyday_merchant_feed_email', $email);
                                heyday_force_update_option('heyday_merchant_feed_just_installed', true);
                                heyday_force_delete_option('heyday_merchant_feed_err');

                                if (function_exists('heyday_merchant_feed_enqueue_script')) {
                                    heyday_merchant_feed_enqueue_script();
                                }

                                echo '<script>location.reload();</script>';

                            } else {
                                echo '<div class="notice notice-error"><p>' . esc_html__('Invalid credentials. Please try again or contact support.', 'heyday-search') . '</p></div>';
                            }
                        }
                    }
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}


function heyday_enqueue_admin_styles_scripts($hook) {
    if ($hook != 'toplevel_page_heyday-merchant-feed') {
        return;
    }

    wp_register_style('heyday_styles', esc_url(plugins_url('assets/css/heyday-styles.css', __DIR__)));
    wp_enqueue_style('heyday_styles');

    wp_register_script('heyday_scripts', esc_url(plugins_url('assets/js/heyday-scripts.js', __DIR__)), array('jquery'), null, true);
    wp_enqueue_script('heyday_scripts');
}
add_action('admin_enqueue_scripts', 'heyday_enqueue_admin_styles_scripts');

?>
