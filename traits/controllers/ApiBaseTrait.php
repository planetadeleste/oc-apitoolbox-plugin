<?php namespace PlanetaDelEste\ApiToolbox\Traits\Controllers;

use Event;
use PlanetaDelEste\ApiToolbox\Plugin;
use System\Classes\PluginManager;

trait ApiBaseTrait
{
    /**
     * @var \Lovata\Buddies\Models\User|\RainLab\User\Models\User|null
     */
    protected $user;

    /**
     * @var \Model
     */
    protected $obModel;

    /**
     * @var string
     */
    protected $modelClass;

    /**
     * API Resource collection class used for list items
     *
     * @var null|string
     */
    protected $listResource = null;

    /**
     * API Resource collection class used for index
     *
     * @var null|string
     */
    protected $indexResource = null;

    /**
     * API Resource class for load item
     *
     * @var null|string
     */
    protected $showResource = null;

    /**
     * @var bool
     */
    protected $exists = false;

    /**
     * @var \Lovata\Toolbox\Classes\Collection\ElementCollection
     */
    public $collection;

    /**
     * @var \Lovata\Toolbox\Classes\Item\ElementItem
     */
    public $item;

    /**
     * Primary column name for show element
     *
     * @var string
     */
    public $primaryKey = 'id';

    /**
     * Default sort by column
     *
     * @var string
     */
    public $sortColumn = 'no';

    /**
     * Default sort direction
     *
     * @var string
     */
    public $sortDirection = 'desc';

    /**
     * @return string
     */
    public function getModelClass()
    {
        return $this->modelClass;
    }

    /**
     * @return string|null
     */
    public function getListResource()
    {
        return $this->listResource;
    }

    /**
     * @return string|null
     */
    public function getIndexResource()
    {
        return $this->indexResource;
    }

    /**
     * @return string|null
     */
    public function getShowResource()
    {
        return $this->showResource;
    }

    /**
     * @return bool
     */
    public function exists()
    {
        return $this->exists;
    }

    /**
     * @return \Lovata\Buddies\Models\User|\RainLab\User\Models\User|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param string $message
     * @param array  $options
     *
     * @return string
     */
    public static function tr($message, $options = [])
    {
        if (!PluginManager::instance()->hasPlugin('RainLab.Translate')) {
            return $message;
        }

        if (!\RainLab\Translate\Models\Message::$locale) {
            \RainLab\Translate\Models\Message::setContext(
                \RainLab\Translate\Classes\Translator::instance()->getLocale()
            );
        }

        return \RainLab\Translate\Models\Message::trans($message, $options);
    }

    protected function setResources()
    {
        if ($this->getListResource()
            && $this->getIndexResource()
            && $this->getShowResource()
            || !$this->getModelClass()) {
            return;
        }

        $classname = ltrim(static::class, '\\');
        $arPath = explode('\\', $this->getModelClass());
        $name = array_pop($arPath);
        list($author, $plugin) = explode('\\', $classname);
        $resourceClassBase = join('\\', [$author, $plugin, 'Classes', 'Resource', $name]);
        $this->showResource = $resourceClassBase.'\\ShowResource';
        $this->listResource = $resourceClassBase.'\\ListCollection';
        $this->indexResource = $resourceClassBase.'\\IndexCollection';
    }

    /**
     * @return \Lovata\Toolbox\Classes\Collection\ElementCollection|null
     */
    protected function makeCollection()
    {
        if (!$this->getModelClass()) {
            return null;
        }

        // Initial empty collections
        $arCollectionClasses = [];
        $arResponseCollections = Event::fire(Plugin::EVENT_API_ADD_COLLECTION);
        if (!empty($arResponseCollections)) {
            foreach ($arResponseCollections as $arResponseCollection) {
                if (empty($arResponseCollection) || !is_array($arResponseCollection)) {
                    continue;
                }

                foreach ($arResponseCollection as $sKey => $sValue) {
                    $arCollectionClasses[$sKey] = $sValue;
                }
            }
        }

        if ($sCollectionClass = array_get($arCollectionClasses, $this->getModelClass())) {
            return forward_static_call([$sCollectionClass, 'make']);
        }

        return null;
    }
}
