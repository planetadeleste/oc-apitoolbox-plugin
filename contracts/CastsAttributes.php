<?php

namespace PlanetaDelEste\ApiToolbox\Contracts;

/**
 * Interface CastsAttributes
 *
 * Define el contrato para clases de cast personalizadas.
 * Similar a Illuminate\Contracts\Database\Eloquent\CastsAttributes
 *
 * @template TGet
 * @template TSet
 */
interface CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param object               $object     The DTO or Model instance
     * @param string               $key        The attribute key
     * @param mixed                $value      The raw value
     * @param array<string, mixed> $attributes All attributes
     *
     * @return TGet|null
     */
    public function get(object $object, string $key, mixed $value, array $attributes): mixed;

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param object               $object     The DTO or Model instance
     * @param string               $key        The attribute key
     * @param TSet|null            $value      The value to set
     * @param array<string, mixed> $attributes All attributes
     *
     * @return mixed
     */
    public function set(object $object, string $key, mixed $value, array $attributes): mixed;
}
