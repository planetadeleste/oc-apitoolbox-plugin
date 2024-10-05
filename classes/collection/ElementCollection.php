<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Collection;

use Lovata\Toolbox\Classes\Collection\ElementCollection as ToolboxElementCollection;
use Lovata\Toolbox\Classes\Store\AbstractListStore;

abstract class ElementCollection extends ToolboxElementCollection
{
    /**
     * @param string     $sStore
     * @param mixed|null $arParams
     * @param bool       $bCache   Set false to not use cache
     *
     * @return self
     */
    protected function applyIntersect(string $sStore, mixed $arParams = null, bool $bCache = true): static
    {
        $sMethod  = $bCache ? 'get' : 'getNoCache';
        $obStore  = $this->getInstance()->{$sStore};
        $arParams = empty($arParams) ? [] : array_except(array_wrap($arParams), ['page', 'limit']);

        if (($arSearch = array_get($arParams, 'search')) && is_array($arSearch)) {
            $arParams = $arSearch;
        }

        $arResultIDList = empty($arParams) ? call_user_func([$obStore, $sMethod]) : call_user_func_array([$obStore, $sMethod], $arParams);

        return $this->intersect($arResultIDList);
    }

    /**
     * @return AbstractListStore
     */
    protected function getInstance(): AbstractListStore
    {
        /** @var AbstractListStore $sStoreClass */
        $sStoreClass = $this->getStoreClass();

        return $sStoreClass::instance();
    }

    /**
     * @return string
     */
    abstract protected function getStoreClass(): string;
}
