<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use Closure;
use PlanetaDelEste\ApiToolbox\Classes\Domain\Concerns\HasCasts;
use PlanetaDelEste\ApiToolbox\Contracts\DtoDomainInterface;
use Str;

/**
 * Class AbstractDtoDomain
 *
 * Clase base para DTOs que facilita la creación mediante un método central de mapeo.
 * Los métodos específicos (toArrayData, fromArrayData, fromModelData) solo retornan
 * las claves/datos a mapear, y mapOutput() construye la salida final.
 *
 * Soporta casting de atributos similar a Laravel Eloquent mediante la propiedad $casts.
 * @see HasCasts
 *
 * @template TModel of \Model
 *
 * @implements DtoDomainInterface<TModel>
 *
 * @property array $casts Define los casts para atributos, similar a Eloquent. Ejemplo:
 *                      protected array $casts = [
 *                          'is_active' => 'bool',
 *                          'created_at' => 'datetime',
 *                      ];
 *
 * @method static __construct(...$args)
 */
abstract class AbstractDtoDomain implements DtoDomainInterface
{
    use HasCasts;

    /**
     * @var TModel|mixed|null $obModel Instancia del modelo asociada al DTO
     */
    protected $obModel = null;

    /**
     * Stack para rastrear objetos en proceso y prevenir recursión infinita
     *
     * @var array<string, bool>
     */
    protected static array $processingStack = [];

    /**
     * Create a DTO instance from an array of data
     * Este método reemplaza a fromRequest y fromArray, ya que ambos procesan arrays
     *
     * @param array $arData
     *
     * @return static
     *
     * @throws \Throwable
     */
    public static function fromArray(array $arData): static
    {
        $arMappedData = null;

        try {
            $arMappedData = static::fromArrayData($arData);

            return new static(...$arMappedData);
        } catch (\Throwable $e) {
            // Log para debugging - limitar tamaño para evitar problemas de memoria
            \Illuminate\Support\Facades\Log::error('Error creating DTO from array', [
                'dto_class'   => static::class,
                'input_data'  => static::truncateForLog($arData),
                'mapped_data' => static::truncateForLog($arMappedData),
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * @param array $arDataList
     *
     * @return array<static>
     *
     * @throws \Throwable
     */
    public static function fromArrayList(array $arDataList): array
    {
        try {
            return array_map(static fn($arData) => static::fromArray($arData), $arDataList);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error creating DTO list from array', [
                'dto_class' => static::class,
                'count'     => count($arDataList),
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param array $obModelList
     *
     * @return array<static>
     */
    public static function fromModelList(array $obModelList): array
    {
        return array_map(static fn($obModel) => static::from($obModel), $obModelList);
    }

    /**
     * Alias de fromArray para compatibilidad con código existente
     *
     * @param array $arData
     *
     * @return static
     */
    public static function fromRequest(array $arData): static
    {
        return static::fromArray($arData);
    }

    /**
     * Create a DTO instance from a model
     *
     * @param TModel|mixed $obModel
     *
     * @return static
     */
    public static function from($obModel): static
    {
        $obDto = new static(...static::fromModelData($obModel));
        $obDto->withModel($obModel);

        return $obDto;
    }

    /**
     * Convert the DTO to an array
     *
     * @return array
     *
     * @throws \Throwable
     */
    public function toArray(): array
    {
        $sObjectHash = spl_object_hash($this);

        // Detectar recursión infinita
        if (isset(static::$processingStack[$sObjectHash])) {
            \Illuminate\Support\Facades\Log::warning('Recursion detected in DTO toArray()', [
                'dto_class'   => static::class,
                'object_hash' => $sObjectHash,
                'stack_depth' => count(static::$processingStack),
            ]);

            // Retornar solo los datos básicos sin relaciones
            return $this->serializeAllAttributes($this->toArrayData());
        }

        // Marcar este objeto como en proceso
        static::$processingStack[$sObjectHash] = true;

        try {
            $arResult = $this->mapOutput($this->toArrayData());
            $arResult = $this->serializeAllAttributes($arResult);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error in DTO toArray()', [
                'dto_class'   => static::class,
                'object_hash' => $sObjectHash,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            // Limpiar el stack al terminar
            unset(static::$processingStack[$sObjectHash]);
        }

        return $arResult;
    }

    /**
     * @param array<static> $arDtoList
     *
     * @return array
     */
    public function toArrayList(array $arDtoList): array
    {
        return array_map(static fn($obDto) => is_object($obDto) && method_exists($obDto, 'toArray') ? $obDto->toArray() : $obDto, $arDtoList);
    }

    /**
     * Clear the processing stack
     * Útil para testing o cuando necesites resetear el estado
     *
     * @return void
     */
    public static function clearProcessingStack(): void
    {
        static::$processingStack = [];
    }

    /**
     * Set the model instance for relation loading
     *
     * @param TModel|mixed $obModel
     *
     * @return static
     */
    public function withModel($obModel): static
    {
        $this->obModel = $obModel;

        return $this;
    }

    /**
     * @param TModel|mixed $obModel
     * @param string       $sDtoClass
     * @param string       $sKey
     *
     * @return mixed
     */
    public static function getDataFromModel(mixed $obModel, string $sDtoClass, string $sKey): mixed
    {
        if (is_null($obModel) || !$obModel->relationLoaded($sKey) || empty($obModel->{$sKey})) {
            return null;
        }

        if ($obModel->{$sKey} instanceof \Illuminate\Support\Collection) {
            return $sDtoClass::fromModelList($obModel->{$sKey}->all());
        }

        return $sDtoClass::from($obModel->{$sKey});
    }

    /**
     * Get the model instance
     *
     * @return TModel|mixed|null
     */
    public function getModel()
    {
        return $this->obModel;
    }

    /**
     * Trunca datos para logging, evitando problemas de memoria
     *
     * @param mixed $data
     * @param int   $maxDepth
     *
     * @return mixed
     */
    protected static function truncateForLog(mixed $data, int $maxDepth = 3): mixed
    {
        if (null === $data) {
            return null;
        }

        if (!is_array($data)) {
            if (is_object($data)) {
                return $data::class;
            }

            return $data;
        }

        if (0 === $maxDepth) {
            return '[...]';
        }

        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = static::truncateForLog($value, $maxDepth - 1);
        }

        return $result;
    }

    /**
     * Método central de mapeo que construye la salida final
     * Puede ser sobrescrito para aplicar transformaciones globales
     *
     * @param array $arData
     *
     * @return array
     */
    protected function mapOutput(array $arData): array
    {
        // Filtrar valores null si es necesario
        // return array_filter($arData, fn($value) => !is_null($value));

        return $arData;
    }

    /**
     * @param array        $arRelations Array de relaciones a mapear
     * @param array        $arData      Array de datos a mapear
     * @param Closure|null $callback    Opcional. Función callback para personalizar el mapeo de cada
     *                                  relación.
     *
     * @return void
     */
    protected function mapRelations(array $arRelations, array &$arData, ?Closure $callback = null): void
    {
        if (empty($arRelations)) {
            return;
        }

        $obModel     = $this->getModel();
        $bIncludeAll = !$obModel;

        foreach ($arRelations as $sRelation => $sDtoKey) {
            if (is_int($sRelation)) {
                $sRelation = $sDtoKey;
            }

            if ($callback) {
                $callback($sRelation, $arData, $obModel);

                if (array_get($arData, $sDtoKey)) {
                    continue;
                }
            }

            $sDtoPropertyKey = Str::camel($sDtoKey);
            $dtoProperty     = property_exists($this, $sDtoPropertyKey) ? $this->{$sDtoPropertyKey} : null;

            // Skip si no hay propiedad o si hay modelo y la relación no está cargada
            if (!$dtoProperty || (!$bIncludeAll && !$obModel->relationLoaded($sRelation))) {
                continue;
            }

            // Si la propiedad es un array vacío, skip
            if (is_array($dtoProperty) && empty($dtoProperty)) {
                continue;
            }

            $obRelationData = $obModel?->{$sRelation};

            // Determinar si es una colección/array
            $bIsCollection = is_array($dtoProperty)
                || ($obRelationData && ($obRelationData instanceof \Illuminate\Support\Collection || is_array($obRelationData)));

            $arItemData = $bIsCollection
                ? $this->toArrayList(is_array($dtoProperty) ? $dtoProperty : collect($dtoProperty)->values()->all())
                : $dtoProperty->toArray();

            $arData[$sDtoKey] = $arItemData;
        }
    }

    /**
     * Helper para castear a int, convirtiendo strings vacíos a null
     *
     * @param mixed $value
     *
     * @return int|null
     */
    protected static function castToInt($value): ?int
    {
        if (is_null($value) || '' === $value || [] === $value) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Helper para castear a float, convirtiendo null a null
     *
     * @param mixed $value
     *
     * @return float|null
     */
    protected static function castToFloat($value): ?float
    {
        if (is_null($value) || '' === $value || [] === $value) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Helper para castear a bool, convirtiendo strings/números a bool
     *
     * @param mixed     $value
     * @param bool|null $default Valor por defecto si el valor es null o vacío
     *
     * @return bool|null
     */
    protected static function castToBool($value, ?bool $default = false): ?bool
    {
        if (is_null($value) || '' === $value) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Helper para castear a string, convirtiendo null a null
     *
     * @param mixed $value
     *
     * @return string|null
     */
    protected static function castToString($value): ?string
    {
        if (is_null($value) || '' === $value) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Helper para castear a Carbon, convirtiendo strings/timestamps a Carbon
     *
     * @param mixed $value
     *
     * @return \Carbon\Carbon|null
     */
    protected static function castToDate($value): ?\Carbon\Carbon
    {
        if (is_null($value) || '' === $value) {
            return null;
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \Carbon\Carbon::instance($value);
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Retorna los datos para construir el DTO desde un array
     * Este método debe retornar un array asociativo con las claves que coincidan
     * con los parámetros del constructor del DTO
     *
     * @param array $arData
     *
     * @return array
     */
    abstract protected static function fromArrayData(array $arData): array;

    /**
     * Retorna los datos para construir el DTO desde un modelo
     * Este método debe retornar un array asociativo con las claves que coincidan
     * con los parámetros del constructor del DTO
     *
     * @param TModel|mixed $obModel
     *
     * @return array
     */
    abstract protected static function fromModelData($obModel): array;

    /**
     * Retorna los datos para convertir el DTO a array
     * Este método debe retornar un array asociativo con las claves finales
     * que se desean en la salida (normalmente en snake_case para DB)
     *
     * @return array
     */
    abstract protected function toArrayData(): array;
}
