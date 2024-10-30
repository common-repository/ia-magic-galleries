<?php
/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

namespace IAMG;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed

/**
 * Class Client handles the communication with the IA SAS server and manages formatting and storage of the data in the
 * WordPress option table. All critical data is encrypted and tamper-proved to prevent code insertion.
 *
 * Security procedures:
 * When the plugin registers with the SAS server, it receives a unique public key that is used to encrypt the data sent
 * to the server and decrypt important data from the server to assure it has not been tampered with. The most important data the plugin
 * receives from the SAS server is the IA Presenter Javascript application library, which it caches and servers to the client browser.
 * Old Approach, not used anymore
 * *This library arrives in an encrypted gzip format, which is stored in the database as base64 encoded string.
 * *The library gzip file is further protected after runtime decryption by a SAH1 checksum and length verification.
 * New Approach, currently used
 * The library arrices in a compressed LZBase64 format together with a cryptographic signature based on the
 * 'RSASSA-PKCS1-v1_5' algorithm, with SHA-256 hash. It is cached in the database. The library is passed from the
 * database to iaPresenter_loader.js, which is responsible for the verification of the library. The verification
 * requires the crypto interface of the browser, which is available only in secure environments.
 * The new approach frees the WordPress server from the responsibility of decrypting and verifying the library, as
 * verification is done by the client browser.
 * This makes it impossible to tamper with the library by accessing the database. The IA Presenter application is itself
 * time-limited, and it must be updated from the SAS server once a week or sooner.
 *
 */
class IAMG_Client
{
    const PLAYER_RESOURCES = "_player_resources";
    const EDITOR_RESOURCES = "_editor_resources";
    const APP_SCRIPT = "_app_script";
    const APP_SCRIPT_TIME = '_app_script_time';
    const APP_SCRIPT_UPDATED = '_app_script_updated';
    const APP_SCRIPT_BLOCKS = '_app_script_blocks';
    const APP_SCRIPT_EDITOR = "_app_script_editor";
    const APP_HAS_PRE_SCRIPT = '_app_has_pre_script';
    const APP_PRE_SCRIPT = "_app_pre_script";

    const ADMIN_EDITOR = '_admin_editor';

    const ADMIN_EDITOR_VERSION = '_admin_editor_version';
    const REGISTERED_SERVER = "_registered_server";

    const RESOURCE = '_resource_';
    const RESOURCE_VERSION = '_resource_version_';


    //ERROR conditions

    const ERROR_NO_ACCESS_TO_INTERNET = 1;
    const ERROR_SERVER_NOT_REACHABLE = 2; //cannot resolve ip address
    const ERROR_SERVER_NOT_AVAILABLE = 3;
    const ERROR_CONNECT_TIMEOUT = 4;
    const ERROR_CONNECT_ERROR = 5;

    const ERROR_NETWORK_EXCEPTION = 6;
    const ERROR_LOCAL_SERVER_USER_AGENT_PROBLEM = 7;


    /**
     * Name of the plugin
     *
     * @var string
     */
//    public $name;

    /**
     * The plugin/theme file path
     * @example .../wp-content/plugins/test-slug/test-slug.php
     *
     * @var string
     */
    public $file;

    /**
     * Main plugin file
     * @example test-slug/test-slug.php
     *
     * @var string
     */
    public $basename;


    /**
     * The project version
     *
     * @var string
     */
    public $project_version;

    /**
     * The project type
     *
     * @var string
     */
    public $type;

    /**
     * textdomain
     *
     * @var string
     */
    public $textdomain;


    /**
     * The Object of Updater Class
     *
     * @var object
     */
    private $updater;


    private $routes = [
        "script" => "scripts.php", //route for requesting application resource for IA Presenter
        "register" => "register_iamg.php", //route for registering the client with the server
        "gallery" => "gallery.php" //route for requesting the generation of a galleries
    ];
    /**
     * If the plugin is runnnig in WASM environment
     * @var bool
     */
    private $in_playground = false;

    /**
     * Initialize the class
     *
     * @param string $name readable name of the plugin
     * @param string $file main plugin file path
     */
    public function __construct(
//        $name = "IAMG",
        $file = IAMG_PATH . "IAMagic-galleries.php"
    ) {
//        $this->name = $name;
        $this->file = $file;

        $this->set_basename();

        $this->in_playground = $_SERVER['SERVER_SOFTWARE'] === 'PHP.wasm';
    }

    /**
     * Set project basename, slug and version
     *
     * @return void
     */
    protected function set_basename()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_data = get_plugin_data($this->file);

        $this->basename = plugin_basename(IAMG_PATH . "IAMagic-galleries.php");

        $this->project_version = $plugin_data['Version'];
        $this->type = 'plugin';

        $this->textdomain = IAMG_SLUG;
    }

//    /**
//     * Initialize plugin/theme updater
//     *
//     * @return IAMG\Updater
//     */
//    public function updater()
//    {
//        if (!class_exists(__NAMESPACE__ . '\Updater')) {
//            require_once __DIR__ . '/Updater.php';
//        }
//
//        // if already instantiated, return the cached one
//        if ($this->updater) {
//            return $this->updater;
//        }
//
//        $this->updater = new Updater($this);
//
//        return $this->updater;
//    }
//
//    public function updateIAMG()
//    {
//
//    }

    public function get_app($editor = false, $_retry = true)
    {
//        if (microtime(true) > $this->getAppTime()) {
//            // emergency system if for some reason a script has expired.
//            $this->updateAppScript();
//        }

        if ($editor) {
            return $this->get_editor_app();
        }

        $lib = get_option(IAMG_SLUG . self::APP_SCRIPT);


        if (!$lib && $_retry) {
            // for some reason the app has not been loaded before from the SAS Server
            $result = $this->update_app_script();
            if ($result) {
                return $this->get_app(false, false);
            } else {
                return [];
            }
        }

        return array_filter([$this->get_pre_app(), $lib], function ($el) {
            return !!$el;
        });

        //old code
    }

    private function get_pre_app($blocks = PHP_INT_MAX, $respond_security = true)
    {
        $pre_app = get_option(IAMG_SLUG . self::APP_PRE_SCRIPT);
        return $pre_app ?: null;

        //Old code

//        $pre = ';console.log("IA Magic Galleries Loading .... ' . gmdate("Y-m-d H:i:s") . ' ");';
//
//        if (get_option(IAMG_SLUG . self::APP_HAS_PRE_SCRIPT)) {
//            try {
//                return $pre .
//                    gzuncompress($this->decrypt(base64_decode(get_option(IAMG_SLUG . self::APP_PRE_SCRIPT)), null,
//                        $blocks, false, true));
//            } catch (\Exception $e) {
//                if ($e->getCode() === 100) {
//                    update_option(IAMG_SLUG . self::APP_PRE_SCRIPT, "", false);
//                    $this->update_app_script();
//                    return $respond_security ? $this->get_pre_app($blocks, false) : "";
//                }
//            }
//        }
//        return $pre;
    }

    private function get_post_app($editor = false)
    {
        return '';

        if ($editor) {
            return ';console.log("IA Magic Galleries Editor Loaded!!!!!")';
        }
        return ';console.log("IA Magic Galleries Loaded!!!!!")';
    }

    /**
     * Provide the presentation for the gallery setup for the admin panel.
     * @return false|string
     */
    public function get_admin_presentation()
    {
        if (get_option(IAMG_SLUG . self::ADMIN_EDITOR)) {
            return get_option(IAMG_SLUG . self::ADMIN_EDITOR);

//            return gzuncompress(base64_decode(get_option(IAMG_SLUG . self::ADMIN_EDITOR))); //old code
        }
        return "";
    }

    /**
     * This function gets an update of the apps and administrative presentations.
     * The app library expires after set period and it must be updated from the SAS server periodically.
     * @return array|WP_Error|int[]|null[]
     */
    public function update_app_script()
    {

        $versions = $this->get_versions();
        $route = $this->routes['script'];

        //For debugging. Forces the server to send the minified version of the script. It is not possible to force
        //the server to send the unminified version.
        if (defined('IAMG_FORCE_MINIFIED') && IAMG_FORCE_MINIFIED) {
            $route .= "?minify";
        }

        $results = $this->send_request(["command" => "get_script", "versions" => $versions], $route,
            true, true);


        set_transient(IAMG_SLUG . "_called_update_app_script", $results);

        if (!$results || isset($results['error']) && $results['error']) {
            return $results;
        }

        if (isset($results['lib'])) {
            update_option(IAMG_SLUG . self::APP_SCRIPT, $results['lib'], false);
            //When the script expires
            update_option(IAMG_SLUG . self::APP_SCRIPT_TIME, $results['expire']);
            update_option(IAMG_SLUG . self::APP_SCRIPT_UPDATED, gmdate("Y-m-d H:i:s"));
            //store the number of blocks in the script that come encrypted with a private key. This includes a
            //md5 check and script to prevent tamparing.
            if (isset($results['blocks'])) {
                update_option(IAMG_SLUG . self::APP_SCRIPT_BLOCKS, $results['blocks']);
            }

            if (isset($results['resources'])) {
                update_option(IAMG_SLUG . self::PLAYER_RESOURCES, $results['resources'], false);
            }

            if (isset($results['editor_lib'])) {
                update_option(IAMG_SLUG . self::APP_SCRIPT_EDITOR, $results['editor_lib'], false);
            }

            if (isset($results['editor_resources'])) {
                update_option(IAMG_SLUG . self::EDITOR_RESOURCES, $results['editor_resources'], false);
            }
        }

        if (isset($results['prelib'])) {
            $prescript = $results['prelib'];
            if ($prescript) {
                update_option(IAMG_SLUG . self::APP_HAS_PRE_SCRIPT, true);
            } else {
                update_option(IAMG_SLUG . self::APP_HAS_PRE_SCRIPT, false);
            }
            update_option(IAMG_SLUG . self::APP_PRE_SCRIPT, $prescript, false);
        }

        if (isset($results['adminpres'])) {
            $adminpres = $results['adminpres'];
            if ($adminpres) {
                if (isset($results['adminpres_version'])) {
                    update_option(IAMG_SLUG . self::ADMIN_EDITOR_VERSION, $results['adminpres_version']);
                }
                update_option(IAMG_SLUG . self::ADMIN_EDITOR, $adminpres, false);
            }
        }

        if (isset($results['other_resources'])) {
            $this->process_other_resources($results['other_resources']);
        }

        return $results;
    }

    public function get_app_time()
    {
        return (int)get_option(IAMG_SLUG . self::APP_SCRIPT_TIME);
    }

    public function get_gallery($settings)
    {
        $results = $this->send_request(["settings" => $settings], $this->routes['gallery'], true, true);


        if (!$results || isset($results['error']) && $results['error']) {
            return $results;
        }

        //We do not do validation $results["svg"] here; the data is only stored by WP. The format is set
        // by the SAS and the client. It is out of the scope of this plugin to validate the data.
        if (isset($results["svg"])) {
            if (isset($results['demo']) && !$results['demo']) {
                $dependence = (isset($results["dependence"])) ? $results["dependence"] : 0;
                $locator = $this->save_gallery_in_temp_storage($results["svg"], $settings, $dependence);
                $results["locator"] = $locator;
            }

            return $results;
        }

        return ["error" => wp_json_encode($results)];
    }

    public function set_gallery_to_post($locator, $local_id, $title, $block_id, $page_id, $post_id, $is_gallery_post)
    {
        if ($locator) {
            $gallery = $this->get_gallery_from_temp_storage($locator);
            if ($gallery) {
                $update_post = \IAMG_posttype::update_post($local_id, $gallery["svg"], $gallery["settings"], $title,
                    $block_id, $page_id, $post_id, $is_gallery_post, $gallery["dependence"]);
                if ($update_post) {
                    return $gallery["settings"];
                }
                return $update_post;
            } else {
                return "expired";
            }
        }
        return false;
    }

    public function rebuild_gallery($local_id)
    {
        $params = \IAMG_posttype::get_post_params($local_id);
        if (!$params) {
            return false;
        }
        $results = $this->get_gallery($params);
        if (isset($results["svg"])) {
            $update_post = \IAMG_posttype::update_post($local_id, $results["svg"], $params);
            return !!$update_post;
        }

        return false;
    }

    public function process_secure_presentation($content, $blocks)
    {
        try {
            $content = $this->decrypt($content, null, $blocks, false, true);
            return $content;
        } catch (\Exception $e) {
            return "";
        }
    }

    public function encrypt($data, $key = null, $blocks = PHP_INT_MAX): array //make private
    {
        if (gettype($key) === 'integer') {
            $blocks = $key;
            $key = null;
        }

        $key = $this->get_key($key);

        if (is_array($data)) {
            $data = wp_json_encode($data);
        }
        if ($key) {
            $encoded = $this->encrypt_chinks($data, $key, $blocks);
            return [$encoded, $blocks];
        }

        return (is_array($data)) ? [wp_json_encode($data), 0] : [$data, 0];
    }

    private function encrypt_chinks($source, $key, $blocks = PHP_INT_MAX, $is_public = true)
    {
        //Assumes 2056 bit key and encrypts in chunks.

        $maxlength = 245;
        $output = '';
        $i = 0;
        while ($source && $i++ < $blocks) {
            $input = substr($source, 0, $maxlength);
            $source = substr($source, $maxlength);
            if (!$is_public) {
                $ok = openssl_private_encrypt($input, $encrypted, $key);
            } else {
                $ok = openssl_public_encrypt($input, $encrypted, $key);
            }

            $output .= $encrypted;
        }
        if ($source) {
            $output .= $source;
        }
        return $output;
    }

    /**
     * Decrypts data send from the server
     * @param string $data the data.
     * @param null $key if a key is provided, it will be used, otherwise the stored key will be used.
     * @param int $blocks
     * @param bool $_is_private whether the key is private (for debugging.)
     * @param bool $sah1_check whether to check the md5 of the decoded string. It assumes that the first 32 characters
     * of the decoded string are the md5 hash of the rest.
     * @return string|null the decoded string.
     */
    private function decrypt($data, $key = null, $blocks = PHP_INT_MAX, $_is_private = false, $sah1_check = false)
    {
        if (gettype($key) === 'integer') {
            $blocks = $key;
            $key = null;
        }
        $key = $this->get_key($key, $_is_private);

        $blocks = intval($blocks);
        if ($key) {
            $decoded = $this->decrypt_chunks($data, $key, $blocks);

            if ($sah1_check) {
                //get the first 40 characters of the decoded string
                $bites = 40;
                $sha1 = substr($decoded, 0, $bites);
                $decoded = substr($decoded, $bites);

                $sha1_check = $sha1 !== md5($decoded);
                if ($sha1_check) {
                    $salt = substr($decoded, 0, $bites);
                    $decoded = substr($decoded, $bites);
                    $size = explode("_", $salt)[0];
                    $size = (int)$size;
                    if ($size !== strlen($decoded)) {
                        //security violation
                        throw new \Exception("Security violation", 100);
                    }
                }
            }

            return $decoded;
        }
        return null;
    }

    private function decrypt_chunks($source, $key, $blocks = PHP_INT_MAX, $is_public = true)
    {
        // The raw PHP decryption functions appear to work
        // on 256 Byte chunks. So this decrypts long text
        // encrypted with ssl_encrypt().

        $maxlength = 256;
        $output = '';
        $i = 0;
        $blocks = intval($blocks);
        while ($source && $i++ < $blocks) {
            $input = substr($source, 0, $maxlength);
            $source = substr($source, $maxlength);
            if (!$is_public) {
                $ok = openssl_private_decrypt($input, $out, $key);
            } else {
                $ok = openssl_public_decrypt($input, $out, $key);
            }

            if (!$ok) {
//                $output .= $out;
                $output .= $input . "**" . $i . "************************************************************";

                break;
            }


            $output .= $out;
            $out = '';
//            $output .= '*****' . $i . '*****';
        }
        if ($source) {
            $output .= $source;
        }

        return $output;
    }


    public function get_key($key = null, $is_private = false)
    {
        $key || $key = get_option(IAMG_SLUG . "_api_key");

        if (!$key) {
            return null;
        }

        if ($is_private) {
            $key_processed = openssl_pkey_get_private(gzuncompress(base64_decode($key)));
        } else {
            $key_processed = openssl_pkey_get_public(gzuncompress(base64_decode($key)));
//            $key_processed = gzuncompress(base64_decode($key));
        }


        return $key_processed;
    }

//    public function test_get_key()
//    {
//        return $this->getKey();
//    }

    public function register_client()
    {
        $key = $this->get_key();

//        echo $key . "\n"

        if (!$key) {
            //Ask for a key from the server
            try {
                $results = $this->send_request(["wp" => admin_url('admin-ajax.php')], $this->routes["register"], true,
                    0);

//                update_option(IAMG_SLUG . "_called_reg_client", $results);
            } catch (\Exception $e) {
                update_option(IAMG_SLUG . "_called_reg_client_exception", $e->getMessage());
                return ["success" => false, "message" => $e->getMessage(), "error" => self::ERROR_NETWORK_EXCEPTION];
            }

            if (isset($results['key'])) {
                $key = $results['key'];

                if ($results['check']) {
                    $check = $this->decrypt(base64_decode($results['check']), $key, 5);

//                    wp_send_json([$results, $check, $this->user_agent()]);

                    if ($check === $this->user_agent()) {
//                        echo "in save\n";
                        update_option(IAMG_SLUG . self::REGISTERED_SERVER, $results['contact_server']);
                        $this->save_key($key);
                        return ["success" => true, "message" => "Registered Successfully", "error" => 0];
                    } elseif ($this->is_local_server()) {
                        return [
                            "success" => false,
                            "message" => "Local Server Agent Problem",
                            "error" => self::ERROR_LOCAL_SERVER_USER_AGENT_PROBLEM
                        ];
                    }
                } else {
                    $this->save_key($key);
                    return ["success" => true, "message" => "Registered Successfully", "error" => 0];
                }
            }

            return [
                "success" => false,
                "message" => "No key received",
                "error" => (isset($results['error']) ? $results['error'] : self::ERROR_CONNECT_ERROR)
            ];
        } else {
            //verify the saved key
            if (!$this->check_key()) {
//                echo "Key is incorrect\n";
                $this->save_key("");
                return $this->register_client();
            } else {
//                echo "Client already registered!";
                return ["success" => true, "message" => "Client already registered", "error" => 0];
            }
        }
    }

    public function check_key($key = null)
    {
//        echo $key."\n";
        $user_agent = $this->user_agent();
//        echo $user_agent."\n";
        $encr = $this->encrypt($user_agent, $key)[0];
//        echo $encr;
        $encrypt = base64_encode($encr);

//        echo $encrypt."\n";

        $results = $this->send_request(['check' => $encrypt], $this->routes["register"], true, false);

//        echo wp_json_encode($results) ."\n";

        $local_success = true;
        $server_success = isset($results['success']) && $results['success'];

        if (isset($results['check'])) {
            $check = $this->decrypt(base64_decode($results['check']), $key);
//            echo $check."\n";
            $local_success = $check === "success";
        }

//        return $server_success ." local: ". $local_success;

        return $server_success && $local_success;
    }

    /**
     * Saves the client decryption key in options
     * @param {string} $key the key.
     *
     * @return void
     */
    public function save_key($key) //make private
    {
        update_option(IAMG_SLUG . "_api_key", $key);
    }


    /**
     * Send request to remote endpoint
     *
     * @param array $params
     * @param string $route
     *
     * @return array|WP_Error   Array of results including HTTP headers or WP_Error if the request failed.
     */
    public function send_request($params, $route, $blocking = true, $encrypt = PHP_INT_MAX, $backup_server = false)
    {
        $endpoint = $this->endpoint($backup_server);
        $url = $endpoint . $route;

        $site_url = $this->site_url();
        $params["site"] = $site_url;
        $params["is_local"] = $this->is_local_server();
        $params["is_playground"] = $this->in_playground;
        $params["registered_server"] = get_option(IAMG_SLUG . self::REGISTERED_SERVER) ?: "none";

        $encrypted = false;
        if (!$this->in_playground && $encrypt) {
            $encrypt1 = $this->encrypt($params);
            $encrypted = $encrypt1[1];
            $data = ($encrypted) ? base64_encode($encrypt1[0]) : $encrypt1[0];
//            wp_send_json($data);
        } else {
            $data = $params;
        }

        $user_agent = $this->user_agent();
        $headers = array(
            'user-agent' => $user_agent,
            'Accept' => 'application/json',
        );

        $post_params = array(
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => $blocking,
            'headers' => $headers,
            'body' => array('data' => $data, 'client' => IAMG_VERSION, 'encrypted' => $encrypted),
            'cookies' => array()
        );

//        wp_send_json([$url,$post_params]);

        if ($this->is_local_server(true)){
            $response = wp_remote_post($url, $post_params);
        } else {
            $response = wp_safe_remote_post($url, $post_params);
        }

        $try_backup = false;
        $error_code = self::ERROR_CONNECT_ERROR;

        $error_from_request = $response instanceof \WP_Error;
        if ($error_from_request) {
            $error_code = $this->analyze_curl_error($response);
            if ($error_code) {
                if ($error_code === self::ERROR_NO_ACCESS_TO_INTERNET) {
                    return ["error" => $error_code];
                } else {
                    $try_backup = true;
                }
            }
        }

        if (!$error_from_request && $response['response']['code'] != 200) {
//            echo $response['body'];
//            die();
            $error_code = self::ERROR_SERVER_NOT_AVAILABLE;
            $try_backup = false; //true;
        }

        if ($try_backup && !$backup_server) {
            return $this->send_request($params, $route, $blocking, $encrypt, true);
        } elseif ($try_backup && $backup_server) {
            return ["error" => $error_code];
        }

        $body = json_decode($response['body'], true);

        $body["contact_server"] = $backup_server ? "backup" : "main";
        return $body;
    }

    private function site_url()
    {
        if ($this->is_local_server()) {
            $saved_key = get_option(IAMG_SLUG . "_local_server_key");
            if (!$saved_key) {
                $saved_key = random_int(1e6, 1e7 - 1) . "-" . round(microtime(true));

                update_option(IAMG_SLUG . "_local_server_key", $saved_key, false);
            }
            return $saved_key;
        }

        return esc_url(home_url());
    }

    /**
     * API Endpoint
     *
     * @return string
     */
    public function endpoint($backup_server)
    {
        $endpoint = apply_filters('IAMG_endpoint', $backup_server ? IAMG_API_URL_BACKUP : IAMG_API_URL);

        return trailingslashit($endpoint);
    }


    /**
     * Check if the current server is localhost
     *
     * @return boolean
     */
    public function is_local_server($truly_local = false)
    {
        $local_ips = ['127.0.0.1', '::1', 'localhost'];

        $is_local = ($this->in_playground && !$truly_local)
            || !isset($_SERVER['SERVER_ADDR'])
            || in_array(sanitize_text_field($_SERVER['HTTP_HOST']), $local_ips)
            || in_array(sanitize_text_field($_SERVER['SERVER_ADDR']), $local_ips)
            || in_array(sanitize_text_field($_SERVER['SERVER_NAME']), $local_ips);

//        if (!$is_local && (!defined('WP_CLI') || !WP_CLI)) {
//            $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
//            $is_local = in_array($remote_ip, $local_ips);
//        }

        if (!$is_local) {
            $privateRanges = [
                ['start' => '10.0.0.0', 'end' => '10.255.255.255'],
                ['start' => '172.16.0.0', 'end' => '172.31.255.255'],
                ['start' => '192.168.0.0', 'end' => '192.168.255.255'],
                ['start' => 'fc00::', 'end' => 'fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff']
            ];
            $is_local = false;
            $ip_pack = inet_pton(sanitize_text_field($_SERVER['SERVER_ADDR']));
            foreach ($privateRanges as $range) {
                if ($ip_pack >= inet_pton($range['start']) && $ip_pack <= inet_pton($range['end'])) {
                    $is_local = true;
                    break;
                }
            }
        }


        return apply_filters('IAMG_is_local', $is_local);
    }

    public function is_server_connected_to_internet()
    {
        // URL to check connectivity (you can use a reliable website)
        $url_to_check = 'https://www.google.com';

        // Set up the arguments for the wp_remote_post function
//        $args = array(
//            'body' => '', // Empty body for a POST request
//            'timeout' => 5,  // Adjust the timeout as needed (in seconds)
//            'headers' => array(
//                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
//            ),
//        );

        // Make a POST request to the URL
        $response = wp_remote_get($url_to_check);

//        wp_send_json($response);

        // Check if the response was successful
        if (is_wp_error($response)) {
            return false; // Server cannot access the internet
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            return (403 === $response_code); // Server can access the internet if the response code is 403, we use that becuse it comes faster.
        }
    }

    private function analyze_curl_error($response)
    {
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();

            if (!$this->is_server_connected_to_internet()) {
                return self::ERROR_NO_ACCESS_TO_INTERNET;
            }
            // Check if the error message contains known cURL error codes and provide appropriate details
            if (strpos($error_message, 'cURL error') !== false) {
                if (preg_match('/cURL error (\d+):/', $error_message, $matches)) {
                    $curl_error_code = $matches[1];
                    switch ($curl_error_code) {
                        case 6:
//                            cURL error 6: Could not resolve host. Check your DNS settings
                            return self::ERROR_SERVER_NOT_REACHABLE;
                        case 7:
                            // cURL Error 7: Failed to connect to host
                            return self::ERROR_SERVER_NOT_AVAILABLE;

                        case 28:
//                            'cURL error 28: Connection timed out. Check your network connectivity.';
                            return self::ERROR_CONNECT_TIMEOUT;
                        default:
                            return self::ERROR_CONNECT_ERROR;
                    }
                }
            }

            // If the error message doesn't match known cURL error formats
            return self::ERROR_CONNECT_ERROR;
        }

        // If there was no error, return null
        return null;
    }


    /**
     * @return string
     */
    private function user_agent(): string
    {
        return 'IAMG/' . md5($this->site_url()) . ';';
    }

    /**
     * @return string
     */
    public function get_slug(): string
    {
        return IAMG_SLUG;
    }

    public function unregister_local()
    {
        if ($this->is_local_server()) {
//            $results = $this->send_request(['dereg' => true], $this->routes["register"], true, false);
//            if (!isset($results['success']) || !$results['success']) {
//                return false;
//            }
//            return true;
            $this->unregister();
        }
        return false;
    }

    public function unregister()
    {
        $results = $this->send_request(['dereg' => true], $this->routes["register"], true, false);
        if (!isset($results['success']) || !$results['success']) {
            return false;
        }
        return true;
    }

    private function get_editor_app($retry = true)
    {
        $lib = get_option(IAMG_SLUG . self::APP_SCRIPT_EDITOR);

        if (!$lib && $retry) {
            $result = $this->update_app_script();
            if ($result) {
                return [$this->get_pre_app(), $this->get_editor_app(false)];
            } else {
                return [];
            }
        }

        return array_filter([$this->get_pre_app(), $lib], function ($el) {
            return !!$el;
        });

//        return $blocks . "  " . $this->decrypt(gzuncompress(base64_decode($lib)), null, $blocks);

//
//        $blocks = get_option(IAMG_SLUG . self::APP_SCRIPT_BLOCKS);
//        try {
//            $preApp = $this->get_pre_app($blocks);
//            return $preApp . "; \n" . gzuncompress($this->decrypt(base64_decode($lib), null, $blocks, false, true))
//                . "; \n" . $this->get_post_app(true);
//        } catch (\Exception $e) {
//            if ($e->getCode() === 100) {
//                update_option(IAMG_SLUG . self::APP_SCRIPT_EDITOR, "", false);
//                $this->update_app_script();
//                return ($retry) ? $this->get_editor_app(false) : "";
//            }
//        }
    }

    /**
     * Get the app resources passed from the server
     * @param $is_editor boolean these are the resource for the editor or the player
     * @return array the resources
     */
    public function get_app_resources(bool $is_editor = false)
    {
        $option = ($is_editor)
            ? $this->get_slug() . self::EDITOR_RESOURCES
            : $this->get_slug() . self::PLAYER_RESOURCES;
        $resources = get_option($option);
        if (!$resources) {
            $resources = [];
        }

        return $resources;
    }


    private function save_gallery_in_temp_storage($svg, $settings, $dependence = 0)
    {
        $locator = md5($svg);
        set_transient($this->get_slug() . $locator,
            ['svg' => $svg, 'settings' => $settings, 'dependence' => $dependence], 60 * 60);
//        set_transient($this->get_slug() . $locator,  $svg, 60 * 60);
        return $locator;
    }

    private function get_gallery_from_temp_storage($locator)
    {
        $result = get_transient($this->get_slug() . $locator);
        if ($result) {
            return $result;
        }
        return false;
    }

    private function process_other_resources($other_resources)
    {
        foreach ($other_resources as $name => $resource) {
            if (is_array($resource) && isset($resource['version'])) {
                $version = $resource['version'];
                $current_version = get_option(IAMG_SLUG . self::RESOURCE_VERSION . $name);
                if ($current_version !== $version) {
                    $this->save_resource($name, $resource['resource'], $version);
                }
            } else {
                $this->save_resource($name, $resource);
            }
        }
    }

    private function save_resource($name, $resource, $version = null)
    {
        $option = IAMG_SLUG . self::RESOURCE . $name;
        update_option($option, $resource, false);
        if ($version) {
            update_option(IAMG_SLUG . self::RESOURCE_VERSION . $name, $version, false);
        }
    }

    public function get_resource(string $name, $encoding = null)
    {
        $option = IAMG_SLUG . self::RESOURCE . $name;
        $option = get_option($option);

        if (is_array($option)) {
            $encoding = $option['encoding'];
            $option = $option['data'];
        }
        if ($encoding) {
            switch ($encoding) {
                case 'base64':
                    return base64_decode($option);
                case 'gzip':
                    return gzuncompress(base64_decode($option));
            }
        }
        return $option;
    }

    private function get_resource_versions()
    {
        //query options database for all options begining with IAMG_SLUG . self::RESOURCE_VERSION, extarct the names and create an assosiatve array with the names as keys and the versions as values.
        //This is called very rarely, only during lib update, so caching is not necessary.
        global $wpdb;
        //get all options begining with IAMG_SLUG . self::RESOURCE_VERSION
        $query = $wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            IAMG_SLUG . self::RESOURCE_VERSION . '%');
        $options = $wpdb->get_results($query);


//        $options = $wpdb->get_results(
//            $wpdb->prepare(
//                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
//                IAMG_SLUG . self::RESOURCE_VERSION . '%'
//            )
//        );

        $versions = [];
        foreach ($options as $option) {
            $name = str_replace(IAMG_SLUG . self::RESOURCE_VERSION, "", $option->option_name);
            $versions[$name] = $option->option_value;
        }

        return $versions;
    }

    private function get_versions()
    {
//        $versions = [];
        $versions['iamg'] = IAMG_VERSION;
        $versions['adminpres_version'] = get_option(IAMG_SLUG . self::ADMIN_EDITOR_VERSION);
        $versions['other_resources'] = $this->get_resource_versions();

        return $versions;
    }
}