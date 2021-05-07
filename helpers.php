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
        return has_plugin('PlanetaDelEste.JWTAuth');
    }
}
