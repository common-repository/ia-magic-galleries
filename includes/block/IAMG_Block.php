<?php
/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

use IAMG\IAMG_AppSettingsBuilder;
use IAMG\IAMG_Client;
use IAMG\IAMG_Nonce;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed

if (!class_exists('IAMG/IAMG_AppSettings')) {
    require_once(IAMG_CLASSES_PATH . 'IAMG_AppSettingsBuilder.php');
}

if (!class_exists('IAMG_Block')) {
    class IAMG_Block
    {
//        const USE_MINIFIED = WP_DEBUG ? false : true;
        const USE_MINIFIED = true;
//        const USE_MINIFIED = false;

        const LZ_VERSION = '1.4.4';

        public function __construct()
        {
            add_action('enqueue_block_editor_assets', [$this, 'load_iamg_block_files']);
        }

        function load_iamg_block_files()
        {
            IAMG_Nonce::setNonce();

            wp_enqueue_media();
            wp_register_script('lz-string', IAMG_URL . $this->script_selector('js/dist/lz-string'), array(),
                self::LZ_VERSION, 'in_footer');
            wp_register_script("IAPresenter_loader", IAMG_URL . $this->script_selector('js/iaPresenter_loader'),
                array(), IAMG_VERSION, 'in_footer');
            wp_register_script('save-monitor', IAMG_URL . $this->script_selector('js/save_monitor'), array(),
                IAMG_VERSION, 'in_footer');
            wp_enqueue_script(
                'iamg-block-script',
                IAMG_URL . $this->script_selector('js/iamg-block'),
                array('wp-blocks', 'wp-i18n', 'wp-editor', 'jquery', 'lz-string', 'IAPresenter_loader', 'save-monitor'),
                IAMG_VERSION, 'in_footer'
            );

            $version = get_option(IAMG_SLUG . IAMG_Client::ADMIN_EDITOR_VERSION);
            $initial_graphics = admin_url('admin-ajax.php') . "?action=iamg_builder_pres&v=" . $version;
            $app_settings_handler = new IAMG_AppSettingsBuilder($initial_graphics);

            $app_settings = $app_settings_handler->setup_load_json([
//                    [$this->script_selector('presentation_expander'), 'presentation_expander_loaded']
            ],
                $app_settings_handler->get_editor_resources(),
                'linked',
                true
            );

            $version = get_bloginfo('version');
            $app_settings['wp_version'] = $version;
//            $app_settings['_iamgnonce'] = wp_create_nonce('iamg_block');

            wp_localize_script('iamg-block-script', 'iap_loader_settings',
                $app_settings
            );

            if (WP_DEBUG) {
//                wp_enqueue_style(
//                    'iamg-def-styles',
//                    IAMG_URL . 'css/ia_designer_general.css'
//                );
                wp_enqueue_style(
                    'iamg-def-styles',
                    IAMG_URL . 'css/ia_presenter_general.css',
                    array(), IAMG_VERSION
                );
                add_editor_style(IAMG_URL . 'css/ia_presenter_general.css');
            } else {
                wp_enqueue_style(
                    'iamg-def-styles',
                    IAMG_URL . 'css/ia_general.min.css',
                    array(), IAMG_VERSION
                );
                add_editor_style(IAMG_URL . 'css/ia_general.min.css');
            }

            wp_enqueue_style(
                'iamg-admin-styles',
                IAMG_URL . 'css/ia_presenter_admin.css',
                array(), IAMG_VERSION
            );
            add_editor_style(IAMG_URL . 'css/ia_presenter_admin.css');

            wp_enqueue_style(
                'video-js',
                IAMG_URL . 'css/video-js.css',
                array(), IAMG_VERSION
            );
            add_editor_style(IAMG_URL . 'css/video-js.css');
        }

        private function script_selector($name, $force_full = false)
        {
            if (self::USE_MINIFIED && !$force_full) {
                return $name . ".min.js";
            } else {
                return $name . ".js";
            }
        }
    }
}

new IAMG_Block();