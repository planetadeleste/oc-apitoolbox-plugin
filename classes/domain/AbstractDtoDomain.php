<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use Closure;
use PlanetaDelEste\ApiToolbox\Contracts\DtoDomainInterface;
use Str;
use TModel;

/**
 * Class AbstractDtoDomain
 *
 * Clase base para DTOs que facilita la creación mediante un método central de mapeo.
 * Los métodos específicos (toArrayData, fromArrayData, fromModelData) solo retornan
 * las claves/datos a mapear, y mapOutput() construye la salida final.
 *
 * @template TModel of \Model
 *
 * @implements DtoDomainInterface<TModel>
 *
 * @method static __construct(...$args)
 */
abstract class AbstractDtoDomain implements DtoDomainInterface
{
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
     */
    public static function fromArray(array $arData): static
    {
        try {
            $arMappedData = static::fromArrayData($arData);

            return new static(...$arMappedData);
        } catch (\Throwable $e) {
            // Log para debugging
            \Log::error('Error creating DTO from array', [
                'dto_class'   => static::class,
                'input_data'  => $arData,
                'mapped_data' => $arMappedData ?? null,
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
     */
    public static function fromArrayList(array $arDataList): array
    {
        return array_map(static fn($arData) => static::fromArray($arData), $arDataList);
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
     */
    public function toArray(): array
    {
        $sObjectHash = spl_object_hash($this);

        // Detectar recursión infinita
        if (isset(static::$processingStack[$sObjectHash])) {
            \Log::warning('Recursion detected in DTO toArray()', [
                'dto_class'   => static::class,
                'object_hash' => $sObjectHash,
                'stack_depth' => count(static::$processingStack),
            ]);

            // Retornar solo los datos básicos sin relaciones
            return $this->toArrayData();
        }

        // Marcar este objeto como en proceso
        static::$processingStack[$sObjectHash] = true;

        try {
            $arResult = $this->mapOutput($this->toArrayData());
        } catch (\Throwable $e) {
            \Log::error('Error in DTO toArray()', [
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
        return array_map(static fn($obDto) => $obDto?->toArray(), $arDtoList);
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

            $dtoProperty = $this->{Str::camel($sDtoKey)};

            if ($callback) {
                $callback($sRelation, $arData);

                if (array_get($arData, $sDtoKey)) {
                    continue;
                }
            }

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
   * Get the model instance
   *
   * @return TModel|mixed|null
   */
    public function getModel()
    {
        return $this->obModel;
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
        if (is_null($value) || $value === '' || $value === []) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Helper para castear a bool, convirtiendo strings/números a bool
     *
     * @param mixed $value
     *
     * @return bool|null
     */
    protected static function castToBool($value): ?bool
    {
        if (is_null($value) || $value === '') {
            return null;
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
        if (is_null($value) || $value === '') {
            return null;
        }

        return (string) $value;
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
