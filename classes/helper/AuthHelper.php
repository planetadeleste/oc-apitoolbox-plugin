<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Helper;

use Lovata\Buddies\Models\Group;
use Lovata\Buddies\Models\User;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthHelper
{
    /** @var array Loaded users */
    protected static $arUsers = [];

    /**
     * Check if logged user has group of code $sCode
     *
     * @param string $sCode
     *
     * @return bool|null
     * @throws \Tymon\JWTAuth\Exceptions\JWTException
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
     * @throws \Tymon\JWTAuth\Exceptions\JWTException
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
     * @return null|int
     * @throws \Tymon\JWTAuth\Exceptions\JWTException
     */
    public static function userId(): ?int
    {
        if (!self::check()) {
            return null;
        }

        return \JWTAuth::parseToken()->authenticate()->id;
    }

    /**
     * @return bool
     */
    public static function check(): bool
    {
        try {
            if (!class_exists('JWTAuth') || !\JWTAuth::getToken() || !\JWTAuth::parseToken()->authenticate()->id) {
                return false;
            }
        } catch (JWTException $ex) {
            return false;
        }

        return true;
    }
}
