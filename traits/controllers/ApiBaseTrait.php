<?php

namespace PlanetaDelEste\ApiToolbox\Traits\Controllers;

use Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Kharanenka\Helper\Result;
use Lovata\Buddies\Models\User;
use Lovata\Toolbox\Classes\Collection\ElementCollection;
use Lovata\Toolbox\Classes\Item\ElementItem;
use PlanetaDelEste\ApiToolbox\Classes\Helper\ApiHelper;
use PlanetaDelEste\ApiToolbox\Classes\Resource\Base;
use PlanetaDelEste\ApiToolbox\Plugin;

/**
 * Trait ApiBaseTrait
 *
 * @property string $primaryKey
 */
trait ApiBaseTrait
{
    /**
     * @var ElementCollection
     */
    public ?ElementCollection $collection = null;

    /**
     * @var ElementItem
     */
    public ?ElementItem $item = null;

    /**
     * @var User|\RainLab\User\Models\User|null
     */
    protected ?User $user = null;

    /**
     * @var \Model
     */
    protected ?Model $obModel = null;

    /**
     * @var string
     */
    protected ?string $modelClass = null;

    /**
     * API Resource collection class used for list items
     *
     * @var string|null
     */
    protected ?string $listResource = null;

    /**
     * API Resource collection class used for index
     *
     * @var string|null
     */
    protected ?string $indexResource = null;

    /**
     * API Resource class for load item
     *
     * @var string|null
     */
    protected ?string $showResource = null;

    /**
     * @var bool
     */
    protected bool $exists = false;

    /**
     * @var array Name of methods to skip on filter collection
     */
    protected static array $arSkipCollectionMethods = [];

    /**
     * @var string|null Column to sort by
     */
    protected ?string $sortColumn = null;

    /**
     * @var string|null Sort direction
     */
    protected ?string $sortDirection = null;

    /**
     * @var string Sort method name
     */
    protected string $sortMethod = 'sort';

    /**
     * @param string      $message
     * @param array       $options
     * @param string|null $locale
     *
     * @return string
     *
     * @deprecated Use ApiHelper::tr() instead
     */
    public static function tr(string $message, array $options = [], ?string $locale = null): string
    {
        return ApiHelper::tr($message, $options, $locale);
    }

    /**
     * @param \Exception $obException
     * @param int        $iStatus
     *
     * @return JsonResponse
     */
    public static function exceptionResult(\Exception $obException, int $iStatus = 403): JsonResponse
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
     * @return User|\RainLab\User\Models\User|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getSortMethod(): string
    {
        return $this->sortMethod;
    }

    /**
     * @return string|null
     */
    public function getSortColumn(): ?string
    {
        return $this->sortColumn;
    }

    /**
     * @return string|null
     */
    public function getSortDirection(): ?string
    {
        return $this->sortDirection;
    }

    /**
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->propertyExists('primaryKey') ? $this->primaryKey : 'id';
    }

    public function setCollection(ElementCollection $collection): self
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * @param string $sortMethod
     *
     * @return ApiBaseTrait|\PlanetaDelEste\ApiToolbox\Classes\Api\Base
     */
    public function setSortMethod(string $sortMethod): self
    {
        $this->sortMethod = $sortMethod;

        return $this;
    }

    /**
     * @param string|null $sortColumn
     *
     * @return ApiBaseTrait|\PlanetaDelEste\ApiToolbox\Classes\Api\Base
     */
    public function setSortColumn(?string $sortColumn): self
    {
        $this->sortColumn = $sortColumn;

        return $this;
    }

    /**
     * @param string|null $sortDirection
     *
     * @return ApiBaseTrait|\PlanetaDelEste\ApiToolbox\Classes\Api\Base
     */
    public function setSortDirection(?string $sortDirection): self
    {
        $this->sortDirection = $sortDirection;

        return $this;
    }

    /**
     * @return void
     */
    protected function setResources(): void
    {
        if (($this->getListResource()
            && $this->getIndexResource()
            && $this->getShowResource())
            || !$this->getModelClass()
        ) {
            return;
        }

        $classname           = ltrim(static::class, '\\');
        $arPath              = explode('\\', $this->getModelClass());
        $name                = array_pop($arPath);
        [$author, $plugin]   = explode('\\', $classname);
        $resourceClassBase   = implode('\\', [$author, $plugin, 'Classes', 'Resource', $name]);
        $this->showResource  = $resourceClassBase.'\\ShowResource';
        $this->listResource  = $resourceClassBase.'\\ListCollection';
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
     * @param $obData
     * @param string|null $sResource
     *
     * @return Base|ResourceCollection
     */
    protected function makeResource($obData, ?string $sResource = null): Base|ResourceCollection
    {
        return $sResource ? new $sResource($obData) : $obData;
    }

    /**
     * @return ElementCollection|null
     */
    protected function makeCollection(): ?ElementCollection
    {
        if (!$this->getModelClass()) {
            return null;
        }

        // Initial empty collections
        $arCollectionClasses   = [];
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

    /**
     * Provides a flexible and organized way to extend functionality based on different actions
     * @param string $action
     *
     * @return void
     */
    protected function extendAction(string $action): void
    {
        $sMethod = 'extend'.ucfirst($action);

        if (!$this->methodExists($sMethod)) {
            return;
        }

        $this->$sMethod();
    }

    protected function getSkipMethods(): array
    {
        if (!empty(self::$arSkipCollectionMethods)) {
            return self::$arSkipCollectionMethods;
        }

        return self::$arSkipCollectionMethods = array_diff(get_class_methods(ElementCollection::class), ['set', 'find']);
    }
}
