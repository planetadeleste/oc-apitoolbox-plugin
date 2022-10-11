<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Helper;

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

        if (!\RainLab\Translate\Models\Message::$locale) {
            \RainLab\Translate\Models\Message::setContext(
                \RainLab\Translate\Classes\Translator::instance()->getLocale()
            );
        }

        return \RainLab\Translate\Models\Message::trans($message, $options, $locale);
    }

    public static function isBackend(): bool
    {
        return AuthHelper::check() && request()->header('X-ENV') === 'backend';
    }

    public static function isFrontend(): bool
    {
        return AuthHelper::check() && request()->header('X-ENV') === 'frontend';
    }
}
