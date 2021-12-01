<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Parser\Create;

use Lovata\Toolbox\Classes\Parser\Create\CommonCreateFile;

class RouteCreateFile extends CommonCreateFile
{
    /** @var string */
    protected $sFile = '{{lower_controller}}.php';
    /** @var string */
    protected $sFolderPath = '/{{lower_author}}/{{lower_plugin}}/routes/';
    /** @var string */
    protected $sTemplatePath = '/planetadeleste/apitoolbox/classes/parser/templates/route.stub';
}
