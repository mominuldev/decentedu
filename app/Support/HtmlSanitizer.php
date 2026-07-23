<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * The real mitigation for the CMS `posts.body` XSS risk (doc 08) — sanitizes on write, not
 * just on render, since the sanitized HTML is what every future consumer (SPA preview now,
 * public site later) will render with dangerouslySetInnerHTML.
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(string $html): string
    {
        return self::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,strong,em,u,ol,ul,li,a[href|target],img[src|alt|width|height],'.
                'h1,h2,h3,h4,blockquote,table,thead,tbody,tr,th,td');
            $config->set('HTML.TargetBlank', true);
            $config->set('Cache.SerializerPath', storage_path('app/htmlpurifier-cache'));
            self::$purifier = new HTMLPurifier($config);
        }

        return self::$purifier;
    }
}
