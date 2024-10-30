<?php
/*
 * Copyright © 2024  Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed

require_once(IAMG_CLASSES_PATH . "IAMG_Client.php");
require_once(IAMG_CLASSES_PATH . 'IAMG_AdminNotice.php');

use IAMG\IAMG_AdminNotice;
use IAMG\IAMG_Client;

class IAMG_Activation
{

    private $client;

    public function __construct()
    {
        $this->client = new IAMG_Client();
        if (is_admin()) {
            register_activation_hook(IAMG_MAIN_FILE, [$this, 'iamg_plugin_activate']);
            //deactivation hook also exists in IAMG_LibHandler.php

            register_uninstall_hook(IAMG_MAIN_FILE, ['IAMG_Activation', 'uninstall']);

            add_action('admin_init', [$this, 'load_plugin']);
        }

        if (!$this->client->is_local_server()) {
            add_action('wp_ajax_nopriv_iamg_verify', [$this, 'verify_wp_server']);


        }
    }

    public function iamg_plugin_activate()
    {
//        return;
        add_option('_iamg_activating_plugin', true);
        add_option('_iamg_activated_plugin', true);

        // Flush cache
        wp_cache_flush();

        return;


        /* activation code here */
//        $result = $this->first_register();
//        if (!$result["success"]) {
//            add_option('_iamg_activation_plugin_error', $result);
//        }

    }


    private function first_register()
    {
        $client = $this->client;


//        return ["success" => false, "error" => "Early end"];

        $registered = $client->register_client();
        update_option($client->get_slug() . '_called_reg_client', $registered); //for debugging
//        IAMG_AdminNotice::display_notice(json_encode($registered));

        $message = "";
        if (!$registered["success"] && $registered["error"] === IAMG_Client::ERROR_LOCAL_SERVER_USER_AGENT_PROBLEM) {
            $this->update_app(); //force to get app nevertheless
            return ["success" => true];

            $success = $client->unregister_local();

            if (!$success) {
                $message = esc_html__('You are running a local server, you have likely activated the plugin on a different
                 local server, and you are also running a publicly accessible wordpress site on the same public IP address. To resolve the issue, please contact support@iaesth.ca',
                    'ia-magic-galleries');
                $this->update_app(); //force to get app nevertheless
                return ["success" => false, "error" => $message];

//                return $this->end_activation($message);
            } else {
                $registered = $client->register_client();
            }
        }
        if (!$registered["success"] && $registered["error"] === IAMG_Client::ERROR_NO_ACCESS_TO_INTERNET) {
            $message = esc_html__('The WordPress server does not have or allow access to the internet. The plugin requires access to the internet to register with the Information Aesthetics servers. Please contact your hosting provider for assistance.',
                'ia-magic-galleries');
        }
        if (!$registered["success"] && $registered["error"] === IAMG_Client::ERROR_SERVER_NOT_REACHABLE) {
            $message = esc_html__('The WordPress server cannot resolve the Information Aesthetics servers. The plugin requires access to the internet to register with the Information Aesthetics servers. Please contact your hosting provider for assistance to check DNS settings.',
                'ia-magic-galleries');
        }
        if (!$registered["success"] && $registered["error"] === IAMG_Client::ERROR_SERVER_NOT_AVAILABLE) {
            $message = esc_html__('The WordPress server cannot reach the Information Aesthetics servers. If you can reach www.iaesth.ca from your browser, then the WordsPress hosting server may be blocking the plugin. 
            The plugin requires access to the internet to register with the Information Aesthetics servers. Please contact your hosting provider for assistance to check firewall settings.',
                'ia-magic-galleries');
        }
        if (!$registered["success"] && $registered["error"] === IAMG_Client::ERROR_CONNECT_TIMEOUT) {
            $message = esc_html__('The WordPress server is taking too long to reach the Information Aesthetics servers. If you can reach www.iaesth.ca from your browser, then the WordsPress hosting server may be blocking the plugin. 
            The plugin requires access to the internet to register with the Information Aesthetics servers. Please contact your hosting provider for assistance to check firewall settings, or try activating the plugin again.',
                'ia-magic-galleries');
        }
        if (!$registered["success"] && $registered["error"] === IAMG_Client::ERROR_NETWORK_EXCEPTION) {
            $message = esc_html__('This is embarrassing. The plugin encountered a network exception while trying to register with the Information Aesthetics servers. Please try activating the plugin again. If the problem persists, please contact your hosting provider and Information Aesthetics support for assistance. The actual error message is: ',
                'ia-magic-galleries');
            $message .= $registered["message"];
        }
        if (!$registered["success"] && $registered["error"] === IAMG_Client::ERROR_CONNECT_ERROR) {
            $message = esc_html__('The WordPress server encountered a connection error while trying to register with the Information Aesthetics servers. Please try activating the plugin again. If the problem persists, please contact your hosting provider and Information Aesthetics support for assistance.',
                'ia-magic-galleries');
//            return $this->end_activation($message);
        };

        if (!$registered["success"] && $message) {
            return ["success" => false, "error" => $message];
        }

        if ($registered["success"]) {
            $this->update_app();
            update_option($client->get_slug() . '_registered', gmdate("Y-m-d H:i:s"));
            return ["success" => true];
        }
    }

    private function update_app()
    {
        $this->client->update_app_script();
    }

    public function load_plugin()
    {
        $result = false;
        if (is_admin()) {
            $during_activation = get_option('_iamg_activating_plugin');
            $first_after_activation = !$during_activation && get_option('_iamg_activated_plugin');

//            IAMG_AdminNotice::display_notice("A notice here using display notice2");


            if ($during_activation) {
                delete_option('_iamg_activating_plugin');
                /* do stuff once right after activation */
                // example: add_action( 'init', 'my_init_function' );

                //Register the plugin with the server and load all resources
                $result = $this->first_register();

//                IAMG_AdminNotice::display_notice(json_encode($result));

                $redirect = true;

                if (!$result["success"]) {
//                    IAMG_AdminNotice::display_notice("A notice here using display notice5");
                   $redirect = $this->end_activation(
                        $result["error"]
                    );
                }
                new IAMG_GalleryUpdate("ignore_lib_update"); //records version info if missing

                if (!extension_loaded('gd')) {
                    $message = __("To function optimally, IA Magic Galleries requires the GD library for image processing to be installed on the server. Otherwise, uploaded images would not be processed by WordPress. Please contact your hosting provider for assistance.",
                        'ia-magic-galleries');
                    IAMG_AdminNotice::display_notice($message, IAMG_AdminNotice::WARNING);
                    $redirect = true;
                }

                if ($redirect) {
                    $this->redirect_to_overview_page();
                }

            } else {
                //check for updates
                new IAMG_GalleryUpdate();
            }

            //for future use
            if ($first_after_activation) {
//                IAMG_AdminNotice::display_notice("A notice here using display notice4");
                delete_option('_iamg_activated_plugin');
            }
        } else {
            //check for updates
            new IAMG_GalleryUpdate();
        }
    }

    // Add the following function
    public function redirect_to_overview_page()
    {
        $redirect_url = admin_url('edit.php?post_type=iamg&page=iamg-overview-page');
        wp_redirect($redirect_url);
        exit;
    }

    public function verify_wp_server()
    {
        $nonce = sanitize_text_field(wp_unslash($_COOKIE['_iamgnonce']));
        if (!wp_verify_nonce($nonce, 'iamg_direct')) {
            http_response_code(404);
            die();
        }
        $ip = $this->get_caller_ip();

        //Do not remove these IP addresses. The SAS server will not be able to connect to the plugin
        // to verify it and the many not be able to update the app library if needed.
        // The addresses ensure that no other client can access the plugin to initiate the operation.
        $allow_addresses = [

            '107.161.24.204', //iaesth.ca
            '192.184.90.53' //infoaesthetics.ca
        ];


        if (!in_array($ip, $allow_addresses)) {
            http_response_code(404);
            die();
        };

        $encrypt = $this->client->encrypt($this->client->basename); //signs the response to the server
        //The SAS will verify the plugin by decrypting the secret with the unique private key and comparing it to the plugin basename.
        $encrypt[0] = base64_encode($encrypt[0]);
        $responce = [
            "plugin" => $this->client->basename,
            "wp_url" => esc_url(home_url()),
            "secret" => $encrypt
        ];

        if ((isset($_POST["update_app"]) && $_POST["update_app"])
            || (isset($_GET["update_app"]) && $_GET["update_app"])
        ) {
            $responce["updating"] = true;
            //asks the plugin to get an updated app library from the server immediately as opposed to following the regular schedule. Useful if security vulnerability is detected.
            //TODO: implement version restriction
            $this->run_after_send_json($responce, [$this, 'update_app']);  //exists the call
        } else {
            wp_send_json($responce);
        }
    }

    private function run_after_send_json($response, $callback, $status_code = null)
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            _doing_it_wrong(
                __FUNCTION__,
                esc_html(sprintf(
                /* translators: 1: WP_REST_Response, 2: WP_Error */
                    __('return a % 1$s or %2$s object from your callback when using the REST API . '),
                    'WP_REST_Response',
                    'WP_Error'
                )),
                '5.5.0'
            );
        }


// Extending the execution time of the script here is recommended because the server needs to execute a curl call to the SAS and respond to a possible timeout with calling a backup SAS.
//
// This line is called on a very rare circumstance, when an external request for a player update is issued from the SAS, for security reasons.

//        set_time_limit(60); //lets hope the server is not too slow

        ob_start();

        if (!headers_sent()) {
            header('Content - Type: application / json; charset = ' . get_option('blog_charset'));
        }

        //This is trusted output sent to the SAS server.
        echo wp_json_encode($response);

        header('Connection: close');
        header('Content - Length: ' . ob_get_length());
        ob_end_flush();
        @ob_flush();
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        };//required for PHP-FPM (PHP > 5.3.3)

        call_user_func($callback);

        die;

    }

    private function get_caller_ip()
    {
        // Known prefix
        $v4mapped_prefix_hex = '00000000000000000000ffff';
        $v4mapped_prefix_bin = pack("H*", $v4mapped_prefix_hex);

// Or more readable when using PHP >= 5.4
# $v4mapped_prefix_bin = hex2bin($v4mapped_prefix_hex);

// Parse
        $addr = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        $addr_bin = inet_pton($addr);
        if ($addr_bin === false) {
            // Unparsable? How did they connect?!?
            die('Invalid IP address');
        }

// Check prefix
        if (substr($addr_bin, 0, strlen($v4mapped_prefix_bin)) == $v4mapped_prefix_bin) {
            // Strip prefix
            $addr_bin = substr($addr_bin, strlen($v4mapped_prefix_bin));
        }

// Convert back to printable address in canonical form
        $addr = inet_ntop($addr_bin);
        return $addr;
    }

    private function end_activation($error_message)
    {
        IAMG_AdminNotice::display_notice($error_message, IAMG_AdminNotice::ERROR);

        deactivate_plugins(plugin_basename(__FILE__));

        return false;
    }

    public static function uninstall()
    {
        (new IAMG_Client())->unregister();

        $save_posts = get_option(IAMG_ComDispatcher::_get_setting_option_name('preserve_posts'), false);


        //remove all options with the plugin slug given by IAMG_SLUG
        global $wpdb;
//        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '" . IAMG_SLUG . " % '");
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                IAMG_SLUG . ' % '
            )
        );

        //remove all metadata for posts if type IAMG_POST_TYPE and then remove all posts
        if (!$save_posts) {
            $post_ids = get_posts([
                'post_type' => IAMG_POST_TYPE,
                'post_status' => ['any', 'auto - draft'],
                'numberposts' => -1,
                'fields' => 'ids'
            ]);

            foreach ($post_ids as $post_id) {
                $keys = get_post_meta($post_id);
                if ($keys) {
                    foreach ($keys as $key => $value) {
                        delete_post_meta($post_id, $key);
                    }
                }

                $revisions = wp_get_post_revisions($post_id);
                foreach ($revisions as $revision) {
                    wp_delete_post_revision($revision->ID);
                }

                wp_delete_post($post_id, true);
            }
        }

        return true;
    }
}

new IAMG_Activation();