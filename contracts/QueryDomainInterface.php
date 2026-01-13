<?php

namespace PlanetaDelEste\ApiToolbox\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Model;
use October\Rain\Database\Builder;
use October\Rain\Database\Collection;

/**
 * Interface QueryDomainInterface
 *
 * @template TKey of array-key
 * @template TModel of Model
 */
interface QueryDomainInterface
{
    public function getQuery(): Builder;

    public function applyFilters(array $filters): self;

    public function applySorting(string $field, string $direction = 'asc'): self;

    public function applyPagination(int $perPage = 15, int $page = 1): self;

    public function withRelations(array $arRelations = []): self;

    /**
     * @return Collection<Tkey, TModel>
     */
    public function get(): Collection;

    /**
     * Get the first result
     *
     * @return TModel|mixed|null
     */
    public function first();

    /**
     * Encontrar
     *
     * @param int $iId
     *
     * @return TModel|mixed|null
     */
    public function find(int $iId);

    /**
     * Encontrar o fallar
     *
     * @param int $iId
     *
     * @return TModel|mixed
     */
    public function findOrFail(int $iId);

    public function paginate(int $perPage = 15): LengthAwarePaginator;
}
