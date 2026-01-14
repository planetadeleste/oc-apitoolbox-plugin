<?php

namespace PlanetaDelEste\ApiToolbox\Contracts;

use Model;

/**
 * Interface ServiceDomainInterface
 *
 * @template TModel of \Model
 */
interface ServiceDomainInterface
{
    /**
     * Create a new model instance
     *
     * @param array $data
     *
     * @return TModel|null
     */
    public function create(array $data): ?Model;

    /**
     * Update an existing model instance
     *
     * @param TModel $obModel
     * @param array  $data
     *
     * @return TModel|null
     */
    public function update(Model $obModel, array $data): ?Model;

    /**
     * Delete a model instance
     *
     * @param TModel $obModel
     *
     * @return bool
     */
    public function delete(Model $obModel): bool;

    /**
     * Get the model class name
     *
     * @return class-string<TModel>
     */
    public function getModelClass(): string;
}
