<?php
/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

use IAMG\IAMG_Client;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed

/**
 * Sub menu class
 *
 * @author Mostafa <mostafa.soufi@hotmail.com>
 */
class IAMG_Submenue
{

    private $parent_slig = "edit.php?post_type=" . IAMG_POST_TYPE;

    /**
     * Autoload method
     * @return void
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_sub_menu']);
        add_action('add_meta_boxes', [$this, 'add_editor_metabox']);

        add_action('post_submitbox_misc_actions', [$this, 'add_saving_info_in_metabox']);
    }

    /**
     * Register submenu
     * @return void
     */
    public function register_sub_menu()
    {
        add_submenu_page(
            $this->parent_slig,
            'IA Magic Gallery Overview',
            'Overview',
            'edit_posts',
            'iamg-overview-page',
            [$this, 'overview_page_callback']
        );
        add_submenu_page(
            $this->parent_slig,
            'IA Magic Gallery Help',
            'Help',
            'edit_posts',
            'iamg-help-page',
            [$this, 'help_page_callback']
        );
    }

    /**
     * Render submenu
     * @return void
     */
    public function overview_page_callback()
    {
        do_action('iamg_enqueue_script');
        $client = new IAMG_Client();
        $pres = $client->get_resource("overview_presentation");


        ?>
        <div class="wrap">
            <?php
            if (!$pres) {
                echo "<div>" . esc_html__("Overview Presentation has not been loaded properly",
                        "ia-magic-galleries") . "</div>";
            } else {
                IAMG_posttype::render_post($pres, 'fixed', null, "height:90vh;");
            }
//            echo wp_kses($render_post, IAMG_posttype::$allowed_html);
            ?>
        </div>
        <?php
    }

    /**
     * Render submenu
     * @return void
     */
    public function help_page_callback()
    {
        do_action('iamg_enqueue_script');
        $client = new IAMG_Client();
        $pres = $client->get_resource("help_presentation");

        ?>
        <div class="wrap">
            <?php
            if (!$pres) {
                //todo: add a better error message
               echo "<div>" . esc_html__("Help Presentation has not been loaded properly",
                        "ia-magic-galleries") . "</div>";
            } else {
                IAMG_posttype::render_post($pres, 'fixed', null, "height:90vh;");
            }
//            echo wp_kses($render_post, IAMG_posttype::$allowed_html);
            ?>
        </div>
        <?php
    }

    function create_editor_environment($post)
    {
        $client = new IAMG_Client();
        $pres = $client->get_admin_presentation();
        // Already escaped in the render_post method.
        IAMG_posttype::render_post($pres, 'fixed', null, "height:90vh;");
//        echo wp_kses(IAMG_posttype::render_post($pres, 'fixed', null, "height:90vh;"),
//            IAMG_posttype::$allowed_html);
        ?>
        <img class="iamg-loading-gif" style="display: block;
    margin-left: auto;
    margin-right: auto;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);"
             src="<?php echo esc_url(IAMG_URL . 'images/admin/loading_dots.gif') ?>">
        </img>
        <?php
    }

    public function add_editor_metabox()
    {
        add_meta_box('iamg_editor_metabox', esc_html__('Edit Gallery Definition', "ia-magic-galleries"),
            array($this, "create_editor_environment"),
            IAMG_POST_TYPE, 'normal', 'high');
    }

    public function add_saving_info_in_metabox()
    {
        ?>
        <div id="saving_announcement" class="misc-pub-section" style="font-size: large;font-weight: bold">
            <?php esc_html_e("Save Gallery with the SAVE button inside the interface, once it is generated.",
                "ia-magic-galleries") ?>
        </div>
        <?php
    }


}

new IAMG_Submenue();