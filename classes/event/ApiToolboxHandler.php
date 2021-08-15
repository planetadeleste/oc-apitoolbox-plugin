<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Event;

use October\Rain\Events\Dispatcher;
use PlanetaDelEste\ApiToolbox\Plugin;

abstract class ApiToolboxHandler
{
    /**
     * @param \October\Rain\Events\Dispatcher $obEvent
     */
    public function subscribe(Dispatcher $obEvent)
    {
        $obEvent->listen(
            Plugin::EVENT_API_ADD_COLLECTION,
            function () {
                return $this->addCollections();
            }
        );

        $this->init($obEvent);
    }

    abstract protected function addCollections(): array;

    /**
     * @param \October\Rain\Events\Dispatcher $obEvent
     */
    public function init(Dispatcher $obEvent)
    {
    }
}
