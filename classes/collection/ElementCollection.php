<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Collection;

use Lovata\Toolbox\Classes\Collection\ElementCollection as ToolboxElementCollection;
use Lovata\Toolbox\Classes\Store\AbstractStore;

abstract class ElementCollection extends ToolboxElementCollection
{
    protected function getInstance()
    {
        /** @var AbstractStore $sStoreClass */
        $sStoreClass = $this->getStoreClass();
        return $sStoreClass::instance();
    }

    /**
     * @param string     $sStore
     * @param mixed|null $aParams
     * @param bool       $bCache Set false to not use cache
     * @return self
     */
    protected function applyIntersect(string $sStore, mixed $aParams = null, bool $bCache = true): static
    {
        $sMethod        = $bCache ? 'get' : 'getNoCache';
        $obStore        = $this->getInstance()->{$sStore};
        $arResultIDList = call_user_func_array([$obStore, $sMethod], array_wrap($aParams));

        return $this->intersect($arResultIDList);
    }

    abstract protected function getStoreClass(): string;
}
