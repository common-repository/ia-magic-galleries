<?php
/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

namespace IAMG;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed

class IAMG_AdminNotice
{

    const SUCCESS = 'success';
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';


    private static $notice_types_classes = [
        'success' => 'notice-success',
        'error' => 'notice-error',
        'warning' => 'notice-warning',
        'info' => 'notice-info'
    ];

    public function __construct()
    {
//        add_action('admin_notices', array($this, 'display_test_notice'));
//        add_action('admin_notices', array($this, 'display_rating_notice'));
//        self::display_notice("A notice here using display notice");
    }


    public static function display_notice($message, $type = self::INFO)
    {
        add_action('admin_notices', function () use ($message, $type) {
            IAMG_AdminNotice::echo_notice($message, $type);
        });
    }

    public static function echo_notice($message, $type = self::INFO)
    {
//        $message = __($message, 'ia-magic-galleries');
        $classes = "notice iamg-notice is-dismissible";
        if (!isset(self::$notice_types_classes[$type])) {
            $type = self::INFO;
        }
        $classes .= " " . self::$notice_types_classes[$type];
        ?>
        <style>
            .iamg-notice {
                min-height: 30px;
            }
        </style>
        <div class=" <?php echo esc_attr($classes); ?> ">
            <div class="">
                <strong>
                    <?php echo esc_html($message); ?>
                </strong>
            </div>
        </div>
        <?php
    }

    // classes for notice
    //    notice-success: This class is often used for success messages. It typically has a green background color.
    //
    //    notice-error or notice-danger: These classes are used for error messages or warnings. They typically have a red or orange background color.
    //
    //    notice-warning: This class is used for general warning messages. It often has a yellow background color.
    //
    //    notice-info: This class is used for informational messages. It usually has a blue background color.
    //
    //    is-dismissible: This class is added to make the notice dismissible, allowing users to hide it by clicking the "X" button.

    /**
     * @return void
     */

    function display_test_notice()
    {
        ?>
        <style>
            .notice {
                min-height: 30px;
            }
        </style>
        <div class="notice notice-info iamg-notice is-dismissible">
            <div class="">
                <strong>
                    A Test Notice Here!
                </strong>
            </div>
        </div>
        <?php
    }

    function display_rating_notice()
    {
        ?>
        <div class="notice notice-success iamg-notice is-dismissible">
            <strong>
                Another Notice Here about rating!
            </strong>
        </div>
        <?php
    }
}