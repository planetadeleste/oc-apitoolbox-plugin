<?php

namespace PlanetaDelEste\ApiToolbox\Traits\Store;

use Db;
use Illuminate\Database\Query\Builder;
use October\Rain\Database\Builder as EloquentBuilder;
use PlanetaDelEste\ApiToolbox\Plugin;

/**
 * @property string $sValue
 * @property array  $arListFromDB
 */
trait SortingListTrait
{
    /**
     * @var bool Set true to use DB instead of Model
     */
    protected bool $db = false;

    /**
     * @var array
     */
    protected array $arItems = [];

    /**
     * Get ID list from database
     *
     * @return array
     */
    protected function getIDListFromDB(): array
    {
        $arListFromDB = $this->getFieldList();
        $sValue       = $this->sValue;

        if (str_contains($sValue, '|')) {
            $this->arItems = explode('|', $sValue);
            $sValue        = count($this->arItems) >= 2 ? implode('|', [$this->arItems[0], $this->arItems[1]]) : $this->arItems[0];
        }

        if (($arSortData = array_get($arListFromDB, $sValue)) && ($sField = array_get($arSortData, 'field'))) {
            $sDir = array_get($arSortData, 'dir', 'asc');

            return method_exists($this, 'sortBy') ? $this->sortBy($sField, $sDir) : $this->orderBy($sField, $sDir);
        }

        return $this->getDefaultList();
    }

    /**
     * Construct array with dir,field keys
     *
     * @return array
     */
    protected function getFieldList(): array
    {
        $arFieldList  = ['created_at', 'name'];
        $arListFromDB = [];

        if (property_exists($this, 'arListFromDB')) {
            $arFieldList = $this->arListFromDB;
        }

        if (($arEventFieldList = \Event::fire(Plugin::EVENT_SORT_LIST, [$this->getModelClass()])) && is_array($arEventFieldList) && !empty($arEventFieldList)) {
            foreach ($arEventFieldList as $arEventFieldItem) {
                if (empty($arEventFieldItem)) {
                    continue;
                }

                if (is_array($arEventFieldItem)) {
                    foreach ($arEventFieldItem as $sEventItem) {
                        if (empty($sEventItem) || !is_string($sEventItem) || in_array($sEventItem, $arFieldList)) {
                            continue;
                        }

                        $arFieldList[] = $sEventItem;
                    }

                    continue;
                }

                $arFieldList[] = $arEventFieldItem;
            }
        }

        foreach ($arFieldList as $sFieldName) {
            $arListFromDB[$sFieldName.'|asc']  = [
                'dir'   => 'asc',
                'field' => $sFieldName,
            ];
            $arListFromDB[$sFieldName.'|desc'] = [
                'dir'   => 'desc',
                'field' => $sFieldName,
            ];
        }

        return $arListFromDB;
    }

    /**
     * @param string $sColumn
     * @param string $sDir
     *
     * @return array
     */
    protected function orderBy(string $sColumn = 'created_at', string $sDir = 'asc'): array
    {
        $sMethod = camel_case('get_'.$sColumn.'_list');

        if (method_exists($this, $sMethod)) {
            return $this->{$sMethod}($sDir);
        }

        $sModelClass = $this->getModelClass();
        $obQuery     = $this->db ? Db::table($this->getTable()) : (new $sModelClass())->query();

        $obQuery->orderBy($sColumn, $sDir);
        $this->wheres($obQuery);

        return $obQuery->pluck('id')->all();
    }

    /**
     * @param Builder|EloquentBuilder $obQuery
     *
     * @return void
     */
    protected function wheres(Builder | EloquentBuilder $obQuery): void
    {
        if (!count($this->arItems) > 2) {
            return;
        }

        foreach ($this->arItems as $sItem) {
            if (!str_contains($sItem, ':')) {
                continue;
            }

            [$sColumn, $sValue] = explode(':', $sItem);
            $obQuery->where($sColumn, $sValue);
        }
    }

    /**
     * Get default list
     *
     * @return array
     */
    protected function getDefaultList(): array
    {
        $sModelClass = $this->getModelClass();
        $obQuery     = $this->db ? Db::table($this->getTable()) : (new $sModelClass())->query();

        return $obQuery->pluck('id')->all();
    }

    protected function getTable()
    {
        $sModelClass = $this->getModelClass();

        return (new $sModelClass())->getTable();
    }

    /**
     * Get model class name
     *
     * @return string
     */
    abstract protected function getModelClass(): string;
}
