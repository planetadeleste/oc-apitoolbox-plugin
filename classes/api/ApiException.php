<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Api;

use Arr;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Kharanenka\Helper\Result;
use Throwable;

class ApiException
{
    /**
     * @param \Throwable|mixed $obException
     * @param int              $iStatus
     * @param bool             $translate
     *
     * @return JsonResponse
     */
    public static function exception(mixed $obException, int $iStatus = 403, bool $translate = false): JsonResponse
    {
        static::logExceptionSafely($obException);

        Result::setFalse();

        $message = $obException instanceof \Throwable
            ? $obException->getMessage()
            : (string) $obException;

        if ($translate) {
            $options = [];
            $locale  = null;

            if (is_array($message)) {
                $options = Arr::isAssoc($message) ? array_get($message, 'options', []) : $message[1] ?? [];
                $locale  = Arr::isAssoc($message) ? array_get($message, 'locale', null) : $message[2] ?? null;
                $message = Arr::isAssoc($message) ? array_get($message, 'message', '') : $message[0] ?? '';
            }

            $message = str_slug(trim($message), '.');
            $message = tr($message, $options, $locale);
        }

        if (!input('silently')) {
            Result::setMessage($message);
        }

        $iStatus = static::resolveStatusCode($obException, $iStatus);

        return response()->json(Result::get(), $iStatus);
    }

    protected static function logExceptionSafely(mixed $obException): void
    {
        try {
            if ($obException instanceof Throwable) {
                Log::error('API exception handled', [
                    'class'   => $obException::class,
                    'message' => $obException->getMessage(),
                    'code'    => $obException->getCode(),
                    'file'    => $obException->getFile(),
                    'line'    => $obException->getLine(),
                ]);

                return;
            }

            Log::error('API exception handled (non-throwable)', [
                'exception' => is_scalar($obException) || null === $obException
                    ? $obException
                    : get_debug_type($obException),
            ]);
        } catch (Throwable) {
            // Evitar que un error al loguear bloquee la respuesta de la API
        }
    }

    protected static function resolveStatusCode(mixed $obException, int $defaultStatus): int
    {
        if ($obException instanceof Throwable) {
            $code = (int) $obException->getCode();

            if ($code >= 400 && $code < 600) {
                return $code;
            }
        }

        return $defaultStatus;
    }
}
