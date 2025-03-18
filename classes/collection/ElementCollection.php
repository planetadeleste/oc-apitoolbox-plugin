<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Collection;

use Lovata\Toolbox\Classes\Collection\ElementCollection as ToolboxElementCollection;
use Lovata\Toolbox\Classes\Item\ElementItem;
use Lovata\Toolbox\Classes\Store\AbstractListStore;
use PlanetaDelEste\ApiToolbox\Contracts\CollectionInterface;

/**
 * @template T of ElementItem
 * @implements CollectionInterface<T>
 */
abstract class ElementCollection extends ToolboxElementCollection implements CollectionInterface
{
    /**
     * @return class-string<T>
     */
    public static function getItemClass(): string
    {
        return static::ITEM_CLASS;
    }

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
        $arParams = empty($arParams) ? [] : array_wrap($arParams);

        if (!$obStore) {
            throw new \RuntimeException('Store '.$sStore.' not found in '.get_class($this->getInstance()));
        }

        if (\Arr::isAssoc($arParams)) {
            $arParams = array_except($arParams, ['page', 'limit']);

            if (($arSearch = array_get($arParams, 'search')) && is_array($arSearch)) {
                $arParams = $arSearch;
            }

            $arParams = [$arParams];
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
