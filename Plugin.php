<?php

namespace PlanetaDelEste\ApiToolbox;

use Event;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiAll;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiController;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiResourceIndex;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiResourceItem;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiResourceList;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiResources;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiResourceShow;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateApiRoute;
use PlanetaDelEste\ApiToolbox\Classes\Console\CreateUpdateModel;
use PlanetaDelEste\ApiToolbox\Classes\Event\Settings\ExtendSettingsFieldsHandler;
use System\Classes\PluginBase;

/**
 * Class Plugin
 *
 * @package PlanetaDelEste\ApiToolbox
 */
class Plugin extends PluginBase
{
    public const EVENT_SHOWRESOURCE_DATA = 'planetadeleste.apitoolbox.resource.showData';
    public const EVENT_ITEMRESOURCE_DATA = 'planetadeleste.apitoolbox.resource.itemData';

    public const EVENT_API_EXTEND_INDEX = 'planetadeleste.apitoolbox.controller.extendIndex';
    public const EVENT_API_AFTER_INDEX  = 'planetadeleste.apitoolbox.controller.afterIndex';

    public const EVENT_API_EXTEND_LIST = 'planetadeleste.apitoolbox.controller.extendList';
    public const EVENT_API_AFTER_LIST  = 'planetadeleste.apitoolbox.controller.afterList';

    public const EVENT_API_EXTEND_COUNT = 'planetadeleste.apitoolbox.controller.extendCount';
    public const EVENT_API_AFTER_COUNT  = 'planetadeleste.apitoolbox.controller.afterCount';

    public const EVENT_API_EXTEND_SHOW         = 'planetadeleste.apitoolbox.controller.extendShow';
    public const EVENT_API_BEFORE_SHOW_COLLECT = 'planetadeleste.apitoolbox.controller.beforeShowCollect';
    public const EVENT_API_AFTER_SHOW          = 'planetadeleste.apitoolbox.controller.afterShow';

    public const EVENT_API_EXTEND_STORE   = 'planetadeleste.apitoolbox.controller.extendStore';
    public const EVENT_API_EXTEND_UPDATE  = 'planetadeleste.apitoolbox.controller.extendUpdate';
    public const EVENT_API_EXTEND_DESTROY = 'planetadeleste.apitoolbox.controller.extendDestroy';
    public const EVENT_API_PERMISSIONS    = 'planetadeleste.apitoolbox.apiPermissions';

    public const EVENT_API_ADD_COLLECTION = 'planetadeleste.apitoolbox.controller.addCollection';

    public const EVENT_BEFORE_FILTER  = 'planetadeleste.apitoolbox.controller.beforeFilter';
    public const EVENT_BEFORE_DESTROY = 'planetadeleste.apitoolbox.controller.beforeDestroy';
    public const EVENT_AFTER_DESTROY  = 'planetadeleste.apitoolbox.controller.afterDestroy';
    public const EVENT_BEFORE_SAVE    = 'planetadeleste.apitoolbox.controller.beforeSave';
    public const EVENT_AFTER_SAVE     = 'planetadeleste.apitoolbox.controller.afterSave';
    public const EVENT_BEFORE_ATTACH  = 'planetadeleste.apitoolbox.controller.beforeAttach';
    public const EVENT_AFTER_ATTACH   = 'planetadeleste.apitoolbox.controller.afterAttach';

    // LOCAL EVENTS
    public const EVENT_LOCAL_EXTEND_INDEX   = 'apitoolbox.controller.extendIndex';
    public const EVENT_LOCAL_AFTER_INDEX    = 'apitoolbox.controller.afterIndex';
    public const EVENT_LOCAL_EXTEND_COUNT   = 'apitoolbox.controller.extendCount';
    public const EVENT_LOCAL_AFTER_COUNT   = 'apitoolbox.controller.afterCount';
    public const EVENT_LOCAL_EXTEND_LIST    = 'apitoolbox.controller.extendList';
    public const EVENT_LOCAL_EXTEND_SHOW    = 'apitoolbox.controller.extendShow';
    public const EVENT_LOCAL_BEFORE_FILTER  = 'apitoolbox.controller.beforeFilter';
    public const EVENT_LOCAL_BEFORE_DESTROY = 'apitoolbox.controller.beforeDestroy';
    public const EVENT_LOCAL_AFTER_DESTROY  = 'apitoolbox.controller.afterDestroy';
    public const EVENT_LOCAL_BEFORE_SAVE    = 'apitoolbox.controller.beforeSave';
    public const EVENT_LOCAL_AFTER_SAVE     = 'apitoolbox.controller.afterSave';

    public $require = ['Lovata.Toolbox', 'Lovata.Buddies'];

    public function boot()
    {
        Event::subscribe(ExtendSettingsFieldsHandler::class);
    }

    public function register(): void
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
