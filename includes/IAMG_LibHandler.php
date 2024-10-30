<?php
/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

use IAMG\IAMG_Client;

if (!defined('WPINC')) {
    exit;
}
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed

require_once(IAMG_CLASSES_PATH . "IAMG_Client.php");

/**
 * The class handles the scheduler for the library update.
 * Class IAMG_LibHandler
 * @package IAMagicGalleries
 */
class IAMG_LibHandler
{
    /**
     * @var mixed|null
     */
    private $client;
    private $schedule_hook;

    function __construct($client = null)
    {
        $this->client = $client;

        $this->schedule_hook = $this->getSlug("_lib_update");

        // Run hook if the library needs to be updated
        add_action($this->schedule_hook, array($this, 'checkExpiringLib'));

        $this->run_schedule();
    }

    /**
     * @return mixed|null
     */
    public function getClient(): IAMG_Client
    {
        if ($this->client) {
            return $this->client;
        }
        return $this->client = new IAMG_Client();
    }

    private function getSlug($sufix = "")
    {
        return $this->getClient()->get_slug() . $sufix;
    }

    public function activate()
    {
        if (!wp_next_scheduled($this->schedule_hook)) {
            wp_schedule_event(time(), 'daily', $this->schedule_hook);

            wp_schedule_single_event(time() + 20, $this->schedule_hook);
        }
    }

    public function deactivate()
    {
        wp_clear_scheduled_hook($this->schedule_hook);
    }

    public function checkExpiringLib()
    {
        $current_time = microtime(true);
        $expire_time = $this->getClient()->get_app_time();

        //If the app (IA Presenter) expire time is less than 24 hours, update the app script
        if ($expire_time - $current_time < 24 * 60 * 60) {
            $this->getClient()->update_app_script();
//            update_option($this->getSlug("_app_script_last"), gmdate("Y-m-d H:i:s"));
//            update_option("ia_lib_epire_test_loaded", gmdate("Y-m-d H:i:s"));
        } else{
//            update_option("ia_lib_epire_test_checked", gmdate("Y-m-d H:i:s"));
        }

    }

    /**
     * Enable/Disable schedule
     */
    private function run_schedule()
    {
        register_activation_hook(IAMG_MAIN_FILE, array($this, 'activate'));
        register_deactivation_hook(IAMG_MAIN_FILE, array($this, 'deactivate'));
    }
}

new IAMG_LibHandler();