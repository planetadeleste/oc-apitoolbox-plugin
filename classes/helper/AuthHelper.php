<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Helper;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Lovata\Buddies\Classes\Item\UserItem;
use Lovata\Buddies\Facades\AuthHelper as BuddiesAuthHelper;
use Lovata\Buddies\Models\Group;
use Lovata\Buddies\Models\User;
use October\Rain\Argon\Argon;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PlanetaDelEste\ApiShopaholic\Classes\Resource\User\ItemResource;
use PlanetaDelEste\ApiToolbox\classes\Dto\TokenDto;
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
     * @param Authenticatable $user
     *
     * @return string
     */
    public static function login(Authenticatable $user): string
    {
        return self::jwt()->login($user);
    }

    /**
     * @return void
     */
    public static function logout(): void
    {
        BuddiesAuthHelper::logout();
    }

    /**
     * @param Authenticatable $user
     *
     * @return array{token: string, expires: string, user: ItemResource, expires_in: int}
     *
     * @throws \Exception
     */
    public static function loginAndReturnResult(Authenticatable $user): array
    {
        $sToken = self::login($user);

        return self::dtoData($sToken, $user);
    }

    /**
     * @param string               $sToken
     * @param Authenticatable|null $user
     *
     * @return array{token: string, expires: string, user: ItemResource, expires_in: int}
     *
     * @throws \Exception
     */
    public static function dtoData(string $sToken, ?Authenticatable $user = null): array
    {
        $tokenDto   = self::getTokenDto($sToken, $user);
        $arResult   = $tokenDto->toArray();
        $obUser     = $arResult['user'];
        $obUserItem = UserItem::make($obUser->id);
        array_set($arResult, 'user', ItemResource::make($obUserItem));

        return $arResult;
    }

    /**
     * @param string               $sToken
     * @param Authenticatable|null $obUser
     *
     * @return TokenDto
     *
     * @throws \Exception
     */
    public static function getTokenDto(string $sToken, ?Authenticatable $obUser = null): TokenDto
    {
        if (!$obUser) {
            $obUser = self::jwt()->user();
        }

        return new TokenDto([
            'token'   => $sToken,
            'expires' => Argon::createFromTimestamp(self::jwt()->getPayload()->get('exp'), ApiHelper::tz()),
            'user'    => $obUser,
        ]);
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
