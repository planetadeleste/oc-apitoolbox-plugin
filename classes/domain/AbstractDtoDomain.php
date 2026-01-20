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
     * Create a DTO instance from an array of data
     * Este método reemplaza a fromRequest y fromArray, ya que ambos procesan arrays
     *
     * @param array $arData
     *
     * @return static
     */
    public static function fromArray(array $arData): static
    {
        return new static(...static::fromArrayData($arData));
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
        return $this->mapOutput($this->toArrayData());
    }

    /**
     * @param array<static> $arDtoList
     *
     * @return array
     */
    public function toArrayList(array $arDtoList): array
    {
        return array_map(static fn($obDto) => $obDto->toArray(), $arDtoList);
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

            if ((!$bIncludeAll && !$obModel->relationLoaded($sRelation)) || !$dtoProperty) {
                continue;
            }

            $obRelationData = $obModel?->{$sRelation};
            $arItemData     = (is_array($obRelationData) || $obRelationData instanceof \Illuminate\Support\Collection)
                ? $this->toArrayList(collect($dtoProperty)->values()->all())
                : $dtoProperty->toArray();

            $arData[$sDtoKey] = $arItemData;
        }
    }

  /**
   * Get the model instance
   *
   * @return TModel|mixed|null
   */
    protected function getModel()
    {
        return $this->obModel;
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
