<?php namespace PlanetaDelEste\ApiToolbox\Classes\Parser\Create;

use Lovata\Toolbox\Classes\Parser\Create\CommonCreateFile;

class ResourceListCreateFile extends CommonCreateFile
{
    /** @var string */
    protected $sFile = '{{studly_model}}ListCollection.php';
    /** @var string */
    protected $sFolderPath = '/{{lower_author}}/{{lower_plugin}}/classes/resource/{{lower_model}}/';
    /** @var string */
    protected $sTemplatePath = '/planetadeleste/apitoolbox/classes/parser/templates/resource_list_collection.stub';
}
