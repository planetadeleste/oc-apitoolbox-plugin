<?php

namespace PlanetaDelEste\ApiToolbox\Traits\Model;

use Illuminate\Database\Query\Builder;

/**
 * @mixin \Model
 */
trait DbQueryTrait {

    /**
     * @return Builder
     */
    public static function dbQuery(): Builder
    {
        return \Db::table((new static)->getTable());
    }
}