<?php

use Illuminate\Support\Str;

if (!function_exists('secure_asset')) {
    function secure_asset($path)
    {
        $asset = asset($path);
        return Str::startsWith($asset, 'http://') ? str_replace('http://', 'https://', $asset) : $asset;
    }
}
