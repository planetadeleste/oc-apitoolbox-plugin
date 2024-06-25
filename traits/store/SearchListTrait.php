<?php

namespace PlanetaDelEste\ApiToolbox\Traits\Store;

use Eloquent;
use Model;
use October\Rain\Database\Builder;
use Str;

/**
 * Trait SearchListTrait
 * @package PlanetaDelEste\ApiToolbox\Traits\Store
 *
 * @property string $sValue
 */
trait SearchListTrait
{
    /** @var Builder|Model|Eloquent */
    protected Model|Builder|Eloquent $obQuery;

    /** @var bool Set true to strict all search, using "and" on each where, otherwise use "or" */
    protected bool $strict = false;

    /**
     * Get ID list from database
     *
     * @return array
     */
    protected function getIDListFromDB(): array
    {
        if (empty($this->sValue) || empty($this->columns())) {
            return [];
        }

        $obModelClass = $this->getModelClass();

        return $obModelClass::where(function ($obQuery): void {
            $this->obQuery = $obQuery;
            $this->wheres();
        })->lists($this->getKeyId());
    }

    protected function wheres(): void
    {
        $bool      = 'and';
        $arColumns = $this->columns();
        $sValue    = $this->parseValue();

        if (empty($sValue)) {
            return;
        }

        foreach ($arColumns as $sCol) {
            // Find for local scope methods
            $sScopeMethod = Str::camel('scope_'.$sCol);

            if (method_exists($this, $sScopeMethod)) {
                $this->{$sScopeMethod}($sValue, $bool);
            } else {
                $this->obQuery->where($sCol, $this->getLikeOperator(), "%{$sValue}%", $bool);
            }

            if ($this->strict) {
                continue;
            }

            $bool = 'or';
        }
    }

    /**
     * @return mixed|null
     */
    protected function parseValue(): mixed
    {
        if (empty($this->sValue)) {
            return null;
        }

        $sValue = $this->sValue;

        if (is_array($sValue)) {
            if (is_array($sValue[0])) {
                $sValue = $sValue[0];
            }

            if (count($sValue) === 1) {
                $sValue = $sValue[0];
            }
        }

        if (is_numeric($sValue)) {
            $sValue = (int) $sValue;
        }

        return $sValue;
    }

    protected function getLikeOperator(): string
    {
        return config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    protected function getKeyId(): string
    {
        return 'id';
    }

    /**
     * Get model columns where search
     *
     * @return array
     */
    abstract protected function columns(): array;

    /**
     * Get model class name
     *
     * @return string
     */
    abstract protected function getModelClass(): string;
}
