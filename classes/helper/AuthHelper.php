<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Helper;

use Illuminate\Contracts\Foundation\Application;
use Lovata\Buddies\Models\Group;
use Lovata\Buddies\Models\User;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use ReaZzon\JWTAuth\Classes\Guards\JWTGuard;

class AuthHelper
{
    /**
     * @var array Loaded users
     */
    protected static array $arUsers = [];

    /**
     * @var JWTGuard|null
     */
    protected static ?JWTGuard $jwt = null;

    /**
     * Check if logged user has group of code $sCode
     *
     * @param string $sCode
     *
     * @return bool|null
     */
    public static function inGroup(string $sCode): ?bool
    {
        if (!$obUser = self::user()) {
            return null;
        }

        $obGroup = Group::getByCode($sCode)->first();

        return $obGroup ? $obUser->inGroup($obGroup) : null;
    }

    /**
     * @return User|null
     */
    public static function user(): ?User
    {
        if ($iUserID = self::userId()) {
            if (isset(self::$arUsers[$iUserID])) {
                return self::$arUsers[$iUserID];
            }

            self::$arUsers[$iUserID] = User::find($iUserID);

            return self::$arUsers[$iUserID];
        }

        return null;
    }

    /**
     * @return int|null
     */
    public static function userId(): ?int
    {
        if (!self::check() || !self::jwt()->hasUser()) {
            return null;
        }

        return self::jwt()->user()->getAuthIdentifier();
    }

    /**
     * @return bool
     */
    public static function check(): bool
    {
        try {
            if ((!$JWTGuard = self::jwt()) || !$JWTGuard->check()) {
                return false;
            }

            return true;
        } catch (JWTException $ex) {
            return false;
        }
    }

    /**
     * @return Application|mixed|JWTGuard|(JWTGuard&Application)
     */
    public static function jwt(): mixed
    {
        if (!self::$jwt) {
            self::$jwt = app('JWTGuard');
        }

        return self::$jwt;
    }
}
