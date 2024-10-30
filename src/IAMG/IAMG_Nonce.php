<?php
/*
 * Copyright © 2024  Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

namespace IAMG;

class IAMG_Nonce
{
    private static $nonce;

    public static function setNonce()
    {
        if (self::$nonce === null) {
            self::$nonce = wp_create_nonce('iamg_direct');
            setcookie('_iamgnonce', self::$nonce, [
                'expires' => time() + 24 * 3600,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'sameSite' => 'None',
                'secure' => true,
            ]);
        }
    }
}