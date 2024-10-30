<?php
/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

/*
Plugin Name: IA Magic Galleries
Plugin URI: https://iaesth.ca/wp/
Description: The plugin facilitates the integration of the IA Magic Galleries system into the WordPress environment.
Version: 1.2.1
Author: Information Aesthetics
Author URI: https://iaesth.ca
License: GPL2, V2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.8
Requires PHP: 7.0
Text Domain: ia-magic-galleries
*/

if (!defined('WPINC')) {
    die;
}
if (!defined("ABSPATH")) {
    exit;
}

//DEFINITIONS
define("IAMG", 1);
define("IAMG_MAIN_FILE", __FILE__);
define("IAMG_PATH", plugin_dir_path(IAMG_MAIN_FILE));
define("IAMG_INCLUDES_PATH", IAMG_PATH . 'includes/');
define("IAMG_CLASSES_PATH", IAMG_PATH . 'src/IAMG/');


list($slug, $_) = explode('/', plugin_basename(__FILE__));
define("IAMG_SLUG", (extension_loaded('mbstring')) ? mb_strtolower($slug) : strtolower($slug)); //ia-magic-galleries

define("IAMG_URL", plugin_dir_url(__FILE__));

define("IAMG_POST_TYPE", 'iamg');

define("IAMG_PREFIX", $slug . '_');

define("IAMG_JS_URL", IAMG_URL . 'js/');



    define("IAMG_VERSION", '1.2.1');
    define("IAMG_API_URL", "https://iaesth.ca/apps/IAMG/com");
    define("IAMG_API_URL_BACKUP", "https://infoaesthetics.ca/apps/IAMG/com");

    

//Define posttype and admin menus
require_once IAMG_INCLUDES_PATH . 'IAMG_posttype.php';

if (is_admin()) {
    require_once IAMG_INCLUDES_PATH . 'IAMG_submenue.php';
}

require_once IAMG_INCLUDES_PATH . 'IAMG_GalleryUpdate.php';//Checks for a plugin update
require_once IAMG_INCLUDES_PATH . 'IAMG_Activation.php';//Adds crone jobs to check if app library must be updated
require_once IAMG_INCLUDES_PATH . 'IAMG_LibHandler.php';
require_once IAMG_INCLUDES_PATH . 'IAMG_ComDispatcher.php';
//This must be after IAMG_ComDispatcher.php
if (is_admin()) {
    require_once IAMG_INCLUDES_PATH . 'block/IAMG_Block.php';
    require_once IAMG_INCLUDES_PATH . 'IAMG_admin_notices.php';
}
require_once IAMG_INCLUDES_PATH . 'IAMG_App_Loader.php';







