<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Resource;

use Illuminate\Http\Request;
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

    /**
     * Transform the resource into a JSON array.
     *
     * @param Request $request
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toAttributes(Request $request)
    {
        return $this->collection->map(static fn ($item) => $item->toArray($request))->all();
    }
}
