<?php

namespace PlanetaDelEste\ApiToolbox\Contracts;

use Model;

/**
 * Interface for Data Transfer Object (DTO) domain logic
 *
 * @template TModel of \Model
 */
interface DtoDomainInterface
{
    /**
     * Create a DTO instance from an request array of data
     *
     * @param array $data
     *
     * @return static
     */
    public static function fromRequest(array $data): static;

    /**
     * Convert the DTO to an array
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Create a DTO instance from a model
     *
     * @param TModel $model
     *
     * @return static
     */
    public static function from(Model $model): static;
}
