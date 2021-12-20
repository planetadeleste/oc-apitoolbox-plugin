<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Parser\Create;

use Lovata\Toolbox\Classes\Parser\Create\CommonCreateFile;

class UpdateModelCreateFile extends CommonCreateFile
{
    /** @var string */
    protected $sFile = 'update_{{lower_controller}}_table.php';
    /** @var string */
    protected $sFolderPath = '/{{lower_author}}/{{lower_plugin}}/updates/{{lower_version}}/';
    /** @var string */
    protected $sTemplatePath = '/planetadeleste/apitoolbox/classes/parser/templates/update_model.stub';

}
