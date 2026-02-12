<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain\Concerns;

use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PlanetaDelEste\ApiToolbox\Contracts\CastsAttributes;

/**
 * Trait HasCasts
 *
 * Provee funcionalidad de casting para DTOs, similar a Laravel Eloquent.
 * Permite definir casts en la propiedad $casts para transformar valores automáticamente.
 *
 * Tipos de cast soportados:
 * - Primitivos: 'int', 'integer', 'float', 'double', 'real', 'string', 'bool', 'boolean'
 * - Arrays/JSON: 'array', 'json', 'collection', 'object'
 * - Fechas: 'date', 'datetime', 'timestamp', 'immutable_date', 'immutable_datetime'
 * - Decimales: 'decimal:2' (con precisión)
 * - Enums: NombreDelEnum::class
 * - Custom: ClaseQuImplementaCastsAttributes::class
 *
 * @example
 * ```php
 * class MyDTO extends AbstractDtoDomain {
 *     protected array $casts = [
 *         'amount' => 'decimal:2',
 *         'is_active' => 'bool',
 *         'created_at' => 'datetime',
 *         'tags' => 'array',
 *         'status' => StatusEnum::class,
 *         'metadata' => MetadataCast::class,
 *     ];
 * }
 * ```
 */
trait HasCasts
{
    /**
     * Los atributos que deben ser casteados.
     * Puede ser sobrescrito en clases hijas.
     *
     * @var array<string, string>
     */
    protected array $casts = [];

    /**
     * Cache de instancias de clases de cast personalizadas
     *
     * @var array<string, CastsAttributes>
     */
    protected array $castClassCache = [];

    /**
     * Tipos de cast primitivos soportados
     *
     * @var array<string>
     */
    protected static array $primitiveCastTypes = [
        'array',
        'collection',
        'date',
        'datetime',
        'decimal',
        'double',
        'float',
        'immutable_date',
        'immutable_datetime',
        'int',
        'integer',
        'json',
        'object',
        'real',
        'string',
        'timestamp',
    ];

    /**
     * Formato de fecha por defecto
     *
     * @var string
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

    /**
     * Obtiene los casts definidos
     *
     * @return array<string, string>
     */
    public function getCasts(): array
    {
        return $this->casts;
    }

    /**
     * Determina si el atributo tiene un cast definido
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    /**
     * Obtiene el tipo de cast para un atributo
     *
     * @param string $key
     *
     * @return string|null
     */
    protected function getCastType(string $key): ?string
    {
        if (!$this->hasCast($key)) {
            return null;
        }

        $castType = strtolower($this->casts[$key]);

        // Para decimales, extraer solo el tipo base
        if (str_starts_with($castType, 'decimal')) {
            return 'decimal';
        }

        // Para clases, retornar el nombre completo
        if (class_exists($castType) || interface_exists($castType)) {
            return $castType;
        }

        return $castType;
    }

    /**
     * Castea un atributo al tipo especificado
     *
     * @param string $key
     * @param mixed  $value
     * @param array  $attributes Todos los atributos (para casts personalizados)
     *
     * @return mixed
     */
    protected function castAttribute(string $key, mixed $value, array $attributes = []): mixed
    {
        if (!$this->hasCast($key)) {
            return $value;
        }

        $castType = $this->getCastType($key);

        // Si es null y es un tipo primitivo, retornar null
        if (is_null($value) && in_array($castType, static::$primitiveCastTypes)) {
            return null;
        }

        // Primero verificar si es una clase de cast personalizada
        if ($this->isClassCastable($key)) {
            return $this->castUsingClass($key, $value, $attributes);
        }

        // Verificar si es un Enum
        if ($this->isEnumCastable($key)) {
            return $this->castToEnum($key, $value);
        }

        // Casts primitivos
        return match ($castType) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => $this->castToFloat($value),
            'decimal' => $this->castToDecimal($key, $value),
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'object' => $this->castToObject($value),
            'array', 'json' => $this->castToArray($value),
            'collection' => $this->castToCollection($value),
            'date' => $this->castToDate($value),
            'datetime' => $this->castToDateTime($value),
            'immutable_date' => $this->castToImmutableDate($value),
            'immutable_datetime' => $this->castToImmutableDateTime($value),
            'timestamp' => $this->castToTimestamp($value),
            default => $value,
        };
    }

    /**
     * Determina si el cast es una clase personalizada que implementa CastsAttributes
     *
     * @param string $key
     *
     * @return bool
     */
    protected function isClassCastable(string $key): bool
    {
        if (!$this->hasCast($key)) {
            return false;
        }

        $castType = $this->casts[$key];

        if (!class_exists($castType)) {
            return false;
        }

        return is_subclass_of($castType, CastsAttributes::class);
    }

    /**
     * Determina si el cast es un Enum
     *
     * @param string $key
     *
     * @return bool
     */
    protected function isEnumCastable(string $key): bool
    {
        if (!$this->hasCast($key)) {
            return false;
        }

        $castType = $this->casts[$key];

        if (!class_exists($castType) && !enum_exists($castType)) {
            return false;
        }

        return enum_exists($castType);
    }

    /**
     * Castea usando una clase personalizada
     *
     * @param string $key
     * @param mixed  $value
     * @param array  $attributes
     *
     * @return mixed
     */
    protected function castUsingClass(string $key, mixed $value, array $attributes): mixed
    {
        $casterClass = $this->casts[$key];

        // Usar cache si existe
        if (!isset($this->castClassCache[$casterClass])) {
            $this->castClassCache[$casterClass] = new $casterClass();
        }

        return $this->castClassCache[$casterClass]->get($this, $key, $value, $attributes);
    }

    /**
     * Castea a un Enum
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return BackedEnum|null
     */
    protected function castToEnum(string $key, mixed $value): ?BackedEnum
    {
        if (is_null($value)) {
            return null;
        }

        $enumClass = $this->casts[$key];

        // Si ya es una instancia del enum, retornarla
        if ($value instanceof $enumClass) {
            return $value;
        }

        // Para BackedEnums
        if (is_subclass_of($enumClass, BackedEnum::class)) {
            return $enumClass::tryFrom($value);
        }

        return null;
    }

    /**
     * Castea a float
     *
     * @param mixed $value
     *
     * @return float
     */
    protected function castToFloat(mixed $value): float
    {
        return (float) $value;
    }

    /**
     * Castea a decimal con precisión
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return string
     */
    protected function castToDecimal(string $key, mixed $value): string
    {
        $castDefinition = $this->casts[$key];
        $decimals       = 2;

        if (str_contains($castDefinition, ':')) {
            $decimals = (int) Str::after($castDefinition, ':');
        }

        return number_format((float) $value, $decimals, '.', '');
    }

    /**
     * Castea a objeto
     *
     * @param mixed $value
     *
     * @return object|null
     */
    protected function castToObject(mixed $value): ?object
    {
        if (is_null($value)) {
            return null;
        }

        if (is_object($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value);

            return is_object($decoded) ? $decoded : (object) $decoded;
        }

        return (object) $value;
    }

    /**
     * Castea a array
     *
     * @param mixed $value
     *
     * @return array
     */
    protected function castToArray(mixed $value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [$value];
        }

        return (array) $value;
    }

    /**
     * Castea a Collection
     *
     * @param mixed $value
     *
     * @return Collection
     */
    protected function castToCollection(mixed $value): Collection
    {
        return new Collection($this->castToArray($value));
    }

    /**
     * Castea a Carbon date (sin hora)
     *
     * @param mixed $value
     *
     * @return Carbon|null
     */
    protected function castToDate(mixed $value): ?Carbon
    {
        $date = $this->asDateTime($value);

        return $date?->startOfDay();
    }

    /**
     * Castea a Carbon datetime
     *
     * @param mixed $value
     *
     * @return Carbon|null
     */
    protected function castToDateTime(mixed $value): ?Carbon
    {
        return $this->asDateTime($value);
    }

    /**
     * Castea a CarbonImmutable date
     *
     * @param mixed $value
     *
     * @return CarbonImmutable|null
     */
    protected function castToImmutableDate(mixed $value): ?CarbonImmutable
    {
        return $this->castToDate($value)?->toImmutable();
    }

    /**
     * Castea a CarbonImmutable datetime
     *
     * @param mixed $value
     *
     * @return CarbonImmutable|null
     */
    protected function castToImmutableDateTime(mixed $value): ?CarbonImmutable
    {
        return $this->castToDateTime($value)?->toImmutable();
    }

    /**
     * Castea a timestamp
     *
     * @param mixed $value
     *
     * @return int|null
     */
    protected function castToTimestamp(mixed $value): ?int
    {
        return $this->asDateTime($value)?->getTimestamp();
    }

    /**
     * Convierte un valor a Carbon
     *
     * @param mixed $value
     *
     * @return Carbon|null
     */
    protected function asDateTime(mixed $value): ?Carbon
    {
        if (is_null($value) || '' === $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof CarbonImmutable) {
            return Carbon::instance($value);
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Serializa un valor para output (inverso del cast)
     *
     * @param string $key
     * @param mixed  $value
     * @param array  $attributes
     *
     * @return mixed
     */
    protected function serializeAttribute(string $key, mixed $value, array $attributes = []): mixed
    {
        if (!$this->hasCast($key)) {
            return $this->serializeValue($value);
        }

        $castType = $this->getCastType($key);

        // Para clases personalizadas, usar el método set
        if ($this->isClassCastable($key)) {
            $casterClass = $this->casts[$key];

            if (!isset($this->castClassCache[$casterClass])) {
                $this->castClassCache[$casterClass] = new $casterClass();
            }

            return $this->castClassCache[$casterClass]->set($this, $key, $value, $attributes);
        }

        // Serialización por tipo - incluye primitivos para asegurar el tipo correcto
        return match ($castType) {
            // Primitivos - asegurar el tipo correcto en la salida
            'int', 'integer' => is_null($value) ? null : (int) $value,
            'real', 'float', 'double' => is_null($value) ? null : (float) $value,
            'decimal' => is_null($value) ? null : $this->castToDecimal($key, $value),
            'string' => is_null($value) ? null : (string) $value,
            'bool', 'boolean' => is_null($value) ? false : (bool) $value,
            'array', 'json' => $this->castToArray($value),
            // Fechas - formatear para output
            'date' => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value,
            'datetime' => $value instanceof DateTimeInterface ? $value->format($this->dateFormat) : $value,
            'immutable_date' => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value,
            'immutable_datetime' => $value instanceof DateTimeInterface ? $value->format($this->dateFormat) : $value,
            'timestamp' => $value instanceof DateTimeInterface ? $value->getTimestamp() : $value,
            // Objetos/colecciones
            'collection' => $value instanceof Collection ? $value->toArray() : $value,
            'object' => is_object($value) ? (array) $value : $value,
            default => $this->serializeEnumOrValue($key, $value),
        };
    }

    /**
     * Serializa un Enum o retorna el valor
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    protected function serializeEnumOrValue(string $key, mixed $value): mixed
    {
        if ($this->isEnumCastable($key) && $value instanceof BackedEnum) {
            return $value->value;
        }

        return $this->serializeValue($value);
    }

    /**
     * Serializa un valor genérico
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function serializeValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($this->dateFormat);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Aplica todos los casts a un array de atributos
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function castAllAttributes(array $attributes): array
    {
        foreach ($this->casts as $key => $type) {
            if (!array_key_exists($key, $attributes)) {
                continue;
            }

            $attributes[$key] = $this->castAttribute($key, $attributes[$key], $attributes);
        }

        return $attributes;
    }

    /**
     * Serializa todos los atributos para output
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function serializeAllAttributes(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->serializeAttribute($key, $value, $attributes);
        }

        return $attributes;
    }
}
