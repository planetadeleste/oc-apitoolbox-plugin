<?php namespace PlanetaDelEste\ApiToolbox\Classes\Parser\Create;

use Lovata\Toolbox\Classes\Parser\Create\CommonCreateFile;

class ResourceItemCreateFile extends CommonCreateFile
{
    /** @var string */
    protected $sFile = '{{studly_model}}ItemResource.php';
    /** @var string */
    protected $sFolderPath = '/{{lower_author}}/{{lower_plugin}}/classes/resource/{{lower_model}}/';
    /** @var string */
    protected $sTemplatePath = '/planetadeleste/apitoolbox/classes/parser/templates/resource_item.stub';
}
