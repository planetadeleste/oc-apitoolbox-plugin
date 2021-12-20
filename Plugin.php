<?php namespace PlanetaDelEste\ApiToolbox;

use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiAll;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiController;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiResourceIndex;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiResourceItem;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiResourceList;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiResources;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiResourceShow;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiRoute;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateUpdateModel;
use System\Classes\PluginBase;

/**
 * Class Plugin
 *
 * @package PlanetaDelEste\ApiToolbox
 */
class Plugin extends PluginBase
{
    const EVENT_SHOWRESOURCE_DATA = 'planetadeleste.apitoolbox.resource.showData';
    const EVENT_ITEMRESOURCE_DATA = 'planetadeleste.apitoolbox.resource.itemData';

    const EVENT_API_EXTEND_INDEX = 'planetadeleste.apitoolbox.controller.extendIndex';
    const EVENT_API_EXTEND_LIST = 'planetadeleste.apitoolbox.controller.extendList';
    const EVENT_API_EXTEND_SHOW = 'planetadeleste.apitoolbox.controller.extendShow';
    const EVENT_API_BEFORE_SHOW_COLLECT = 'planetadeleste.apitoolbox.controller.beforeShowCollect';
    const EVENT_API_EXTEND_STORE = 'planetadeleste.apitoolbox.controller.extendStore';
    const EVENT_API_EXTEND_UPDATE = 'planetadeleste.apitoolbox.controller.extendUpdate';
    const EVENT_API_EXTEND_DESTROY = 'planetadeleste.apitoolbox.controller.extendDestroy';
    const EVENT_API_PERMISSIONS = 'planetadeleste.apitoolbox.apiPermissions';

    const EVENT_API_ADD_COLLECTION = 'planetadeleste.apitoolbox.controller.addCollection';

    const EVENT_BEFORE_FILTER = 'planetadeleste.apitoolbox.controller.beforeFilter';
    const EVENT_BEFORE_DESTROY = 'planetadeleste.apitoolbox.controller.beforeDestroy';
    const EVENT_AFTER_DESTROY = 'planetadeleste.apitoolbox.controller.afterDestroy';
    const EVENT_BEFORE_SAVE = 'planetadeleste.apitoolbox.controller.beforeSave';
    const EVENT_AFTER_SAVE = 'planetadeleste.apitoolbox.controller.afterSave';

    const EVENT_LOCAL_EXTEND_INDEX = 'apitoolbox.controller.extendIndex';
    const EVENT_LOCAL_EXTEND_LIST = 'apitoolbox.controller.extendList';
    const EVENT_LOCAL_EXTEND_SHOW = 'apitoolbox.controller.extendShow';
    const EVENT_LOCAL_BEFORE_FILTER = 'apitoolbox.controller.beforeFilter';
    const EVENT_LOCAL_BEFORE_DESTROY = 'apitoolbox.controller.beforeDestroy';
    const EVENT_LOCAL_AFTER_DESTROY = 'apitoolbox.controller.afterDestroy';
    const EVENT_LOCAL_BEFORE_SAVE = 'apitoolbox.controller.beforeSave';
    const EVENT_LOCAL_AFTER_SAVE = 'apitoolbox.controller.afterSave';

    public $require = ['Lovata.Toolbox', 'Lovata.Buddies'];

    public function register()
    {
        $this->registerConsoleCommand('toolbox:create.api.all', CreateApiAll::class);
        $this->registerConsoleCommand('toolbox:create.api.controller', CreateApiController::class);
        $this->registerConsoleCommand('toolbox:create.api.resources', CreateApiResources::class);
        $this->registerConsoleCommand('toolbox:create.api.resourceindex', CreateApiResourceIndex::class);
        $this->registerConsoleCommand('toolbox:create.api.resourcelist', CreateApiResourceList::class);
        $this->registerConsoleCommand('toolbox:create.api.resourceitem', CreateApiResourceItem::class);
        $this->registerConsoleCommand('toolbox:create.api.resourceshow', CreateApiResourceShow::class);
        $this->registerConsoleCommand('toolbox:create.api.route', CreateApiRoute::class);
        $this->registerConsoleCommand('toolbox:create.model.update', CreateUpdateModel::class);
    }
}
