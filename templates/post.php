<?php
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>
<head>
    <meta http-equiv="Content-Type"
          content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>"/>

    <title><?php echo esc_attr(wp_get_document_title()); ?></title>
    <style>
        body, html {
            height: 100%;
            overflow: hidden; /* Hide scrollbars */
            margin: 0;
        }

        .IA_Presenter_Container, .IA_Designer_Container {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 50%;
            left: 50%;
            /* bring your own prefixes */
            transform: translate(-50%, -50%);
            /*vertical-align: top;*/
        }

        #wpadminbar {
            opacity: .3;
        }

        #wpadminbar:hover {
            opacity: 1;
        }
    </style>
    <?php //wp_head(); ?>
</head>
<body <?php body_class(); ?>
>
<div id="page">
</div>
<?php

IAMG_posttype::render_post();
//echo wp_kses(IAMG_posttype::render_post(), IAMG_posttype::$allowed_html);
?>
<div id="footer" role="contentinfo">
    <?php wp_footer(); ?>
</div>
</body>
</html>
