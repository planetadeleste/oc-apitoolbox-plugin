<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Model;
use October\Rain\Database\Builder;
use October\Rain\Database\Collection;
use PlanetaDelEste\ApiToolbox\Contracts\QueryDomainInterface;
use PlanetaDelEste\ApiToolbox\Traits\EmitterTrait;
use Str;

/**
 * Class AbstractQueryDomain
 *
 * @template TModel of Model
 *
 * @implements QueryDomainInterface<TModel>
 *
 * @method void onBeforeFilters(array &$filters)
 * @method void onBeforeSorting(string &$field, string &$direction)
 */
abstract class AbstractQueryDomain implements QueryDomainInterface
{
    use EmitterTrait;

    protected ?string $mainEvent = 'query';

    /**
     * @var Builder
     */
    protected $query;

    /**
     * AbstractQueryDomain constructor.
     */
    public function __construct()
    {
        $modelClass  = $this->getModelClass();
        $this->query = $modelClass::query();
    }

    abstract public function getModelClass(): string;

    /**
     * Get the query builder instance
     * @return Builder
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Apply filters to the query
     * @param array $filters
     *
     * @return $this
     */
    public function applyFilters(array $filters = []): self
    {
        $this->withEvents();
        $this->fireBeforeEvent('filters', [&$filters]);

        foreach ($filters as $field => $value) {
            if (empty($value)) {
                continue;
            }

            $sMethod = Str::camel('filter_'.$field);

            if (method_exists($this, $sMethod)) {
                $this->$sMethod($value);

                continue;
            }

            $this->query->where($field, $value);
        }

        return $this;
    }

    /**
     * Apply sorting to the query
     * @param string $field
     * @param string $direction
     *
     * @return $this
     */
    public function applySorting(string $field, string $direction = 'asc'): self
    {
        $this->withEvents();
        $this->fireBeforeEvent('sorting', [&$field, &$direction]);
        $this->query->orderBy($field, $direction);

        return $this;
    }

    /**
     * Apply pagination to the query
     * @param int $perPage
     * @param int $page
     *
     * @return $this
     */
    public function applyPagination(int $perPage = 15, int $page = 1): self
    {
        $this->query->limit($perPage)->offset(($page - 1) * $perPage);

        return $this;
    }

    /**
     * Cargar relaciones
     *
     * @param array $arRelations
     *
     * @return $this
     */
    public function withRelations(array $arRelations = []): self
    {
        if (!empty($arRelations)) {
            $this->query->with($arRelations);
        }

        return $this;
    }

    /**
     * Register event listeners
     *
     * @return void
     */
    public function withEvents(): void
    {
    }

    /**
     * Execute the query and get results
     *
     * @return Collection
     */
    public function get(): Collection
    {
        return $this->query->get();
    }

    /**
     * Get the first result
     *
     * @return TModel|null
     */
    public function first(): ?Model
    {
        return $this->query->first();
    }

    /**
     * Encontrar
     *
     * @param mixed $iId
     *
     * @return TModel|null
     */
    public function find(mixed $iId): ?Model
    {
        return $this->query->find($iId);
    }

    /**
     * Encontrar o fallar
     *
     * @param mixed $iId
     *
     * @return TModel
     */
    public function findOrFail(mixed $iId): Model
    {
        return $this->query->findOrFail($iId);
    }

    /**
     * Get paginated results
     * @param int $perPage
     *
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query->paginate($perPage);
    }
}
