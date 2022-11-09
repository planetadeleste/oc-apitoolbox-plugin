<?php namespace PlanetaDelEste\ApiToolbox\Traits\Store;

/**
 * @property string $sValue
 * @property array  $arListFromDB
 */
trait SortingListTrait
{
    /**
     * Get ID list from database
     *
     * @return array
     */
    protected function getIDListFromDB(): array
    {
        $arListFromDB = $this->getFieldList();
        if (($arSortData = array_get($arListFromDB, $this->sValue)) && ($sField = array_get($arSortData, 'field'))) {
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
        $arFieldList = ['created_at', 'name'];
        $arListFromDB = [];

        if (property_exists($this, 'arListFromDB')) {
            $arFieldList = $this->arListFromDB;
        }

        foreach ($arFieldList as $sFieldName) {
            $arListFromDB[$sFieldName.'|asc'] = [
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
        return $sModelClass::orderBy($sColumn, $sDir)->lists('id');
    }

    /**
     * Get default list
     *
     * @return array
     */
    protected function getDefaultList(): array
    {
        $sModelClass = $this->getModelClass();
        return (array)$sModelClass::lists('id');
    }

    /**
     * Get model class name
     *
     * @return string
     */
    abstract protected function getModelClass(): string;
}
