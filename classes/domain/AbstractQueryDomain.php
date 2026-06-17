<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use Cache;
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
 * @method void bindEvent(string $event, \Closure $callback, int $priority = 0)
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
     * @return string
     */
    public function getTable(): string
    {
        return $this->query->getModel()->getTable();
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

        $arColumns = $this->getModelColumns();

        foreach ($filters as $field => $value) {
            if (empty($value)) {
                continue;
            }

            $sMethod = Str::camel('filter_'.$field);

            if (method_exists($this, $sMethod)) {
                $this->$sMethod($value);

                continue;
            }

            // Validar que la columna exista en el modelo
            if (!in_array($field, $arColumns)) {
                continue;
            }

            $this->query->where($this->getTable().'.'.$field, $value);
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

        $sMethod = Str::camel('sort_by_'.str_replace('.', '_', $field));

        if (method_exists($this, $sMethod)) {
            $this->$sMethod($direction);

            return $this;
        }

        // Detectar si es ordenamiento por relación (ej: firm.name)
        if (str_contains($field, '.')) {
            $this->applySortingByRelation($field, $direction);

            return $this;
        }

        $this->query->orderBy($field, $direction);

        return $this;
    }

    /**
     * Aplicar ordenamiento por relación
     *
     * @param string $field     Campo con relación (ej: firm.name)
     * @param string $direction Dirección del ordenamiento
     *
     * @return void
     */
    protected function applySortingByRelation(string $field, string $direction = 'asc'): void
    {
        [$sRelation, $sColumn] = explode('.', $field, 2);

        $sModelClass   = $this->getModelClass();
        $obModel       = new $sModelClass();
        $obRelation    = $obModel->{$sRelation}();
        $sRelatedTable = $obRelation->getRelated()->getTable();
        $sMainTable    = $obModel->getTable();

        // Determinar las claves según el tipo de relación
        $sRelationClass = $obRelation::class;

        match (true) {
            $obRelation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo => $this->applySortingByBelongsTo(
                $sMainTable,
                $sRelatedTable,
                $obRelation,
                $sColumn,
                $direction
            ),
            $obRelation instanceof \Illuminate\Database\Eloquent\Relations\HasOne,
            $obRelation instanceof \Illuminate\Database\Eloquent\Relations\HasMany => $this->applySortingByHasOneOrMany(
                $sMainTable,
                $sRelatedTable,
                $obRelation,
                $sColumn,
                $direction
            ),
            $obRelation instanceof \Illuminate\Database\Eloquent\Relations\MorphOne,
            $obRelation instanceof \Illuminate\Database\Eloquent\Relations\MorphMany => $this->applySortingByMorph(
                $sMainTable,
                $sRelatedTable,
                $obRelation,
                $sColumn,
                $direction
            ),
            default => throw new \RuntimeException("Relation type {$sRelationClass} not supported for sorting")
        };
    }

    /**
     * Ordenar por relación BelongsTo
     */
    protected function applySortingByBelongsTo(
        string $sMainTable,
        string $sRelatedTable,
        \Illuminate\Database\Eloquent\Relations\BelongsTo $obRelation,
        string $sColumn,
        string $direction
    ): void {
        $sForeignKey = $obRelation->getForeignKeyName();
        $sOwnerKey   = $obRelation->getOwnerKeyName();

        $this->query
            ->select($sMainTable.'.*')
            ->leftJoin($sRelatedTable, $sMainTable.'.'.$sForeignKey, '=', $sRelatedTable.'.'.$sOwnerKey)
            ->orderBy($sRelatedTable.'.'.$sColumn, $direction);
    }

    /**
     * Ordenar por relación HasOne o HasMany
     */
    protected function applySortingByHasOneOrMany(
        string $sMainTable,
        string $sRelatedTable,
        \Illuminate\Database\Eloquent\Relations\HasOneOrMany $obRelation,
        string $sColumn,
        string $direction
    ): void {
        $sForeignKey = $obRelation->getForeignKeyName();
        $sLocalKey   = $obRelation->getLocalKeyName();

        $this->query
            ->select($sMainTable.'.*')
            ->leftJoin($sRelatedTable, $sMainTable.'.'.$sLocalKey, '=', $sRelatedTable.'.'.$sForeignKey)
            ->orderBy($sRelatedTable.'.'.$sColumn, $direction);
    }

    /**
     * Ordenar por relación Morph (polimórfica)
     */
    protected function applySortingByMorph(
        string $sMainTable,
        string $sRelatedTable,
        \Illuminate\Database\Eloquent\Relations\MorphOneOrMany $obRelation,
        string $sColumn,
        string $direction
    ): void {
        $sForeignKey = $obRelation->getForeignKeyName();
        $sLocalKey   = $obRelation->getLocalKeyName();
        $sMorphType  = $obRelation->getMorphType();
        $sMorphClass = $obRelation->getMorphClass();

        $this->query
            ->select($sMainTable.'.*')
            ->leftJoin($sRelatedTable, static function ($join) use ($sMainTable, $sRelatedTable, $sForeignKey, $sLocalKey, $sMorphType, $sMorphClass): void {
                $join->on($sMainTable.'.'.$sLocalKey, '=', $sRelatedTable.'.'.$sForeignKey)
                    ->where($sRelatedTable.'.'.$sMorphType, '=', $sMorphClass);
            })
            ->orderBy($sRelatedTable.'.'.$sColumn, $direction);
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
     * @return TModel|mixed|null
     */
    public function first()
    {
        return $this->query->first();
    }

    /**
     * Encontrar
     *
     * @param mixed $iId
     *
     * @return TModel|mixed|null
     */
    public function find(mixed $iId)
    {
        return $this->query->find($iId);
    }

    /**
     * Encontrar o fallar
     *
     * @param mixed $iId
     *
     * @return TModel|mixed
     */
    public function findOrFail(mixed $iId)
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

    /**
     * @param mixed $sValue
     *
     * @return static
     */
    public function filterSet(mixed $sValue): self
    {
        $sTable   = $this->getTable();
        $arIdList = is_string($sValue) && str_contains($sValue, '|')
          ? explode('|', $sValue)
          : array_wrap($sValue);
        $this->query->whereIn($sTable.'.id', $arIdList);

        return $this;
    }

    /**
     * Obtener las columnas del modelo con caché permanente
     *
     * @return array
     */
    protected function getModelColumns(): array
    {
        $sModelClass = $this->getModelClass();
        $sCacheKey   = 'model_columns_'.Str::slug($sModelClass);

        return Cache::rememberForever($sCacheKey, static function () use ($sModelClass) {
            $obModel = new $sModelClass();

            return $obModel->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($obModel->getTable());
        });
    }
}
