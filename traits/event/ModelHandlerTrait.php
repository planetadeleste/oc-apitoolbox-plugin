<?php

namespace PlanetaDelEste\ApiToolbox\Traits\Event;

use Lovata\Toolbox\Classes\Store\AbstractStoreWithoutParam;
use Lovata\Toolbox\Classes\Store\AbstractStoreWithParam;

/**
 * @method void checkFieldChanges(string $sField, AbstractStoreWithParam|AbstractStoreWithoutParam $obListStore)
 */
trait ModelHandlerTrait
{
    protected function clearSorting(array $arFieldList = ['created_at', 'name'], string $sFieldName = 'sorting'): void
    {
        $sClassName = $this->getStoreClass();
        $arSortingFieldList = [];

        foreach ($arFieldList as $sFieldItemName) {
            $arSortingFieldList[] = $sFieldItemName.'|asc';
            $arSortingFieldList[] = $sFieldItemName.'|desc';
        }

        foreach ($arSortingFieldList as $sSortingFieldName) {
            $sClassName::instance()->{$sFieldName}->clear($sSortingFieldName);
        }
    }

    protected function checkFieldsChanges(array $arFieldList = ['active'])
    {
        $sClassName = $this->getStoreClass();
        foreach ($arFieldList as $sFieldName) {
            $this->checkFieldChanges($sFieldName, $sClassName::instance()->{$sFieldName});
        }
    }

    abstract protected function getStoreClass(): string;
}
