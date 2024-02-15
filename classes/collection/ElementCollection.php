<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Collection;

use Lovata\Toolbox\Classes\Collection\ElementCollection as ToolboxElementCollection;
use October\Rain\Support\Traits\Singleton;

abstract class ElementCollection extends ToolboxElementCollection
{
    protected function getInstance()
    {
        /** @var Singleton $sStoreClass */
        $sStoreClass = $this->getStoreClass();
        return $sStoreClass::instance();
    }

    /**
     * @param string     $sStore
     * @param mixed|null $aParams
     * @return self
     */
    protected function applyIntersect(string $sStore, mixed $aParams = null): static
    {
        $obStore        = $this->getInstance()->{$sStore};
        $arResultIDList = call_user_func_array([$obStore, 'get'], array_wrap($aParams));

        return $this->intersect($arResultIDList);
    }

    abstract protected function getStoreClass(): string;
}
