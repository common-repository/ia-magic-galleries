<?php
/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

namespace IAMG;

if (!defined('ABSPATH')) exit; // Exit if accessed

class IAMG_AppSettingsBuilder
{

    /**
     * @var mixed|null
     */
    private $initial_graphics;
    private $client;

    const DEFAULT_EDITOR_RESOURCES = [
        'buttons' => ['buttons_presenter.json'],
        'tools' => [],
        'controllers' => [
            'controller_presenter_simple.json',
            'iamg_editor_included_images_controller.json'
        ],
        'libraries' => ['iamg_editor_libraries.json']
    ];

    const DEFAULT_PLAYER_RESOURCES = [
        'buttons' => ['buttons_presenter.json'],
        'tools' => [],
        'controllers' => ['controller_presenter_simple.json'],
        'libraries' => []
    ];

    public function __construct($initial_graphics = null)
    {
        $this->initial_graphics = $initial_graphics;
    }

    public function setup_load_json(
        $post_scripts = null,
        $resources = null,
        $sizeBehaviour = 'linked',
        $editor = false,
        $width = 680,
        $height = 600
    )
    {
        if (is_string($resources)) {
            $sizeBehaviour = $resources;
            $resources = null;
        }
        if (!$resources) {
            $resources = ($editor)
                ? self::DEFAULT_EDITOR_RESOURCES
                : self::DEFAULT_PLAYER_RESOURCES;
        }
        $buttons = $resources['buttons'];
        $libraries = $resources['libraries'];
        $controllers = $resources['controllers'];
        $tools = $resources['tools'];

        $ajax_url = admin_url('admin-ajax.php') . "?"
            . "action=iamg_com";
//        $resoruces_path = IAMG_URL . "resources/process.php?target="; //this is the old way
        $resoruces_path = IAMG_URL . "resources/";

        //IA_Designer GUI settings
        $settings = [
            "panel_setting" => [
                "sizeBehaviour" => $sizeBehaviour,
                "leftSize" => 0,
                "topSize" => 0,
                "rightSize" => 0,
                "bottomSize" => 0,
                "slideBar" => false,
                "positionDisplay" => false,
                "zoomControls" => false,
                "messageBox" => ["x" => 10, "y" => -5, "time" => 5000],
                "noDesignBackground" => true,
                "processInlineImgsets" => true,
                "processDelayedImages" => true,
                "mouseNavigation" => false,
                "skipSplashScreen" => true,
                "preventExternalLinks" => ($editor) ? "External Links are disabled in the Setup Environment" : false,
            ],
//            "width" => 600,
            "com_path" => $ajax_url,
            "com_action" => "", //we need to include this to avoid adding the default, com.php to the com_path
            "resources_dir" => $resoruces_path,
            "skip_svg_resources" => true, //must be true because the resources are not included
//			"post_scripts"  => [ [ 'presentation_expander.js', 'presentation_expander_loaded' ] ]
        ];

        if ($width) {
            $settings['width'] = $width;
        }
        if ($height) {
            $settings['height'] = $height;
        }

        if ($this->initial_graphics) {
            if (substr($this->initial_graphics, 0, strlen($ajax_url))) {
                $settings['initial_graphics'] = $this->initial_graphics;
            } else {
                $settings['initial_graphics'] = $resoruces_path . $this->initial_graphics;
            }
        }


        if ($buttons) {
            $settings['buttons'] = $buttons;
        }

        if ($libraries) {
            $settings['libraries'] = $libraries;
        }

        if ($controllers) {
            $settings['controllers'] = $controllers;
        }

        if ($tools) {
            $settings['tools'] = $tools;
        }

        if ($post_scripts) {
            $settings["post_scripts"] = $post_scripts;
        }

        $settings['pre_scripts'] = $this->get_app_link_internal($editor);

        return [
            "settings" => $settings,
            "resources" => IAMG_JS_URL //these resources are for IAPresenter_loader
        ];
    }

    public function get_app_link()
    {
        return $this->get_app_link_internal();
    }

    private function get_app_link_internal($editor = false)
    {

        $time = (int)$this->get_client()->get_app_time();

        $url = admin_url('admin-ajax.php');
        $url .= '?action=iamg_app&v=' . $time;
        if ($editor) {
            $url .= '&editor';
        }

        return ["url" => $url, "encrypted" => true, "cache" => ($editor) ? 'iaPresenterEditor' : 'iaPresenter'];
    }

    private function get_client(): IAMG_Client
    {
        if (!$this->client) {
            $this->client = new IAMG_Client();
        }
        return $this->client;
    }


    public function get_editor_resources()
    {
        $resources = $this->get_client()->get_app_resources(true);
        foreach (array_keys(self::DEFAULT_EDITOR_RESOURCES) as $item) {
            if (!isset($resources[$item])) {
                $resources[$item] = self::DEFAULT_EDITOR_RESOURCES[$item];
            }
        }
        return $resources;
    }

    public function get_resources()
    {
        $resources = $this->get_client()->get_app_resources();
        foreach (array_keys(self::DEFAULT_PLAYER_RESOURCES) as $item) {
            if (!isset($resources[$item])) {
                $resources[$item] = self::DEFAULT_PLAYER_RESOURCES[$item];
            }
        }
        return $resources;
    }

}