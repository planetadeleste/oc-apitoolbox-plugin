<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Api;

use October\Rain\Support\Traits\Singleton;

class Filter
{
    use Singleton;

    protected $arFilters = [];

    public function addFilter(string $sKey, $sValue, $arParams = []): self
    {
        $this->arFilters[$sKey] = array_merge([$sValue], $arParams);

        return $this;
    }

    public function addFilters(array $arFilters): self
    {
        foreach ($arFilters as $sKey => $sValue) {
            $this->addFilter($sKey, $sValue);
        }

        return $this;
    }

    public function forget(string $sKey): self
    {
        array_forget($this->arFilters, $sKey);

        return $this;
    }

    public function get(): array
    {
        return $this->arFilters;
    }

}
