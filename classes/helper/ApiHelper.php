<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Helper;

use Exception;
use System\Classes\PluginManager;

class ApiHelper
{
    /**
     * @param string      $message
     * @param array       $options
     * @param string|null $locale
     *
     * @return string
     */
    public static function tr(string $message, array $options = [], string $locale = null): string
    {
        if (!PluginManager::instance()->hasPlugin('RainLab.Translate')) {
            return $message;
        }

        return \RainLab\Translate\Models\Message::trans($message, $options, $locale);
    }

    /**
     * @return bool
     */
    public static function isBackend(): bool
    {
        return AuthHelper::check() && request()->header('X-ENV') === 'backend';
    }

    /**
     * @return bool
     */
    public static function isFrontend(): bool
    {
        return AuthHelper::check() && request()->header('X-ENV') === 'frontend';
    }

    /**
     * @param string|null $ip
     * @return string|null
     * @throws Exception
     */
    public static function tz(string $ip = null): ?string
    {
        return geoip()->getLocation()->timezone;
    }
}
