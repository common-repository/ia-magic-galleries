<?php
/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

use IAMG\IAMG_Client;
use IAMG\IAMG_ImageHandler;

if (!defined('WPINC')) {
    exit;
}
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed

//require_once IAMG_INCLUDES_PATH . "Gallery_Gen_Link.php";

require_once __DIR__ . '/../src/autoload.php';

class IAMG_ComDispatcher
{

//    private $gen_link;
    private $slug = IAMG_SLUG;
    private $jsonData;

    /**
     * @var array[] $settings_defs global settings for the plugin
     * //todo: move to separate class in future versions when more settings are available
     */
    static private $settings_defs = [
        'preserve_posts' => [
            'option' => 'preserve_posts_on_uninstall', //option name
            'type' => true, //type of value, as example
            'autoload' => false
        ],
//        'test' => [
//            'option' => 'test_for_settings', //option name
//            'type' => "string", //type of value as example
//            'autoload' => false
//        ],
    ];

    function __construct()
    {

        //The main API link
        add_action('wp_ajax_iamg_com', [$this, 'dispatcher']);

        if (defined('IAMG_DEBUG_SKIP_SECURITY') && IAMG_DEBUG_SKIP_SECURITY) {
            add_action('wp_ajax_nopriv_iamg_com', [$this, 'dispatcher']);
        }

        //Loading the IA Presenter app in secure format, this is processed by iaPresenter_loader.js
        add_action('wp_ajax_iamg_app', [$this, 'load_app']);
        add_action('wp_ajax_nopriv_iamg_app', [$this, 'load_app']);

        //Passes the presentation defining the gallery builder GUI
        add_action('wp_ajax_iamg_builder_pres', [$this, 'builder_presentation']);

//        add_action('wp_ajax_iamg_pres', [$this, 'presentation']); //old way
//        add_action('wp_ajax_nopriv_iamg_pres', [$this, 'presentation']);

        add_filter('wp_prepare_attachment_for_js', [$this, '_attachment_monitor_filter']);
        add_filter('wp_insert_attachment_data', [$this, '_attachment_metadata_monitor_filter']);

        //Overwrite Cache-Control header for ajax requests of iamg_app and iamg_builder_pres actions.
        // Ajax requests are not cached by default, but the app and builder presentation are large and are static for about a week.
        if (defined('DOING_AJAX') && DOING_AJAX) {
            add_filter('nocache_headers', function ($headers) {
                $action = isset($_GET['action']) ? $_GET['action'] : (
                isset($_POST['action']) ? $_POST['action'] : null
                );
                $action = sanitize_text_field($action);

                //action in [iamg_builder_pres, iamg_app]
                if ($action && in_array($action, ['iamg_builder_pres', 'iamg_app'])
                    && $this->_verify_nonce(['iamg_direct'])
                ) {
                    $headers['Cache-Control'] = 'max-age=31536000';
                    $headers['Expires'] = gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT';
                }
                return $headers;
            });
        }

//        add_action('save_post', [$this, 'save_post_monitor_filter']); //old way
    }

    public function __call(string $name, array $arguments)
    {
        return null;
    }

    /**
     * Dispatcher function for the main API used by the gallery builder GUI. It looks at 'command' key,
     * finds a method with the same name and calls it.
     * The method should return an array that will be sent to the client as JSON.
     * Non-dispacheable methods in this class must be prefixed with '_'.
     * @return void
     */
    public function dispatcher()
    {
        if ( $this->is_allowed()) {
            $output = null;
            $command = $this->_get_command();
            if ($command && $command[0] !== '_' && $command !== __FUNCTION__ && method_exists($this, $command)) {
                $output = $this->$command();
                wp_send_json($output);
            }
            if ($output) {
                wp_send_json($output);
            }
        }

        wp_die();
    }

    private function _get_command()
    {
        return $this->_get_param('command');
    }

    /**
     * A utility function to get a parameter from POST, GET, COOKIE or JSON data
     * The php://input may be processed only once, so the JSON data is stored in $this->jsonData.
     * nonce is checked by the appropriate calling function.
     *
     * @param string $param
     * @return string|null
     */
    private function _get_param($param)
    {
        //nonce is checked by the appropriate function
        if (isset($_POST[$param]) && $_POST[$param]) {
            return sanitize_text_field($_POST[$param]);
        }
        if (isset($_GET[$param]) && $_GET[$param]) {
            return sanitize_text_field($_GET[$param]);
        }
        //Check in cookie
        if (isset($_COOKIE[$param]) && $_COOKIE[$param]) {
            return sanitize_text_field($_COOKIE[$param]);
        }

        if (!$this->jsonData) {
            $jsonData = file_get_contents("php://input");
            if ($jsonData) {
                $this->jsonData = json_decode($jsonData, true);
            }
            if (!$this->jsonData) {
                $this->jsonData = [null];
            }
        }
        if (isset($this->jsonData[$param])) {
            $data = $this->jsonData[$param];
            if (is_string($data)) {
                //we should never be here, because we would have captured the data above already, but just in case
                return sanitize_text_field($data);
            }
            return $data;
        }

        return null;
    }

    /**
     * Serve the IA Presenter app to client
     * @return void
     */
    public function load_app()
    {

        if (!$this->_verify_nonce(['iamg_direct', 'iamg_admin_direct'])) {
            wp_die();
        }

        $client = new IAMG_Client();
        $exp = $client->get_app_time(); //expiration time of the app.
        $cache_time = $exp - round(microtime(true));
        header("Cache-Control: max-age=" . $cache_time);  //This is not necessary because the client will cache the app in local storage, but we can add it for redundancy.
        $is_editor = $this->is_allowed() && (isset($_GET['editor']) || isset($_POST['editor']));
        wp_send_json($client->get_app($is_editor));

        wp_die();
    }

    /**
     * Serve a presentation directly
     * Serve the IA Presenter app to client
     * @return void
     */
//    function presentation()
//    {
//        $id = $this->_get_param("id");
//        if ($id) {
//            $content = IAMG_posttype::get_post_presentation($id);
//
//            if ($content) {
//                header('Content-type: image/svg+xml');
////                header('Content-Type: text/plain');
//                echo $content;
//            }
//        }
//
//        wp_die();
//    }

    /**
     * Serv the Admin presentation for building galleries directly
     * @return void
     */
    public function builder_presentation()
    {
        if (!$this->_verify_nonce(['iamg_direct', 'iamg_admin_direct'])
        || (!$this->is_allowed())) {
            wp_die();
        }

        //plane text header
        header('Content-Type: text/plain', true);
        //Send instruction to client to cache the presentation
        header('Cache-Control: max-age=31536000', true);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT', true);


        $client = new IAMG_Client();

        //Snap_ia can load a presentation from base64 encoded LZ compressed string. Its needs to be prefixed with 'LZBase64:'
        echo esc_attr('LZBase64:' . $client->get_admin_presentation());
        wp_die();
    }

    public function reset()
    {
        //todo
    }

    /**
     * Save a gallery from temporary storage to permanent storage, making it a post
     * @return void
     */
    public function save()
    {
        if (!$this->_verify_nonce(['iamg_direct', 'iamg_admin_direct'])) {
            wp_send_json(['error' => 'Save Access Denied']);
        }
        //secure save
        $block_id = $this->_get_param('block_id');
        if ($block_id && !$this->_validate_block_id($block_id)) {
            wp_send_json(['error' => 'Invalid Block ID']);
        }
        $pres_id = $this->_get_param('pres_id');
        if ($pres_id && !$this->_validate_press_id($pres_id)) {
            wp_send_json(['error' => 'Invalid Presentation ID']);
        }

        $post_id = $this->_get_param('post_id');
        //validate post_id to be a number
        if ($post_id && !is_numeric($post_id)) {
            wp_send_json(['error' => 'Invalid Post ID']);
        }

        $page_id = $this->_get_param('page_id');
        //validate page_id to be a number or null
        if ($page_id && !is_numeric($page_id)) {
            wp_send_json(['error' => 'Invalid Page ID']);
        }

        $title = sanitize_title($this->_get_param('title'));
        $locator = $this->_get_param('locator');
        //validate locator to be a md5 hash or null
        if ($locator && !preg_match('/^[a-f0-9]{32}$/', $locator)) {
            wp_send_json(['error' => 'Invalid Locator']);
        }
        $is_gallery_post = $this->_get_param('is_gallery_post');


        $user_id = get_current_user_id();

        if (!$pres_id) {
            $gallery_number = IAMG_posttype::get_gallery_count();
            $pres_id = "iamg_gallery_" . ($gallery_number + 1);
        }

        if ($locator) { // we should always have a locator
            $settings = (new IAMG_Client())->set_gallery_to_post($locator, $pres_id, $title, $block_id, $page_id,
                $post_id,
                $is_gallery_post);
            if ($settings === 'expired') {
                wp_send_json([
                    'pres_id' => $pres_id,
                    'user_id' => $user_id,
                    'success' => false,
                    'status' => 'expired']);
            } elseif ($settings) {
                wp_send_json([
                    'pres_id' => $pres_id,
                    'user_id' => $user_id,
                    'success' => true,
                    'settings' => IAMG_ImageHandler::sanitize($settings)
                ]);
            } else {
                wp_send_json(['pres_id' => $pres_id, 'user_id' => $user_id, 'success' => false, 'status' => 'error']);
            }
        }

        //just for testing, we should never get here
//        wp_send_json(['pres_id' => $pres_id, 'user_id' => $user_id]);
    }

    public function remove()
    {
        //for now the user will remove galleries from the admin panel
        // if we want to respond to removal of blocks containing galleries,
        //we will handle the removal here
    }

    /**
     * Send image to the library after request
     * @return array
     */
    public function images()
    {

        if ( !$this->_verify_nonce(['iamg_direct', 'iamg_admin_direct'])) {
            return ['error' => 'Images Access Denied'];
        }

        $start = (int)$this->_get_param('start');
        $num_images = $this->_get_param('number_results');
        if (!is_numeric($num_images)) {
            $num_images = null;
        } else {
            $num_images = (int)$num_images;
        }


        $album = $this->_get_param('album'); //$album is validated by user functions

        require_once IAMG_CLASSES_PATH . 'IAMG_ImageHandler.php';

        $imageHandler = new IAMG_ImageHandler();

        return $imageHandler->get_for_library($start, $num_images, false, $album);
    }

    public function image_albums()
    {
        if ( !$this->_verify_nonce(['iamg_direct', 'iamg_admin_direct'])) {
            return ['error' => 'Images Albums Access Denied'];
        }

        require_once IAMG_CLASSES_PATH . 'IAMG_ImageHandler.php';

        $imageHandler = new IAMG_ImageHandler();

        return $imageHandler->get_album_names();
    }

    /**
     * Send video to the library after request
     * @return array
     */
    public function videos()
    {
        if (!$this->_verify_nonce(['iamg_direct', 'iamg_admin_direct'])) {
            return ['error' => 'Videos Access Denied'];
        }

        $start = (int)$this->_get_param('start');
        $num_images = $this->_get_param('number_results');
        //todo: add albums

        if (!is_numeric($num_images)) {
            $num_images = null;
        } else {
            $num_images = (int)$num_images;
        }

        require_once IAMG_CLASSES_PATH . 'IAMG_ImageHandler.php';

        $imageHandler = new IAMG_ImageHandler(true);

//        wp_send_json($imageHandler->get_for_library($start, $num_images, true));
        return $imageHandler->get_for_library($start, $num_images, true);
    }

    /**
     * Gather images for gallery and send the request to generate it to the IA SAS
     * @return array
     */
    public function make_gallery()
    {
        if (!$this->_verify_nonce(['iamg_direct', 'iamg_admin_direct'])) {
            return ['error' => 'Gallery Access Denied'];
        }

        $type = $this->_get_param("gallery_type");
        //validate type for being a single alphanumeric word but not numbers only
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $type)) {
            return ['error' => 'Invalid Gallery Type'];
        }
        $images = $this->_get_param("images");
        //validate images for being an array and the first element is an array with keys 'id'
        if (!is_array($images) || !isset($images[0]) || !isset($images[0]['id'])) {
            return ['error' => 'Invalid Images'];
        }
        $resource = $this->_get_param("resource");
        //validate resource for being an array or null
        if ($resource && !is_array($resource)) {
            return ['error' => 'Invalid Resource'];
        }
        $settings = $this->_get_param("settings");
        //validate settings for being an array or null
        if ($settings && !is_array($settings)) {
            return ['error' => 'Invalid Settings'];
        }

        if (!$settings) {
            $settings = [];
        }

        $imageHandler = new IAMG_ImageHandler("all");

        $image_info = $imageHandler->get_for_gallery($images);

        $settings["type"] = $type;
        $settings["images"] = $image_info;
        $settings["requested_images"] = $images;
        if ($resource) {
            $settings["resource"] = $resource;
        }

        $gallery = (new IAMG_Client())->get_gallery($settings);

        $result = [];
        if (isset($gallery['svg'])) {
            $result["svg"] = $gallery['svg'];
        }
        if (isset($gallery['locator'])) {
            $result['locator'] = $gallery['locator'];
        }
        if (isset($gallery['stored_settings'])) {
            $result['stored_settings'] = $gallery['stored_settings'];
        }
        if (isset($gallery['error'])) {
            $result['error'] = $gallery['error'];
        }
        if (!$gallery) {
            $result['error'] = esc_html__('No Response received from Server', 'ia-magic-galleries');
        }

        $result['settings'] = $settings;

        return $result;
    }

    private function _cast_to_same_type($source, $target)
    {
        switch (gettype($target)) {
            case 'boolean':
                $source = (bool)$source;
                break;
            case 'integer':
                $source = (int)$source;
                break;
            case 'float':
                $source = (float)$source;
                break;
            case 'string':
                $source = (string)$source;
                break;
            case 'array':
                $source = explode('+', $source);
                break;
            default:
        }
        return $source;
    }

    /**
     * Change global settings for the plugin
     * todo: primarily to future use
     * @return array
     */
    public function settings()
    {
        if (!$this->_verify_nonce(['iamg_direct', 'iamg_admin_direct']) ||
            !current_user_can('manage_options')) {
            return ['error' => 'Settings Access Denied'];
        }
        $updated = [];
        foreach (self::$settings_defs as $sett => $def) {
            $setting_val = $this->_get_param($sett);
            if ($setting_val !== null) {
                $updated[$sett] = $setting_val;
                $autoload = !!$def['autoload'];
                $val = $this->_cast_to_same_type($setting_val, $def['type']);
                update_option($this->slug . '_' . $def['option'], $val, $autoload);
            }
        }
        return $updated;
    }

    public static function _get_setting_option_name($setting)
    {
        if (isset(self::$settings_defs[$setting])) {
            return IAMG_SLUG . '_' . self::$settings_defs[$setting]['option'];
        }
        return '';
    }

    private function _verify_nonce($actions)
    {
        // if IAMG_DEBUG_SKIP_NONCE is defined, we skip nonce verification
        if (defined('IAMG_DEBUG_SKIP_SECURITY') && IAMG_DEBUG_SKIP_SECURITY) {
            return true;
        }

        $nonce = sanitize_text_field(wp_unslash($this->_get_param('_iamgnonce')));

        if (!is_array($actions)) {
            $actions = [$actions];
        }
        for ($i = 0; $i < count($actions); $i++) {
            if (wp_verify_nonce($nonce, $actions[$i])) {
                return true;
            }
        }

        return false;
    }

    private function _validate_block_id($block_id)
    {
        //$block_id is alphanumeric string with hyphens
        return preg_match('/^[a-zA-Z0-9-]+$/', $block_id);
    }

    private function _validate_press_id($press_id)
    {
        //$press_id is alphanumeric string with hyphens and underscores without spaces
        return preg_match('/^[a-zA-Z0-9-_]+$/', $press_id);
    }

    //Monitors
    public function _attachment_monitor_filter($post = null)
    {
        $this->_recordMediaChange($post['type']);
        return $post;
    }

    public function _attachment_metadata_monitor_filter($data)
    {

        if ($data && isset($data['post_type']) && $data['post_type'] === 'attachment') {
            $mime = (isset($data['post_mime_type'])) ? $data['post_mime_type'] : "";
            $type = explode('/', $mime)[0];
            $this->_recordMediaChange($type);
        }

        return $data;
    }

    function _save_post_monitor_filter($id, $post)
    {
        $this->_recordMediaChange($post['type']);
    }

    /**
     * @param $type
     * @return void
     */
    private function _recordMediaChange($type): void
    {
        if ($type == "image") {
            if (get_option($this->slug . "_last_image_update")) {
                update_option($this->slug . "_last_image_update", microtime(true), '', false);
            } else {
                add_option($this->slug . "_last_image_update", microtime(true), '', false);
            }
        }
        if ($type == "video") {
            if (get_option($this->slug . "_last_video_update")) {
                update_option($this->slug . "_last_video_update", microtime(true), '', false);
            } else {
                add_option($this->slug . "_last_video_update", microtime(true), '', false);
            }
        }
    }

    /**
     * @return bool
     */
    private function is_allowed(): bool
    {
        if (defined('IAMG_DEBUG_SKIP_SECURITY') && IAMG_DEBUG_SKIP_SECURITY) {
            return true;
        }

        return is_admin() && is_user_logged_in() && current_user_can('edit_posts');
    }
}

new IAMG_ComDispatcher();