<?php namespace PlanetaDelEste\ApiToolbox;

use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiController;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateResource;
use System\Classes\PluginBase;

/**
 * Class Plugin
 *
 * @package PlanetaDelEste\ApiToolbox
 */
class Plugin extends PluginBase
{
    const EVENT_SHOWRESOURCE_DATA = 'planetadeleste.apiToolbox.showResourceData';
    const EVENT_ITEMRESOURCE_DATA = 'planetadeleste.apiToolbox.itemResourceData';
    const EVENT_API_EXTEND_INDEX = 'planetadeleste.apiToolbox.apiExtendIndex';
    const EVENT_API_EXTEND_LIST = 'planetadeleste.apiToolbox.apiExtendList';
    const EVENT_API_EXTEND_SHOW = 'planetadeleste.apiToolbox.apiExtendShow';
    const EVENT_API_BEFORE_SHOW_COLLECT = 'planetadeleste.apiToolbox.apiBeforeShowCollect';
    const EVENT_API_EXTEND_STORE = 'planetadeleste.apiToolbox.apiExtendStore';
    const EVENT_API_EXTEND_UPDATE = 'planetadeleste.apiToolbox.apiExtendUpdate';
    const EVENT_API_EXTEND_DESTROY = 'planetadeleste.apiToolbox.apiExtendDestroy';
    const EVENT_API_ADD_COLLECTION = 'planetadeleste.apiToolbox.apiAddCollection';

    public $require = ['Lovata.Toolbox', 'Lovata.Buddies'];

    public function register()
    {
        $this->registerConsoleCommand('apitoolbox:create:resource', CreateResource::class);
        $this->registerConsoleCommand('apitoolbox:create:controller', CreateApiController::class);
    }
}
