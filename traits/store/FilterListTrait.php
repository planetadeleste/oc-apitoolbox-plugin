<?php

namespace PlanetaDelEste\ApiToolbox\Traits\Store;

use Carbon\Carbon;
use Eloquent;
use Lovata\OrdersShopaholic\Models\Order;
use Model;
use October\Rain\Database\Builder;
use Str;

/**
 * @property array $sValue
 */
trait FilterListTrait
{
    /** @var array<string> */
    protected array $arFields = [];

    /** @var bool Set true to strict all filters, using "and" on each where, otherwise use "or" */
    protected bool $strict = true;

    /** @var Builder|Model|Eloquent */
    protected $obQuery;

    /**
     * Get ID list from database
     *
     * @return array
     */
    protected function getIDListFromDB(): array
    {
        if (empty($this->sValue) || !is_array($this->sValue)) {
            return [];
        }

        $this->init();
        $this->sValue   = array_except($this->sValue, ['page', 'limit']);
        $this->arFields = array_keys($this->sValue);
        $obModelClass   = $this->getModelClass();

        return $obModelClass::where(function ($obQuery): void {
            $this->obQuery = $obQuery;
            $this->startScope();
            $this->wheres();
        })->lists($this->getKeyId());
    }

    /**
     * @return array<string>
     */
    protected function columns(): array
    {
        /** @var array $arColumns */
        $obModelClass = $this->getModelClass();
        $arColumns    = (new $obModelClass())->getFillable();
        $arColumns    = array_diff($arColumns, $this->skip());

        if (!empty($this->only())) {
            $arColumns = array_intersect($arColumns, $this->only());
        }

        return array_unique(array_merge($arColumns, $this->withColumns()));
    }

    /**
     * Define wich columns to search
     *
     * @return array<string>
     */
    protected function only(): array
    {
        return [];
    }

    /**
     * Define skip columns from search
     *
     * @return array<string>
     */
    protected function skip(): array
    {
        return ['id'];
    }

    /**
     * Add columns to search
     *
     * @return array<string>
     */
    protected function withColumns(): array
    {
        return [];
    }

    /**
     * @return void
     */
    protected function wheres(): void
    {
        $operator  = $this->getLikeOperator();
        $obQuery   = $this->obQuery;
        $bool      = 'and';
        $arColumns = $this->columns();

        foreach ($this->arFields as $sCol) {
            $sOperator = $operator;
            $sValue    = array_get($this->sValue, $sCol);

            if (empty($sValue)) {
                continue;
            }

            if (is_array($sValue)) {
                if (is_array($sValue[0])) {
                    $sValue = $sValue[0];
                }

                if (count($sValue) === 1) {
                    $sValue = $sValue[0];
                }
            }

            if (is_numeric($sValue) && ends_with($sCol, '_id')) {
                $sOperator = '=';
                $sValue    = (int) $sValue;
            }

            // Find for local scope methods
            $sScopeMethod = Str::camel('scope_'.$sCol);

            if (method_exists($this, $sScopeMethod)) {
                $this->{$sScopeMethod}($sValue);

                continue;
            }

            // Check for valid column
            if (!in_array($sCol, $arColumns)) {
                continue;
            }

            if (is_array($sValue)) {
                $obQuery->whereIn($sCol, $sValue, $bool);
            } else {
                $sValue = $sOperator === $this->getLikeOperator() ? "%{$sValue}%" : $sValue;
                $obQuery->where($sCol, $sOperator, $sValue, $bool);
            }

            if ($this->strict) {
                continue;
            }

            $bool = 'or';
        }
    }

    /**
     * @param mixed  $sValue
     * @param string $sColumn
     *
     * @return \Illuminate\Database\Query\Builder|Builder
     */
    protected function scopeDate($sValue, string $sColumn)
    {
        if (is_array($sValue) && count($sValue) === 1) {
            $sValue = array_first($sValue);
        } elseif (is_array($sValue) && count($sValue) > 1) {
            $sValue      = array_slice($sValue, 0, 2);
            $obStartDate = Carbon::parse($sValue[0])->startOfDay();
            $obEndDate   = Carbon::parse($sValue[1])->endOfDay();

            return $this->obQuery->whereBetween($sColumn, [$obStartDate, $obEndDate]);
        }

        return $this->obQuery->whereDate($sColumn, $sValue);
    }

    /**
     * @return void
     */
    protected function startScope(): void
    {
    }

    /**
     * @return string
     */
    protected function getLikeOperator(): string
    {
        return config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    /**
     * @return string
     */
    protected function getKeyId(): string
    {
        return 'id';
    }

    /**
     * Get model class name
     *
     * @return string
     */
    abstract protected function getModelClass(): string;
}
