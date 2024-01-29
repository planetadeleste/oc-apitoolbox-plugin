<?php

use System\Classes\PluginManager;

if (!function_exists('has_plugin')) {

    /**
     * Checks to see if a plugin has been registered.
     *
     * @param string $sNamespace
     *
     * @return bool
     */
    function has_plugin(string $sNamespace): bool
    {
        return PluginManager::instance()->hasPlugin($sNamespace);
    }
}

if (!function_exists('has_jwtauth_plugin')) {
    /**
     * Checks for plugin PlanetaDelEste.JWTAuth
     *
     * @return bool
     */
    function has_jwtauth_plugin(): bool
    {
        return has_plugin('PlanetaDelEste.JWTAuth') || has_plugin('ReaZzon.JWTAuth');
    }
}

if (!function_exists('tr')) {
    /**
     * Translate $message using current locale or $locale
     *
     * @param string $message
     * @param array       $options
     * @param string|null $locale
     *
     * @return string
     */
    function tr(string $message, array $options = [], string $locale = null): string
    {
        return \PlanetaDelEste\ApiToolbox\Classes\Helper\ApiHelper::tr($message, $options, $locale);
    }
}
