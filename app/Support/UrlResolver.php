<?php

namespace App\Support;

use App\Models\Setting;

class UrlResolver
{
    public static function portalUrl(): string
    {
        $url = '';
        $settingUrl = Setting::getValue('app_url');

        if (is_string($settingUrl) && $settingUrl !== '') {
            $url = $settingUrl;
        } elseif (app()->bound('request')) {
            $root = request()->root();
            if (is_string($root) && $root !== '') {
                $url = $root;
            }
        }

        if (! is_string($url) || $url === '') {
            $url = config('app.url');
        }

        if (! is_string($url) || $url === '') {
            $url = 'http://localhost';
        }

        return rtrim($url, '/');
    }
}
