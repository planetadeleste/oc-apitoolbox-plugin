<?php

namespace PlanetaDelEste\ApiToolbox\classes\Dto;

use JetBrains\PhpStorm\ArrayShape;
use Lovata\Buddies\Models\User;
use ReaZzon\JWTAuth\Classes\Dto\TokenDto as JWTAuthTokenDto;

class TokenDto extends JWTAuthTokenDto
{
    /**
     * @var int|float
     */
    protected int|float $expiresIn;

    public function __construct($data)
    {
        parent::__construct($data);
        $this->expiresIn = now()->diffInSeconds($this->expires);
    }

    /**
     * @return array{token: string, expires: string, user: User, expires_in: int}
     */
    #[ArrayShape([
        'token'      => 'string',
        'expires'    => 'string',
        'user'       => User::class,
        'expires_in' => 'int'
    ])]
    public function toArray(): array
    {
        return parent::toArray() + ['expires_in' => $this->expiresIn];
    }
}
