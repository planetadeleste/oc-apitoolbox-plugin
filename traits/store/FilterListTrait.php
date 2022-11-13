<?php

namespace PlanetaDelEste\ApiToolbox\Traits\Store;

trait FilterListTrait
{
    /** @var string[] */
    protected array $arFields = [];

    /** @var bool Set true to strict all filters, using "and" on each where, otherwise use "or" */
    protected bool $strict = true;

    /** @var \October\Rain\Database\Builder|\Model|\Eloquent */
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

        $this->arFields = array_keys($this->sValue);
        $obModelClass = $this->getModelClass();

        return $obModelClass::where(function ($obQuery) {
            $this->obQuery = $obQuery;
            $this->wheres();
        })->lists('id');
    }

    /**
     * @return string[]
     */
    protected function columns(): array
    {
        /** @var array $arColumns */
        $obModelClass = $this->getModelClass();
        $arColumns = (new $obModelClass())->getFillable();
        $arColumns = array_diff($arColumns, $this->skip());

        return array_unique(array_merge($arColumns, $this->withColumns()));
    }

    /**
     * @return string[]
     */
    protected function skip(): array
    {
        return [];
    }

    /**
     * @return string[]
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
        $operator = $this->getLikeOperator();
        $obQuery = $this->obQuery;
        $bool = 'and';
        $arColumns = $this->columns();
        foreach ($this->arFields as $sCol) {
            $sOperator = $operator;
            $sValue = array_get($this->sValue, $sCol);
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
                $sValue = (int)$sValue;
            }

            // Find for local scope methods
            $sScopeMethod = \Str::camel('scope_'.$sCol);
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
                $obQuery->where($sCol, $sOperator, $sValue, $bool);
            }

            if (!$this->strict) {
                $bool = 'or';
            }
        }
    }

    protected function getLikeOperator(): string
    {
        return config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    /**
     * Get model class name
     *
     * @return string
     */
    abstract protected function getModelClass(): string;
}
