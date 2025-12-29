<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Class AbstractResourceCollectionDomain
 *
 * @template TModel of \Model
 * @template TKey of array-key
 *
 * @extends ResourceCollection<TModel>
 *
 * @mixin \Illuminate\Http\Resources\Json\JsonResource<TModel>
 */
abstract class AbstractResourceCollectionDomain extends ResourceCollection
{
}
