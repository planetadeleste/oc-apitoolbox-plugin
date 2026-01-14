<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class AbstractResource
 *
 * Clase base para Resources que usan DTOs para estructurar la salida
 * Combina las ventajas de Resources (whenLoaded, when, etc.) con
 * la consistencia y tipado fuerte de los DTOs
 *
 * @template TModel of \Model
 * @template TDto of \PlanetaDelEste\ApiToolbox\Contracts\DtoDomainInterface
 */
abstract class AbstractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray(Request $request): array
    {
        // Obtener clase del DTO
        $sDtoClass = $this->getDtoClass();

        // Convertir modelo a DTO
        $dto = $sDtoClass::from($this->resource);

        // Obtener datos base del DTO
        $arData = $dto->toArray();

        // Permitir que las clases hijas agreguen relaciones condicionales
        return $this->withRelations($arData, $request);
    }

    /**
     * Método que puede ser sobrescrito para agregar relaciones condicionales
     * usando whenLoaded, when, etc.
     *
     * @param array                    $arData  Datos base del DTO
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    protected function withRelations(array $arData, Request $request): array
    {
        // Por defecto retorna solo los datos del DTO
        // Las clases hijas pueden sobrescribir este método para agregar relaciones
        return $arData;
    }

    /**
     * Retorna la clase del DTO a utilizar
     *
     * @return class-string<TDto>
     */
    abstract protected function getDtoClass(): string;
}
