<?php
/*
 * Copyright © 2024  Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

/*
 * Copyright © 2023  Information Aesthetics. All rights reserved.
* This work is licensed under the GPL2, V2 license.
 */

if (!defined('WPINC')) {
    die;
}
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed


require_once(IAMG_CLASSES_PATH . 'IAMG_Client.php');
require_once(IAMG_CLASSES_PATH . 'IAMG_AppSettingsBuilder.php');
require_once(IAMG_CLASSES_PATH . 'IAMG_Nonce.php');


//use IAMG\IAMG_AdminNotice;
use IAMG\IAMG_AppSettingsBuilder;
use IAMG\IAMG_Client;
use IAMG\IAMG_Nonce;

class IAMG_App_Loader
{
    const LZ_VERSION = '1.4.4';
    private $scripts_enqueued = false;

    private $client;

//    const USE_MINIFIED = false;
    const USE_MINIFIED = true;

    function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'load_IAMG_Scripts']);
        add_action('admin_enqueue_scripts', [$this, 'load_IAMG_Scripts_Admin']);
        add_shortcode('ia_magic_gallery', [$this, 'shortcode_render']);
        add_filter('single_template', [$this, 'load_post_template']);

        add_action('init',
            function () {
                if (!defined('DOING_AJAX') || !DOING_AJAX) {
                    IAMG_Nonce::setNonce();
                }
            });

        add_action('after_setup_theme', function () {
//            global $_wp_theme_features;
            if (!(current_theme_supports('align-wide'))) {
                add_theme_support('align-wide');
            }
        });

        //create a new action hook
        add_action('iamg_enqueue_script', [$this, 'enqueue_script']);

        // IA Presenter can be quite large. We want the client to cache its files when visiting the site,
        // even if the page does not contain any IA Presenter content. A special boot script is used to request the
        // resources from the server five seconds after the page has loaded.
        $this->script_caching();
    }


    function load_post_template($template)
    {
        global $post;

        if ($post->post_type === IAMG_POST_TYPE) {
            $this->enqueue_script(null);
            $template = IAMG_PATH . "templates/post.php";
        }

        return $template;
    }

    function enqueue_script($post_scripts = null, $video = true, $width = 680, $height = 600)
    {

        if ($this->scripts_enqueued) {
            return;
        }
        $this->scripts_enqueued = true;

        //handles lz compression needed for the app loader.
        wp_register_script('lz-string',
            IAMG_URL . $this->script_selector('js/dist/lz-string'), [], self::LZ_VERSION, 'in_footer');

        //The app loader. As small script that checks if IA Presenter is needed in the page and loads any prerequisite scrips (including Mousetrap.js, simple-statistics.js, math.js and minify.json.js), as well as a secured version of IA Presenter, which adds the global objects: ID_Designer, Snap_ia, eve_ia, and mina.
        wp_register_script("IAPresenter_loader", IAMG_URL . $this->script_selector('js/iaPresenter_loader'), [],
            IAMG_VERSION, 'in_footer');

        //A very small script that boots IAPresenter_loader with the localized settings.
        wp_enqueue_script(
            'IAPresenter_boot',
            IAMG_URL . $this->script_selector('js/boot_iamg'),
            array('jquery', 'lz-string', 'IAPresenter_loader'),
            IAMG_VERSION, 'in_footer'
        );

        $app_settings_handler = new IAMG_AppSettingsBuilder();

        $settings = $app_settings_handler->setup_load_json($post_scripts, null, 'linked', false, $width, $height);

        wp_localize_script('IAPresenter_boot', 'iamg_settings',
            $settings
        );

        //todo: add only if video is present
        $this->enqueue_styles($video);

//        wp_enqueue_style('iamg-base-admin-styles', IAMG_URL . 'css/iamg-base.css');
    }

    private function enqueue_parent_style_scrip()
    {
        //todo: think about if we still need this!
        wp_enqueue_script(
            'IAMG_ParentStyleScrip',
            IAMG_URL . $this->script_selector('js/parent_style_setter'),
            [],
            IAMG_VERSION,
            'in_footer'
        );
    }

    public function load_IAMG_Scripts()
    {
        $post_type = strtolower(get_post_type());
        if ($post_type === strtolower(IAMG_POST_TYPE)) {
            IAMG_Nonce::setNonce();
//            print_r("In load_IAMG_Scripts");
            $extra = [[$this->script_selector('presentation_expander'), 'presentation_expander_loaded']];
            $extra = null;
            $this->enqueue_script($extra);
        }
    }

    public function load_IAMG_Scripts_Admin()
    {
        $screen = get_current_screen();
//        add_action('wp_head', function () use ($post_type){echo "In load_IAMG_Scripts " . $post_type;});


        if ($screen->id === strtolower(IAMG_POST_TYPE)) {
            IAMG_Nonce::setNonce();
            $this->load_iamg_editor_files();
        }
    }

    private function load_iamg_editor_files()
    {
        global $post;
        wp_enqueue_media();

        //handles lz compression needed for the app loader.
        wp_register_script('lz-string', IAMG_URL . $this->script_selector('js/dist/lz-string'), [], self::LZ_VERSION,
            'in_footer');

        //The app loader. As small script that checks if IA Presenter is needed in the page and loads any prerequisite scrips (including Mousetrap.js, simple-statistics.js, math.js and minify.json.js), as well as a secured version of IA Presenter, which adds the global objects: ID_Designer, Snap_ia, eve_ia, and mina.
        wp_register_script('IAPresenter_loader', IAMG_URL . $this->script_selector('js/iaPresenter_loader'), [],
            IAMG_VERSION, 'in_footer');

        wp_register_script('save-monitor', IAMG_URL . $this->script_selector('js/save_monitor'), array(),
            IAMG_VERSION, 'in_footer');

        //A very small script that boots IAPresenter_loader with the localized settings.
        wp_enqueue_script(
            'IAPresenter_boot',
            IAMG_URL . $this->script_selector('js/boot_iamg_post_admin'),
            array('jquery', 'lz-string', 'save-monitor', 'IAPresenter_loader'),
            IAMG_VERSION, 'in_footer'
        );

        //Add the gallery builder presentation. This is the main GUI that is used to build the gallery.

        $version = get_option(IAMG_SLUG . IAMG_Client::ADMIN_EDITOR_VERSION);
        $initial_graphics = admin_url('admin-ajax.php') . "?action=iamg_builder_pres&v=" . $version;
        $app_settings_handler = new IAMG_AppSettingsBuilder($initial_graphics);

        $iamg_settings = $app_settings_handler->setup_load_json([
//                    [$this->script_selector('presentation_expander'), 'presentation_expander_loaded']
        ],
            $app_settings_handler->get_editor_resources(),
            'linked',
            true
        );
        //If there is an exising gallery for this post, we need to load the settings for it so that GUI can initialize its state.
        $local_id = get_post_meta($post->ID, 'id_local', true);
        $params = get_post_meta($post->ID, "presentation_parameters", true);

        $iamg_settings["id"] = $local_id;
        $iamg_settings["gallery_properties"] = $params;
        $iamg_settings["post_id"] = $post->ID;

        wp_localize_script('IAPresenter_boot', 'iamg_settings',
            $iamg_settings
        );

        $this->enqueue_styles();
    }

    public function enqueue_script_for_caching()
    {
        if ($this->scripts_enqueued) {
            return;
        }
        $this->scripts_enqueued = true;

//        print_r("In Caching Code: <br>");
        IAMG_Nonce::setNonce();

        //handles lz compression needed for the app loader.
        wp_register_script('lz-string', IAMG_URL . $this->script_selector('js/dist/lz-string'), [],
            self::LZ_VERSION,
            'in_footer');

        //The app loader. As small script that checks if IA Presenter is needed in the page and loads any prerequisite scrips (including Mousetrap.js, simple-statistics.js, math.js and minify.json.js), as well as a secured version of IA Presenter, which adds the global objects: ID_Designer, Snap_ia, eve_ia, and mina.
        wp_register_script("IAPresenter_loader", IAMG_URL . $this->script_selector('js/iaPresenter_loader'), [],
            IAMG_VERSION, 'in_footer');

        //A very small script that boots IAPresenter_loader only to force resources to be cached.
        wp_enqueue_script(
            'IAPresenter_boot',
            IAMG_URL . $this->script_selector('js/boot_iamg_cache'),
            array('jquery', 'lz-string', 'IAPresenter_loader'),
            IAMG_VERSION, 'in_footer'
        );

        wp_localize_script('IAPresenter_boot', 'iamg_settings',
            [
                "settings" => ['pre_scripts' => (new IAMG_AppSettingsBuilder())->get_app_link()],
                "resources" => IAMG_JS_URL
            ]);

    }

    public
    function shortcode_render(
        $atts
    ) {
//        convert hyphens in keys to underscores
        $atts = array_combine(
            array_map(function ($key) {
                return str_replace('-', '_', $key);
            }, array_keys($atts)),
            $atts
        );

        if (!function_exists('get_background_color')) {
            require_once(ABSPATH . 'wp-admin/includes/template.php');
        }
        $color = get_background_color();
        if (!$color) {
            $color = "#ffffff";
        }

        $a = shortcode_atts(array(
            'id' => "155", //get demo code (todo)
            'behavior' => 'fixed',
            'height' => null,
            'height_type' => 'pixel',
            'width' => null,
            'width_type' => 'pixel',
            'max_width' => null,
            'resize_time' => null,
            'background_color' => $color,
            'background_opacity' => 1
        ), $atts);


//        $post = get_post($a['id']);

        $id = esc_attr($a['id']);
        $behavior = esc_attr($a['behavior']);
//        print_r($behavior);
        $additional_scripts = [];
        $additional_attributes = [];
        $style_css = '';
        $block_class = "block-" . $id;

        $opacity = $a['background_opacity'];
        if (is_numeric($opacity) && $opacity > 0) {
            if ($opacity > 1) {
                $opacity /= 100;
            }
            $color = $a["background_color"];
            $color = $this->toColor($color);
            if ($color) {
                $style_css = '<style id="' . esc_attr($block_class) . '"> .' . esc_attr($block_class)
                    . ' .IA_Designer_Panel_Background{fill:' . esc_attr($color)
                    . '; fill-opacity: ' . esc_attr($opacity) . '; opacity: ' . esc_attr($opacity) . ';}</style>';
            }
        }

//        print_r("Style" . $style_css);

        $max_width = !empty($a['max-width']) ? $a['max-width'] : $a['max_width'];

        switch ($behavior) {
            case 'full':
                $additional_scripts[] = [$this->script_selector('presentation_full'), 'presentation_full_loaded'];
                break;
            case 'fixed':
                $additional_scripts[] = [$this->script_selector('iamg_helper')];
//                $additional_scripts[] = [
//                    $this->script_selector('presentation_expander'),
//                    'presentation_expander_loaded'
//                ];
//                print_r($behavior);

                $this->enqueue_parent_style_scrip();
                $style = [];
                $style['position'] = 'relative';
                if ($a['width']) {
                    $w = (int)$a['width'];
                    if ($a['width_type'] !== 'pixel') {
//                    width:400px;margin - left:calc(50 % -200px)
                        $style["width"] = $w . "vw";
                        $style["margin-left"] = "calc(50% - " . ($w / 2) . "vw)";
                    } else {
                        $style["width"] = $w . "px";
                        $style["margin-left"] = "calc(50% - " . ($w / 2) . "px)";
                    }
                }

                if ($max_width) {
                    if ($a['width_type'] !== 'pixel' && $a['width_type'] !== 'px') {
//                    width:400px;margin - left:calc(50 % -200px)
                        $style["max-width"] = $max_width . "vw";
                    } else {
                        $style["max-width"] = $max_width . "px";
                    }
                }

                if ($a['height']) {
                    $h = (int)$a['height'];
                    if ($a['height_type'] !== 'pixel') {
                        $style["height"] = $h . "vh";
                    } else {
                        $style["height"] = $h . "px";
                    }
                } else {
                    $style["height"] = "100vh";
                }

                if ($style) {
                    $additional_attributes['style'] = $style;
                }

                break;
            case 'adaptive':
                $additional_scripts[] = [
                    $this->script_selector('presentation_expander'),
                    'presentation_expander_loaded'
                ];
                $this->enqueue_parent_style_scrip();

                if ($a['width']) {
                    $w = (int)$a['width'];
                    if ($a['width_type'] !== 'pixel') {
//                    width:400px;margin - left:calc(50 % -200px)
                        $additional_attributes['width'] = $w . "%";
                    } else {
                        $additional_attributes['width'] = $w;
                    }
                }

                if ($max_width) {
                    if ($a['width_type'] !== 'pixel' && $a['width_type'] !== 'px') {
//                    width:400px;margin - left:calc(50 % -200px)
                        $style["max-width"] = $max_width . "vw";
                    } else {
                        $style["maxwidth"] = $max_width . "px";
                    }
                }

                $style = [];
                $style['position'] = 'relative';

                if ($a['height']) {
                    $h = (int)$a['height'];
                    if ($a['height_type'] !== 'pixel') {
                        $style["height"] = $h . "vh";
                    } else {
                        $style["height"] = $h . "px";
                    }
                }
                if ($style) {
                    $additional_attributes['style'] = $style;
                }

                if ($a['resize_time']) {
                    $additional_attributes['resize-time'] = esc_attr($a['resize_time']);
                }

                break;
        }

//        print_r(json_encode($additional_attributes) . "<br>");

        $pres = IAMG_posttype::get_post_presentation($id);
        $content = IAMG_posttype::render_post($pres, $behavior, $additional_attributes, "", false);

        $result = null;

        if ($content) {
            $result = $content;
        } else {
            $post = IAMG_posttype::get_post($id);
            if ($post && $post->post_type === IAMG_POST_TYPE) {
                $result = $post->post_content;
            }
        }
        if ($result) {
            $this->enqueue_script($additional_scripts, true, null, null);

            $needle = 'class="';
            $pos = strpos($result, $needle);
            if ($pos !== false) {
                $result = substr_replace($result, $needle . $block_class . " ", $pos, strlen($needle));
            }

            return $style_css . $result;
        }
//        print_r("No content for post" . json_encode((array)$atts) . "<br>");
//        print_r("No content for post " . $id . " " . json_encode((array)$pres) . "<br>");
        return null;
    }

    /**
     * @return void
     */
    private
    function enqueue_styles(
        $video = true
    ): void {
        if (WP_DEBUG) {
            wp_enqueue_style(
                'iamg-def-styles',
                IAMG_URL . 'css/ia_designer_general.css',
                [], IAMG_VERSION
            );
            wp_enqueue_style(
                'iamg-def-styles_pres',
                IAMG_URL . 'css/ia_presenter_general.css',
                [], IAMG_VERSION
            );
        } else {
            wp_enqueue_style(
                'iamg-def-styles',
                IAMG_URL . 'css/ia_general.min.css',
                [], IAMG_VERSION
            );
        }

        if (is_admin()) {
            wp_enqueue_style(
                'iamg-admin-styles',
                IAMG_URL . 'css/ia_presenter_admin.css',
                [], IAMG_VERSION
            );
        }

        //Todo: add only if video is present
        if ($video) {
            wp_enqueue_style(
                'video-js',
                IAMG_URL . 'css/video-js.css',
                //            IAMG_URL . 'css/iamg-base.css'
                [], IAMG_VERSION
            );
        }
    }

    private
    function get_client(): IAMG_Client
    {
        if (!$this->client) {
            $this->client = new IAMG_Client();
        }
        return $this->client;
    }

    /**
     * @return void
     */
    private
    function script_caching(): void
    {
        // Expiration time of the script, usually less than 7 days from now
        $time_script = $this->get_client()->get_app_time();
        if (!isset($_COOKIE["iamg_lib_loaded"]) || (int)$_COOKIE["iamg_lib_loaded"] < $time_script) {
            setcookie("iamg_lib_loaded", $time_script, $time_script, '/');
            add_action('wp_footer', [$this, 'enqueue_script_for_caching']);
        }
    }

    private
    function script_selector(
        $name
    ) {
        if (self::USE_MINIFIED) {
            return $name . ".min.js";
        } else {
            return $name . ".js";
        }
    }

    private
    function is_valid_hex_color(
        $color
    ) {
        return preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $color);
    }


    private static $colorNames = array(
        'antiquewhite' => '#faebd7',
        'aqua' => '#00ffff',
        'aquamarine' => '#7fffd4',
        'beige' => '#f5f5dc',

        'blue' => '#0000ff',
        'brown' => '#a52a2a',
        'cadetblue' => '#5f9ea0',
        'chocolate' => '#d2691e',
        'cornflowerblue' => '#6495ed',
        'crimson' => '#dc143c',
        'darkblue' => '#00008b',
        'darkgoldenrod' => '#b8860b',
        'darkgreen' => '#006400',
        'darkmagenta' => '#8b008b',
        'darkorange' => '#ff8c00',
        'darkred' => '#8b0000',
        'darkseagreen' => '#8fbc8f',
        'darkslategray' => '#2f4f4f',
        'darkviolet' => '#9400d3',
        'deepskyblue' => '#00bfff',
        'dodgerblue' => '#1e90ff',
        'firebrick' => '#b22222',
        'forestgreen' => '#228b22',
        'fuchsia' => '#ff00ff',
        'gainsboro' => '#dcdcdc',
        'gold' => '#ffd700',
        'gray' => '#808080',
        'green' => '#008000',
        'greenyellow' => '#adff2f',
        'hotpink' => '#ff69b4',
        'indigo' => '#4b0082',
        'khaki' => '#f0e68c',
        'lavenderblush' => '#fff0f5',
        'lemonchiffon' => '#fffacd',
        'lightcoral' => '#f08080',
        'lightgoldenrodyellow' => '#fafad2',
        'lightgreen' => '#90ee90',
        'lightsalmon' => '#ffa07a',
        'lightskyblue' => '#87cefa',
        'lightslategray' => '#778899',
        'lightyellow' => '#ffffe0',
        'lime' => '#00ff00',
        'limegreen' => '#32cd32',
        'magenta' => '#ff00ff',
        'maroon' => '#800000',
        'mediumaquamarine' => '#66cdaa',
        'mediumorchid' => '#ba55d3',
        'mediumseagreen' => '#3cb371',
        'mediumspringgreen' => '#00fa9a',
        'mediumvioletred' => '#c71585',
        'midnightblue' => '#191970',
        'mintcream' => '#f5fffa',
        'moccasin' => '#ffe4b5',
        'navy' => '#000080',
        'olive' => '#808000',
        'orange' => '#ffa500',
        'orchid' => '#da70d6',
        'palegreen' => '#98fb98',
        'palevioletred' => '#d87093',
        'peachpuff' => '#ffdab9',
        'pink' => '#ffc0cb',
        'powderblue' => '#b0e0e6',
        'purple' => '#800080',
        'red' => '#ff0000',
        'royalblue' => '#4169e1',
        'salmon' => '#fa8072',
        'seagreen' => '#2e8b57',
        'sienna' => '#a0522d',
        'silver' => '#c0c0c0',
        'skyblue' => '#87ceeb',
        'slategray' => '#708090',
        'springgreen' => '#00ff7f',
        'steelblue' => '#4682b4',
        'tan' => '#d2b48c',
        'teal' => '#008080',
        'thistle' => '#d8bfd8',
        'turquoise' => '#40e0d0',
        'violetred' => '#d02090',

        'yellow' => '#ffff00',
        'aliceblue' => '#f0f8ff',
        'azure' => '#f0ffff',
        'bisque' => '#ffe4c4',
        'blanchedalmond' => '#ffebcd',
        'blueviolet' => '#8a2be2',
        'burlywood' => '#deb887',
        'chartreuse' => '#7fff00',
        'coral' => '#ff7f50',
        'cornsilk' => '#fff8dc',
        'cyan' => '#00ffff',
        'darkcyan' => '#008b8b',
        'darkgray' => '#a9a9a9',
        'darkgrey' => '#a9a9a9',
        'darkkhaki' => '#bdb76b',
        'darkolivegreen' => '#556b2f',
        'darkorchid' => '#9932cc',
        'darksalmon' => '#e9967a',
        'darkslateblue' => '#483d8b',
        'darkslategrey' => '#2f4f4f',
        'darkturquoise' => '#00ced1',
        'deeppink' => '#ff1493',
        'dimgray' => '#696969',
        'dimgrey' => '#696969',
        'floralwhite' => '#fffaf0',
        'ghostwhite' => '#f8f8ff',
        'goldenrod' => '#daa520',
        'grey' => '#808080',
        'honeydew' => '#f0fff0',
        'indianred' => '#cd5c5c',
        'ivory' => '#fffff0',
        'lavender' => '#e6e6fa',
        'lawngreen' => '#7cfc00',
        'lightblue' => '#add8e6',
        'lightcyan' => '#e0ffff',
        'lightgray' => '#d3d3d3',
        'lightgrey' => '#d3d3d3',
        'lightpink' => '#ffb6c1',
        'lightseagreen' => '#20b2aa',
        'lightslategrey' => '#778899',
        'lightsteelblue' => '#b0c4de',
        'linen' => '#faf0e6',
        'mediumblue' => '#0000cd',
        'mediumpurple' => '#9370db',
        'mediumslateblue' => '#7b68ee',
        'mediumturquoise' => '#48d1cc',
        'mistyrose' => '#ffe4e1',
        'navajowhite' => '#ffdead',
        'oldlace' => '#fdf5e6',
        'olivedrab' => '#6b8e23',
        'orangered' => '#ff4500',
        'palegoldenrod' => '#eee8aa',
        'paleturquoise' => '#afeeee',
        'papayawhip' => '#ffefd5',
        'peru' => '#cd853f',
        'plum' => '#dda0dd',
        'rosybrown' => '#bc8f8f',
        'saddlebrown' => '#8b4513',
        'sandybrown' => '#f4a460',
        'seashell' => '#fff5ee',
        'slateblue' => '#6a5acd',
        'slategrey' => '#708090',
        'snow' => '#fffafa',
        'tomato' => '#ff6347',
        'violet' => '#ee82ee',
        'wheat' => '#f5deb3',
        'whitesmoke' => '#f5f5f5',
        'yellowgreen' => '#9acd32',

        'black' => '#000000',
        'white' => '#ffffff'
    );

    private function toColor($color)
    {
        if ($this->is_valid_hex_color($color)) {
            return $color;
        }

        $color = strtolower($color);
        if (array_key_exists($color, self::$colorNames)) {
            return self::$colorNames[$color];
        }

        return null;
    }

}

new IAMG_App_Loader();


