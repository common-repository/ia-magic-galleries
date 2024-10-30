<?php
/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

use IAMG\IAMG_Client;

if (!defined('WPINC')) {
    die;
}
if (!defined("ABSPATH")) {
    exit;
}

//For future use
class IAMG_GalleryUpdate
{
    public $posts = array();

    public $needUpdate = 0;

    //For future use
    public $dbVersionOld = false;

    public $dbVersion = false;

    public $fieldArray = array();


    public function __construct($ignore_lib_update = false)
    {

        $curVersion = get_option(IAMG_SLUG . '_install_version', 0);
        $forceUpdateOptions = false;
        if ($curVersion == false) {
            //backwords compatibility
            $curVersion = get_option('iamg_install_version', 0);
            if ($curVersion != false) {
                $forceUpdateOptions = true;
                delete_option('iamg_install_version');
                delete_option('iamg_install_date');
            }

        }


        if ($curVersion != IAMG_VERSION || $forceUpdateOptions) {
            update_option(IAMG_SLUG . '_install_date', time());
            update_option(IAMG_SLUG . '_install_version', IAMG_VERSION);
            $this->needUpdate = true;
        }


//		$this->dbVersionOld = get_option( IAMG_SLUG .'_db_version', 0 );
//
//		$this->dbVersion = IAMG_VERSION;
//
//		if( $this->dbVersionOld == $this->dbVersion )  $this->needUpdate = false;

        if ($this->needUpdate) {
//            update_option(IAMG_SLUG . '_after_install', '1');

//            update_option( IAMG_SLUG .'_db_version', IAMG_VERSION );

           if (!$ignore_lib_update) (new IAMG_Client())->update_app_script();

            if ((count($this->fieldArray))) {
                $this->posts = $this->getGalleryPost();
                $this->update();
            }
        }
    }


    public function getGalleryPost()
    {
        $my_wp_query = new WP_Query();
        return $my_wp_query->query(
            array(
                'post_type' => IAMG_POST_TYPE,
                'posts_per_page' => 999,
            )
        );
    }

    public function fieldInit($fields)
    {
        for ($i = 0; $i < count($this->posts); $i++) {
            $postId = $this->posts[$i]->ID;
            if (count($fields)) {
                foreach ($fields as $key => $value) {
                    add_post_meta($postId, IAMG_PREFIX . $key, $value, true);
                }
            }
        }
    }

    public function update()
    {
        if (count($this->fieldArray)) {
            foreach ($this->fieldArray as $version => $fields) {
                if (
                    version_compare($version, $this->dbVersionOld, '>')
                    || version_compare($version, $this->dbVersion, '<=')
                ) {
                    if (isset($fields)) {
                        $this->fieldInit($fields);
                    }
                }
            }
        }
    }
}

//new IAMG_GalleryUpdate();