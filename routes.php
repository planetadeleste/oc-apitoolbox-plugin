<?php

Route::prefix('api/v1')
    ->namespace('PlanetaDelEste\ApiToolbox\Classes\Api')
    ->middleware('web')
    ->group(function () {
        Route::get('load/{source}/{path?}', 'Base@loadFile')->name('load.file');
    });
