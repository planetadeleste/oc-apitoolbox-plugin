<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Api;

use Cms\Classes\CmsObject;
use Cms\Classes\ComponentBase;
use Cms\Classes\ComponentManager;
use Eloquent;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\UploadedFile;
use Kharanenka\Helper\Result;
use Lovata\Buddies\Models\User;
use Lovata\Toolbox\Classes\Collection\ElementCollection;
use Lovata\Toolbox\Classes\Item\ElementItem;
use Model;
use October\Rain\Extension\Extendable;
use October\Rain\Support\Arr;
use PlanetaDelEste\ApiToolbox\Classes\Helper\ApiHelper;
use PlanetaDelEste\ApiToolbox\Plugin;
use PlanetaDelEste\ApiToolbox\Traits\Controllers\ApiBaseTrait;
use PlanetaDelEste\ApiToolbox\Traits\Controllers\ApiCastTrait;
use PlanetaDelEste\ApiToolbox\Traits\Controllers\ApiValidationTrait;
use RainLab\Translate\Classes\Translator;
use ReaZzon\JWTAuth\Classes\Guards\JWTGuard;
use RuntimeException;
use System\Classes\PluginManager;
use System\Models\File;
use System\Traits\EventEmitter;
use SystemException;

/**
 * Class Base
 *
 * @method void extendIndex()
 * @method void extendList()
 * @method void extendCount()
 * @method void extendShow()
 * @method void extendDestroy()
 * @method void extendSave()
 * @method void extendFilters(array &$filters)
 *
 * @package PlanetaDelEste\ApiToolbox\Classes\Api
 */
class Base extends Extendable
{
    use ApiBaseTrait;
    use ApiCastTrait;
    use ApiValidationTrait;
    use EventEmitter;

    public const ALERT_TOKEN_NOT_FOUND = 'token_not_found';
    public const ALERT_USER_NOT_FOUND = 'user_not_found';
    public const ALERT_JWT_NOT_FOUND = 'jwt_auth_not_found';
    public const ALERT_ACCESS_DENIED = 'access_denied';
    public const ALERT_PERMISSIONS_DENIED = 'insufficient_permissions';
    public const ALERT_RECORD_NOT_FOUND = 'record_not_found';
    public const ALERT_RECORDS_NOT_FOUND = 'records_not_found';
    public const ALERT_RECORD_UPDATED = 'record_updated';
    public const ALERT_RECORDS_UPDATED = 'records_updated';
    public const ALERT_RECORD_CREATED = 'record_created';
    public const ALERT_RECORD_DELETED = 'record_deleted';
    public const ALERT_RECORD_NOT_DELETED = 'record_not_deleted';
    public const ALERT_RECORD_NOT_UPDATED = 'record_not_updated';
    public const ALERT_RECORD_NOT_CREATED = 'record_not_created';

    /**
     * @var array
     */
    protected $data = [];

    /** @var array */
    public static array $components = [];

    /** @var int Items per page in pagination */
    public int $itemsPerPage = 10;

    protected array $arFileList = [
        'attachOne'  => ['preview_image'],
        'attachMany' => ['images']
    ];

    public function __construct()
    {
        parent::__construct();

        $this->setLocale();
        $this->init();
        $this->data = $this->getInputData();
        $this->setCastData($this->data);
        $this->setResources();
        $this->makeCollection();
        $this->applyFilters();
    }

    public function init(): void
    {
    }

    /**
     * @return LengthAwarePaginator|JsonResponse|ResourceCollection
     */
    public function index(): LengthAwarePaginator|JsonResponse|ResourceCollection
    {
        try {
            if ($this->methodExists('extendIndex')) {
                $this->extendIndex();
            }

            /**
             * Extend collection results
             */
            $this->fireSystemEvent(Plugin::EVENT_API_EXTEND_INDEX, [&$this->collection], false);

            $obModelCollection = $this->collection->paginate($this->getItemsPerPage());
            $sIndexResource    = $this->getIndexResource();
            $obResponse        = $sIndexResource
                ? new $sIndexResource($obModelCollection)
                : $obModelCollection;
            $this->fireSystemEvent(Plugin::EVENT_API_AFTER_INDEX, [$obResponse], false);

            return $obResponse;
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @return array|JsonResponse
     */
    public function list(): JsonResponse|array
    {
        try {
            if ($this->methodExists('extendList')) {
                $this->extendList();
            }

            /**
             * Extend collection results
             */
            $this->fireSystemEvent(Plugin::EVENT_API_EXTEND_LIST, [&$this->collection], false);

            $arListItems   = $this->collection->values();
            $sListResource = $this->getListResource();
            $obResponse    = $sListResource
                ? new $sListResource(collect($arListItems))
                : $arListItems;
            $this->fireSystemEvent(Plugin::EVENT_API_AFTER_LIST, [$obResponse], false);

            return $obResponse;
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    public function count(): JsonResponse|array
    {
        try {
            if ($this->methodExists('extendCount')) {
                $this->extendCount();
            }

            /**
             * Extend collection results
             */
            $this->fireSystemEvent(Plugin::EVENT_API_EXTEND_COUNT, [$this->collection], false);

            $fValue = $this->collection->count();

            $this->fireSystemEvent(Plugin::EVENT_API_AFTER_COUNT, [&$fValue], false);

            Result::setData(['count' => $fValue]);

            return Result::get();
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @param int|string $value
     *
     * @return JsonResponse|ElementItem
     */
    public function show(int|string $value): JsonResponse|ElementItem
    {
        try {
            /**
             * Fire event before show item
             */
            $this->fireSystemEvent(Plugin::EVENT_API_BEFORE_SHOW_COLLECT, [&$value], false);

            $iModelId = $this->getItemId($value);
            if (!$iModelId) {
                throw new RuntimeException(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            $this->item = $this->getItem($iModelId);

            if ($this->methodExists('extendShow')) {
                $this->extendShow();
            }

            /**
             * Extend collection results
             */
            $this->fireSystemEvent(Plugin::EVENT_API_EXTEND_SHOW, [$this->item]);

            $sShowResource = $this->getShowResource();
            $obResponse    = $sShowResource
                ? new $sShowResource($this->item)
                : $this->item;
            $this->fireSystemEvent(Plugin::EVENT_API_AFTER_SHOW, [$obResponse], false);

            return $obResponse;
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @return JsonResponse|string
     */
    public function store(): JsonResponse|string
    {
        try {
            $this->currentUser();

            $this->obModel = app($this->getModelClass());
            $this->exists  = false;
            $message       = ApiHelper::tr(static::ALERT_RECORD_NOT_CREATED);

            if (!$this->hasPermission('store')) {
                throw new RuntimeException(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            $this->fireSystemEvent(Plugin::EVENT_BEFORE_SAVE, [$this->obModel, &$this->data]);
            $this->validate();

            if ($this->save()) {
                $message = ApiHelper::tr(static::ALERT_RECORD_CREATED);
            }

            if (!Result::status() && Result::message()) {
                throw new RuntimeException(Result::message());
            }

            $obItem         = $this->getItem($this->obModel->id);
            $obResourceItem = $this->getShowResource()
                ? app($this->getShowResource(), [$obItem])
                : $obItem;

            return Result::setData($obResourceItem)
                ->setMessage($message)
                ->getJSON();
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @param int|string $id
     *
     * @return JsonResponse|string
     */
    public function update(int|string $id): JsonResponse|string
    {
        try {
            $this->currentUser();
            $this->obModel = app($this->getModelClass())->where($this->getPrimaryKey(), $id)->firstOrFail();
            $this->exists  = true;
            $message       = ApiHelper::tr(static::ALERT_RECORD_NOT_UPDATED);
            Result::setFalse();

            if (!$this->obModel) {
                throw new RuntimeException(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            if (!$this->hasPermission('update')) {
                throw new RuntimeException(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            $this->fireSystemEvent(Plugin::EVENT_BEFORE_SAVE, [$this->obModel, &$this->data]);
            $this->validate();

            if ($this->save()) {
                if (!Result::status() && Result::message()) {
                    throw new RuntimeException(Result::message());
                }

                Result::setTrue();
                $message = ApiHelper::tr(static::ALERT_RECORD_UPDATED);
            }

            $obItem         = $this->getItem($this->obModel->id);
            $obResourceItem = $this->getShowResource()
                ? app($this->getShowResource(), [$obItem])
                : $obItem;
            return Result::setData($obResourceItem)
                ->setMessage($message)
                ->getJSON();
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @param int|string $id
     * @return JsonResponse|string
     */
    public function attach(int|string $id): JsonResponse|string
    {
        try {
            $this->currentUser();
            $this->obModel = app($this->getModelClass())->where($this->getPrimaryKey(), $id)->firstOrFail();
            $this->exists  = true;
            $message       = ApiHelper::tr(static::ALERT_RECORD_NOT_UPDATED);
            Result::setFalse();

            if (!$this->obModel) {
                throw new RuntimeException(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            if (!$this->hasPermission('update')) {
                throw new RuntimeException(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            $this->fireSystemEvent(Plugin::EVENT_BEFORE_ATTACH, [$this->obModel, &$this->data]);
            $this->validate();

            if ($this->attachFiles(true)) {
                if (!Result::status() && Result::message()) {
                    throw new RuntimeException(Result::message());
                }

                Result::setTrue();
                $message = ApiHelper::tr(static::ALERT_RECORD_UPDATED);
            }

            $obItem         = $this->getItem($this->obModel->id);
            $obResourceItem = $this->getShowResource()
                ? app($this->getShowResource(), [$obItem])
                : $obItem;
            return Result::setData($obResourceItem)
                ->setMessage($message)
                ->getJSON();
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @param int|string $id
     *
     * @return JsonResponse|string
     */
    public function destroy(int|string $id): JsonResponse|string
    {
        try {
            $this->currentUser();
            $this->obModel = app($this->getModelClass())->where($this->getPrimaryKey(), $id)->firstOrFail();

            if (!$this->obModel) {
                throw new RuntimeException(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            if (!$this->hasPermission('destroy')) {
                throw new RuntimeException(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            $this->fireSystemEvent(Plugin::EVENT_BEFORE_DESTROY, [$this->obModel]);

            if ($this->obModel->delete()) {
                Result::setTrue()
                    ->setMessage(ApiHelper::tr(static::ALERT_RECORD_DELETED));
            } else {
                Result::setFalse()
                    ->setMessage(ApiHelper::tr(static::ALERT_RECORD_NOT_DELETED));
            }

            return Result::getJSON();
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @return bool
     */
    protected function save(): bool
    {
        $this->obModel->fill($this->data);
        if ($this->methodExists('extendSave')) {
            $this->extendSave();
        }

        return $this->saveAndAttach();
    }

    /**
     * @return bool
     */
    protected function saveAndAttach(): bool
    {
        $bResponse = $this->attachFiles($this->obModel->save());
        $this->fireSystemEvent(Plugin::EVENT_AFTER_SAVE, [$this->obModel, $this->data]);

        return $bResponse;
    }

    /**
     * Attach files related to model
     */
    protected function attachFiles(bool $bResponse = false): bool
    {
        $bSave = false;

        $arAttachOneAttrList = array_get($this->arFileList, 'attachOne');
        if (!empty($arAttachOneAttrList)) {
            $arAttachOneAttrList = array_wrap($arAttachOneAttrList);
            $bSave               = true;
            foreach ($arAttachOneAttrList as $sAttachOneKey) {
                $this->attachOne($sAttachOneKey);
            }
        }

        $arAttachManyAttrList = array_get($this->arFileList, 'attachMany');
        if (!empty($arAttachManyAttrList)) {
            $arAttachManyAttrList = array_wrap($arAttachManyAttrList);
            $bSave                = true;
            foreach ($arAttachManyAttrList as $sAttachManyKey) {
                $this->attachMany($sAttachManyKey);
            }
        }

        if ($bSave) {
            $this->fireSystemEvent(Plugin::EVENT_AFTER_ATTACH, [$this->obModel, $this->data]);
        }

        return $bSave ? $this->obModel->save() : $bResponse;
    }

    /**
     * Attach one file to model, using $arFileList array
     *
     * @param string     $sAttachKey
     * @param Model|null $obModel
     * @param bool       $save
     */
    protected function attachOne(string $sAttachKey, Model $obModel = null, bool $save = false): void
    {
        if (!$obModel) {
            if (!$this->obModel) {
                return;
            }
            $obModel = $this->obModel;
        }

        if ($obModel->hasRelation($sAttachKey)) {
            $obModel->load($sAttachKey);

            if (request()->hasFile($sAttachKey)) {
                $obFile = request()->file($sAttachKey);
                if ($obFile->isValid()) {
                    if ($obModel->{$sAttachKey} instanceof File) {
                        $obModel->{$sAttachKey}->delete();
                    }

                    $this->attachFile($obModel, $obFile, $sAttachKey);
                }
            } elseif (!input($sAttachKey)) {
                if ($obModel->{$sAttachKey} instanceof File) {
                    $obModel->{$sAttachKey}->delete();
                }
            }

            if ($save) {
                $obModel->save();
            }
        }
    }

    /**
     * Attach many files to model, using $arFileList array
     *
     * @param string     $sAttachKey
     * @param null|Model $obModel
     * @param bool       $save
     */
    protected function attachMany(string $sAttachKey, Model $obModel = null, bool $save = false): void
    {
        if (!$obModel) {
            if (!$this->obModel) {
                return;
            }

            $obModel = $this->obModel;
        }

        if ($obModel->hasRelation($sAttachKey)) {
            $obModel->load($sAttachKey);

            if (request()->hasFile($sAttachKey)) {
                $arFiles = request()->file($sAttachKey);
                if (!empty($arFiles)) {
                    if ($obModel->{$sAttachKey}->count()) {
                        $obModel->{$sAttachKey}->each(
                            function ($obImage) {
                                $obImage->delete();
                            }
                        );
                    }

                    foreach ($arFiles as $obFile) {
                        $this->attachFile($obModel, $obFile, $sAttachKey);
                    }
                }
            } elseif (!input($sAttachKey)) {
                if ($obModel->{$sAttachKey}->count()) {
                    $obModel->{$sAttachKey}->each(
                        function ($obImage) {
                            $obImage->delete();
                        }
                    );
                }
            }

            if ($save) {
                $obModel->save();
            }
        }
    }

    /**
     * @param Model        $obModel
     * @param UploadedFile $obFile
     * @param string       $sAttachKey
     */
    protected function attachFile(Model $obModel, UploadedFile $obFile, string $sAttachKey): void
    {
        $obSystemFile            = new File();
        $obSystemFile->data      = $obFile;
        $obSystemFile->is_public = true;
        $obSystemFile->save();

        $obModel->{$sAttachKey}()->add($obSystemFile);
    }

    /**
     * @return array
     */
    protected function getInputData(): array
    {
        $arData = array_wrap(input());

        foreach ($this->arFileList as $sRelationName => $arRelated) {
            if (empty($arRelated)) {
                continue;
            }
            $arRelated = array_wrap($arRelated);
            foreach ($arRelated as $sColumn) {
                array_forget($arData, $sColumn);
            }
        }

        return $arData;
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function hasPermission(string $action): bool
    {
        return true;
    }

    /**
     * @return JsonResponse
     */
    public function check(): JsonResponse
    {
        try {
            $group = null;
            if ($this->currentUser()) {
                $group = $this->user->getGroups();
                Result::setTrue(compact('group'));
            } else {
                Result::setFalse();
            }

            return response()->json(Result::get());
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @return JsonResponse
     */
    public function csrfToken(): JsonResponse
    {
        Result::setData(['token' => csrf_token()]);

        return response()->json(Result::get());
    }

    /**
     * @throws Exception
     */
    protected function currentUser(): Authenticatable|User|null
    {
        if ($this->user) {
            return $this->user;
        }

        /** @var JWTGuard $obJWTGuard */
        $obJWTGuard = app('JWTGuard');
        $this->user = $obJWTGuard->userOrFail();

        return $this->user;
    }

    /**
     * Check if api request get from backend or frontend
     *
     * @return bool
     */
    protected function isBackend(): bool
    {
        try {
            $this->currentUser();
            return ApiHelper::isBackend();
        } catch (Exception $ex) {
            return false;
        }
    }

    protected function userInGroup(string $sGroupCode): bool
    {
        try {
            $this->currentUser();
            $arGroups = $this->user->groups->lists('code');
            return in_array($sGroupCode, $arGroups);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return array
     *  [
     *      'sort' => [
     *          'column'    => 'created_at',
     *          'direction' => 'desc'
     *      ],
     *      'filters' => []
     *   ]
     */
    protected function filters(): array
    {
        $sortDefault = [
            'column'    => $this->getSortColumn(),
            'direction' => $this->getSortDirection()
        ];
        $sort        = get('sort', []);
        if (is_string($sort)) {
            $json = json_decode($sort, true);
            if (!json_last_error()) {
                $sort = $json;
            } else {
                $sort = ['column' => $sort];
            }
        }
        $sort = array_merge($sortDefault, $sort);

        if (!$filters = get('filters')) {
            $filters = get();
        }
        if (is_string($filters)) {
            $json = json_decode($filters, true);
            if (!json_last_error()) {
                $filters = $json;
            }
        }

        $obFilters = Filter::instance()->addFilters($filters);

        if ($this->methodExists('extendFilters')) {
            $this->extendFilters($filters);
        }

        $arFilters = $this->fireSystemEvent(Plugin::EVENT_BEFORE_FILTER, [$filters]);
        if (!empty($arFilters)) {
            $arFilters = array_wrap($arFilters);
            if (Arr::isAssoc($arFilters)) {
                $arFilters = [$arFilters];
            }

            foreach ($arFilters as $arFilter) {
                if (empty($arFilter) || !is_array($arFilter)) {
                    continue;
                }
                foreach ($arFilter as $sKey => $sValue) {
                    $obFilters->addFilter($sKey, $sValue);
                }
            }
        }

        if ($sFilter = array_get($filters, "sort")) {
            if (is_string($sFilter)) {
                $sort['column'] = $sFilter;
            } elseif (is_array($sFilter)) {
                $sort = array_merge($sort, $sFilter);
            }

            $obFilters->forget('sort');
        }

        $filters = $obFilters->get();

        return compact('sort', 'filters');
    }

    /**
     * @return ElementCollection|mixed|null
     */
    protected function applyFilters(): ?ElementCollection
    {
        if (!$this->collection) {
            return $this->collection;
        }

        $data         = $this->filters();
        $obFilters    = Filter::instance();
        $arFilters    = $obFilters->get();
        $arSort       = array_get($data, 'sort');
        $obCollection = $this->collection;

        if ($obCollection->methodExists('sort') && ($sSort = array_get($arSort, 'column'))) {
            if (($sDir = array_get($arSort, 'direction')) && !str_contains($sSort, '|')) {
                $sSort .= '|' . $sDir;
            }

            $obCollection->sort($sSort);
        }

        if (!empty($arFilters)) {
            if ($obCollection->methodExists('filter')) {
                $obCollection->filter($arFilters);
            }

            $arSkipMethods = $this->getSkipMethods();

            foreach ($arFilters as $sFilterName => $sFilterValue) {
                if (in_array($sFilterName, $arSkipMethods)) {
                    continue;
                }

                $sMethodName = camel_case($sFilterName);
                if ($obCollection->methodExists($sMethodName)) {
                    $obResult = call_user_func_array(
                        [$obCollection, $sMethodName],
                        $sFilterValue
                    );

                    if (is_array($obResult)) {
                        $obCollection->intersect(array_keys($obResult));
                    }
                }
            }
        }

        return $obCollection;
    }

    /**
     * @param string|int $sValue
     *
     * @return mixed
     */
    protected function getItemId($sValue): mixed
    {
        return ($this->getPrimaryKey() === 'id')
            ? $sValue
            : app($this->getModelClass())->where($this->getPrimaryKey(), $sValue)->value('id');
    }

    /**
     * @param int $iModelID
     *
     * @return ElementItem
     */
    protected function getItem(int $iModelID): ElementItem
    {
        /** @var ElementItem $sItemClass */
        $sItemClass = $this->collection::ITEM_CLASS;
        return $sItemClass::make($iModelID);
    }

    /**
     * @param string         $sName
     * @param CmsObject|null $cmsObject
     * @param array          $properties
     * @param bool           $isSoftComponent
     *
     * @return ComponentBase
     * @throws SystemException
     * @throws Exception
     */
    public function component(
        string    $sName,
        CmsObject $cmsObject = null,
        array     $properties = [],
        bool      $isSoftComponent = false
    ): ComponentBase
    {
        if (array_key_exists($sName, static::$components)) {
            return static::$components[$sName];
        }

        $component = ComponentManager::instance()->makeComponent($sName, $cmsObject, $properties, $isSoftComponent);
        if (!$component) {
            throw new RuntimeException('component not found');
        }

        static::$components[$sName] = $component;
        return $component;
    }

    /**
     * @param string $sNamespace
     *
     * @return bool
     */
    public function hasPlugin(string $sNamespace): bool
    {
        return PluginManager::instance()->hasPlugin($sNamespace);
    }

    /**
     * Set locale
     *
     * @return void
     */
    protected function setLocale(): void
    {
        if (!$this->hasPlugin('RainLab.Translate')) {
            return;
        }

        $obTranslate = Translator::instance();

        if (!$sActiveLangCode = request()->header('Accept-Language')) {
            $sActiveLangCode = $obTranslate->getLocale();
        }

        $obTranslate->setLocale($sActiveLangCode);
    }

    /**
     * @return int
     */
    protected function getItemsPerPage(): int
    {
        return (int)input('limit', $this->itemsPerPage);
    }
}
