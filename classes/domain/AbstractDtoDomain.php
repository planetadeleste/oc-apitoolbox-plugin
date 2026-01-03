<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use Model;
use PlanetaDelEste\ApiToolbox\Contracts\DtoDomainInterface;

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
     * @param TModel $obModel
     *
     * @return static
     */
    public static function from(Model $obModel): static
    {
        return new static(...static::fromModelData($obModel));
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
     * @param TModel $obModel
     *
     * @return array
     */
    abstract protected static function fromModelData(Model $obModel): array;

    /**
     * Retorna los datos para convertir el DTO a array
     * Este método debe retornar un array asociativo con las claves finales
     * que se desean en la salida (normalmente en snake_case para DB)
     *
     * @return array
     */
    abstract protected function toArrayData(): array;
}
