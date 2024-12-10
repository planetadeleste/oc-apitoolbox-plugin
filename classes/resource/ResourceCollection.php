<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Resource;

use Illuminate\Http\Resources\Json\ResourceCollection as JsonResourceCollection;

class ResourceCollection extends JsonResourceCollection
{
    public function __construct($resource)
    {
        $this->init();
        parent::__construct($resource);
    }

    /**
     * @return void
     */
    public function init(): void
    {
    }
}