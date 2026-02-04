<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Api;

use Arr;
use Exception;
use Illuminate\Http\JsonResponse;
use Kharanenka\Helper\Result;

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
        try {
            trace_log($obException);
        } catch (\Throwable $e) {
            // Ignorar errores de logging
        }

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

        return response()->json(Result::get(), $iStatus);
    }
}
