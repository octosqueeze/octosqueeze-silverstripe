<?php

namespace OctoSqueeze\Silverstripe;

use foroco\BrowserDetection;

class Imaginator
{
    /**
     * Detecting browser and checking if its version support requested format
     */
    public static function browserCheck($format)
    {
      	if (isset($_SERVER['HTTP_USER_AGENT']))
        {
            $agent = $_SERVER['HTTP_USER_AGENT'];
            $browserDetector = new BrowserDetection();
            $userAgent = $browserDetector->getAll($agent);

            // Browser support versions from caniuse.com
            // https://caniuse.com/avif
            // https://caniuse.com/webp

            if ($userAgent)
            {
                $browsers = [
                    'Chrome' => [
                        'avif' => 85,
                        'webp' => 32,
                    ],
                    'Edge' => [
                        'avif' => 121,
                        'webp' => 18,
                    ],
                    'Safari' => [
                        'avif' => 16.4,
                        'webp' => 16,
                    ],
                    'Firefox' => [
                        'avif' => 93,
                        'webp' => 65,
                    ],
                    'Opera' => [
                        'avif' => 71,
                        'webp' => 19,
                    ],
                    'IE' => [
                        'avif' => false,
                        'webp' => false,
                    ],
                ];

                if (isset($browsers[$userAgent['browser_name']]))
                {
                    $browser = $browsers[$userAgent['browser_name']];

                    if (isset($browser[$format]) && $browser[$format] !== false)
                    {
                        return $userAgent['browser_version'] >= $browser[$format];
                    }
                }
            }
        }

        return false;
    }

    public static function imageSupport()
    {
        $support = [];

        if (isset($_SERVER['HTTP_USER_AGENT']))
        {
            $agent = $_SERVER['HTTP_USER_AGENT'];

            if (strpos($agent, 'image/avif') !== false || self::browserCheck('avif'))
            {
                $support[] = 'avif';
            }

            if (strpos($agent, 'image/webp') !== false || self::browserCheck('webp'))
            {
                $support[] = 'webp';
            }
        }

        return $support;
    }
}
