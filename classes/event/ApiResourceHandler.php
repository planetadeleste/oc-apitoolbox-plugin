<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Event;

use October\Rain\Events\Dispatcher;
use PlanetaDelEste\ApiToolbox\Classes\Resource\Base;

abstract class ApiResourceHandler
{
    public function subscribe(Dispatcher $obEvent)
    {
        $sResourceClass = $this->getResourceClass();
        $sEvent = $sResourceClass::make([])->event();
        if (!$sEvent) {
            return;
        }

        $obEvent->listen($sEvent, function ($obResourse, $arData) {
            return $this->extendResource($obResourse, $arData);
        });
    }

    /**
     * @return string
     */
    abstract protected function getResourceClass(): string;

    /**
     * @param Base  $obResource
     * @param array $arData
     *
     * @return array
     */
    abstract protected function extendResource(Base $obResource, array $arData): array;
}
