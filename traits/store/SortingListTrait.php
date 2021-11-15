<?php namespace PlanetaDelEste\ApiToolbox\Traits\Store;

/**
 * @property string $sValue
 */
trait SortingListTrait
{
    private $_arListFromDB = [
        'created_at|asc' => [
            'field' => 'created_at',
            'dir'   => 'asc'
        ],
        'created_at|desc' => [
            'field' => 'created_at',
            'dir'   => 'desc'
        ],
        'name|asc' => [
            'field' => 'name',
            'dir'   => 'asc'
        ],
        'name|desc' => [
            'field' => 'name',
            'dir'   => 'desc'
        ],
    ];

    protected $arListFromDB = [];

    /**
     * Get ID list from database
     *
     * @return array
     */
    protected function getIDListFromDB(): array
    {
        $arListFromDB = $this->_arListFromDB + $this->arListFromDB;
        if ($arSortData = array_get($arListFromDB, $this->sValue)) {
            if ($sField = array_get($arSortData, 'field')) {
                $sDir = array_get($arSortData, 'dir', 'asc');
                return $this->orderBy($sField, $sDir);
            }
        }

        return $this->getDefaultList();
    }

    /**
     * @param string $sColumn
     * @param string $sDir
     *
     * @return array
     */
    protected function orderBy(string $sColumn = 'created_at', string $sDir = 'asc'): array
    {
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
