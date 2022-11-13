<?php

namespace PlanetaDelEste\ApiToolbox\Traits\Event;

use Lovata\Toolbox\Classes\Store\AbstractStoreWithoutParam;
use Lovata\Toolbox\Classes\Store\AbstractStoreWithParam;

/**
 * @property \Model|\Eloquent $obElement
 *
 * @method void checkFieldChanges(string $sField, AbstractStoreWithParam|AbstractStoreWithoutParam $obListStore)
 * @method void clearCacheEmptyValue(string $sField, AbstractStoreWithoutParam $obListStore)
 * @method void clearCacheNotEmptyValue(string $sField, AbstractStoreWithParam|AbstractStoreWithoutParam $obListStore)
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

    protected function checkFieldsChanges(array $arFieldList = ['active']): void
    {
        $sClassName = $this->getStoreClass();
        foreach ($arFieldList as $sFieldName) {
            $this->checkFieldChanges($sFieldName, $sClassName::instance()->{$sFieldName});
        }
    }

    /**
     * @param array $arFieldList Use key => value type if model property is not the same as cache field
     * @example ['customer' => 'customer_id'] customer is the cache field and customer_id is the model property name
     *
     * @return void
     */
    protected function clearCacheFields(array $arFieldList = []): void
    {
        if (empty($arFieldList)) {
            return;
        }

        $sClassName = $this->getStoreClass();
        foreach ($arFieldList as $sKey => $sFieldName) {
            $sField = is_numeric($sKey) ? $sFieldName : $sKey;
            $this->clearCacheNotEmptyValue($sFieldName, $sClassName::instance()->{$sField});
        }

    }

    protected function clearCacheEmptyFields(array $arFieldList = []): void
    {
        if (empty($arFieldList)) {
            return;
        }

        $sClassName = $this->getStoreClass();
        foreach ($arFieldList as $sFieldName) {
            $this->clearCacheEmptyValue($sFieldName, $sClassName::instance()->{$sFieldName});
        }
    }

    abstract protected function getStoreClass(): string;
}
