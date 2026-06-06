<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Helper;

use Exception;
use Str;
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
    public static function tr(string $message, array $options = [], ?string $locale = null): string
    {
        if (!PluginManager::instance()->hasPlugin('RainLab.Translate')) {
            return $message;
        }

        return \RainLab\Translate\Models\Message::trans($message, $options, $locale);
    }

    /**
     * @param string $message
     *
     * @return bool
     */
    public static function isTranslatable(string $message): bool
    {
        if (!PluginManager::instance()->hasPlugin('RainLab.Translate')) {
            return false;
        }

        return Str::slug($message, '.') === $message;
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
     *
     * @return string|null
     *
     * @throws Exception
     */
    public static function tz(?string $ip = null): ?string
    {
        return geoip($ip)->getLocation()->timezone;
    }

    public static function companyID(): int|string|null
    {
        $iCompanyID = request()->header('X-AV-CID', null);

        return $iCompanyID ?: null;
    }

    public static function officeID(): int|string|null
    {
        $iOfficeID = request()->header('X-AV-OID', null);

        return $iOfficeID ?: null;
    }

    public static function arrayFilterRecursive(array $arData): array
    {
        return array_filter(array_map(static function ($item) {
            if (is_array($item)) {
                $item = self::arrayFilterRecursive($item);
            }

            return $item;
        }, $arData), static function ($item) {
            if (is_array($item)) {
                return !empty($item);
            }

            return null !== $item;
        });
    }
}
