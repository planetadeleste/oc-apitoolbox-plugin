<?php namespace PlanetaDelEste\ApiToolbox\Traits\Controllers;

use Event;
use Kharanenka\Helper\Result;
use PlanetaDelEste\ApiToolbox\Classes\Helper\ApiHelper;
use PlanetaDelEste\ApiToolbox\Plugin;
use System\Classes\PluginManager;

/**
 * Trait ApiBaseTrait
 *
 * @package PlanetaDelEste\ApiToolbox\Traits\Controllers
 *
 * @property string $primaryKey
 * @property string $sortColumn
 * @property string $sortDirection
 */
trait ApiBaseTrait
{
    /**
     * @var \Lovata\Toolbox\Classes\Collection\ElementCollection
     */
    public $collection;
    /**
     * @var \Lovata\Toolbox\Classes\Item\ElementItem
     */
    public $item;
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
     * @param string      $message
     * @param array       $options
     * @param string|null $locale
     *
     * @return string
     * @deprecated Use ApiHelper::tr() instead
     */
    public static function tr(string $message, array $options = [], string $locale = null): string
    {
        return ApiHelper::tr($message, $options, $locale);
    }

    /**
     * @param \Exception $obException
     * @param int        $iStatus
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function exceptionResult(\Exception $obException, int $iStatus = 403): \Illuminate\Http\JsonResponse
    {
        trace_log($obException);
        Result::setFalse();
        if (!input('silently')) {
            Result::setMessage($obException->getMessage());
        }
        return response()->json(Result::get(), $iStatus);
    }

    /**
     * @return bool
     */
    public function exists(): bool
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
     * @return string|null
     */
    public function getSortColumn(): ?string
    {
        return $this->propertyExists('sortColumn') ? $this->sortColumn : null;
    }

    /**
     * @return string|null
     */
    public function getSortDirection(): ?string
    {
        return $this->propertyExists('sortDirection') ? $this->sortDirection : 'desc';
    }

    /**
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->propertyExists('primaryKey') ? $this->primaryKey : 'id';
    }

    protected function setResources(): void
    {
        if (($this->getListResource()
                && $this->getIndexResource()
                && $this->getShowResource())
            || !$this->getModelClass()) {
            return;
        }

        $classname = ltrim(static::class, '\\');
        $arPath = explode('\\', $this->getModelClass());
        $name = array_pop($arPath);
        [$author, $plugin] = explode('\\', $classname);
        $resourceClassBase = implode('\\', [$author, $plugin, 'Classes', 'Resource', $name]);
        $this->showResource = $resourceClassBase.'\\ShowResource';
        $this->listResource = $resourceClassBase.'\\ListCollection';
        $this->indexResource = $resourceClassBase.'\\IndexCollection';
    }

    /**
     * @return string|null
     */
    public function getListResource(): ?string
    {
        return $this->listResource;
    }

    /**
     * @return string|null
     */
    public function getIndexResource(): ?string
    {
        return $this->indexResource;
    }

    /**
     * @return string|null
     */
    public function getShowResource(): ?string
    {
        return $this->showResource;
    }

    /**
     * @return string|null
     */
    public function getModelClass(): ?string
    {
        return $this->modelClass;
    }

    /**
     * @return \Lovata\Toolbox\Classes\Collection\ElementCollection|null
     */
    protected function makeCollection(): ?\Lovata\Toolbox\Classes\Collection\ElementCollection
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
            return $this->collection = $sCollectionClass::make();
        }

        return null;
    }
}
