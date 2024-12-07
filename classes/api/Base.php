<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Api;

use Cms\Classes\CmsObject;
use Cms\Classes\ComponentBase;
use Cms\Classes\ComponentManager;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Kharanenka\Helper\Result;
use Lovata\Buddies\Models\User;
use Lovata\Toolbox\Classes\Collection\ElementCollection;
use Lovata\Toolbox\Classes\Item\ElementItem;
use October\Rain\Extension\Extendable;
use October\Rain\Support\Arr;
use PlanetaDelEste\ApiToolbox\Classes\Helper\ApiHelper;
use PlanetaDelEste\ApiToolbox\Plugin;
use PlanetaDelEste\ApiToolbox\Traits\Controllers\ApiBaseTrait;
use PlanetaDelEste\ApiToolbox\Traits\Controllers\ApiCastTrait;
use PlanetaDelEste\ApiToolbox\Traits\Controllers\ApiValidationTrait;
use PlanetaDelEste\GW\Classes\Helper\MeasureHelper;
use RainLab\Translate\Classes\Translator;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    public const string ALERT_TOKEN_NOT_FOUND    = 'token_not_found';
    public const string ALERT_USER_NOT_FOUND     = 'user_not_found';
    public const string ALERT_JWT_NOT_FOUND      = 'jwt_auth_not_found';
    public const string ALERT_ACCESS_DENIED      = 'access_denied';
    public const string ALERT_PERMISSIONS_DENIED = 'insufficient_permissions';
    public const string ALERT_RECORD_NOT_FOUND   = 'record_not_found';
    public const string ALERT_RECORDS_NOT_FOUND  = 'records_not_found';
    public const string ALERT_RECORD_UPDATED     = 'record_updated';
    public const string ALERT_RECORDS_UPDATED    = 'records_updated';
    public const string ALERT_RECORD_CREATED     = 'record_created';
    public const string ALERT_RECORD_DELETED     = 'record_deleted';
    public const string ALERT_RECORD_NOT_DELETED = 'record_not_deleted';
    public const string ALERT_RECORD_NOT_UPDATED = 'record_not_updated';
    public const string ALERT_RECORD_NOT_CREATED = 'record_not_created';

    /**
     * @var array
     */
    public static array $components = [];

    /**
     * @var int Items per page in pagination
     */
    public int $itemsPerPage = 10;

    /**
     * @var array
     */
    protected $data = [];

    protected array $arFileList = [
        'attachOne'  => ['preview_image'],
        'attachMany' => ['images'],
    ];

    /**
     * @var bool Take measure of execution
     */
    protected bool $measure = false;

    /**
     * @var MeasureHelper|null
     */
    protected ?MeasureHelper $obMeasure = null;

    public function __construct()
    {
        if ($this->measure && env('APP_MEASURE', false)) {
            $this->obMeasure = MeasureHelper::instance();
            $this->obMeasure->start();
        }

        parent::__construct();

        $this->setLocale();
        $this->init();
        $this->setData($this->getInputData());
        $this->setResources();
        $this->makeCollection();
        $this->applyFilters();
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
     * @param string $sNamespace
     *
     * @return bool
     */
    public function hasPlugin(string $sNamespace): bool
    {
        return PluginManager::instance()->hasPlugin($sNamespace);
    }

    public function init(): void
    {
    }

    /**
     * @param array $arData
     *
     * @return $this
     */
    public function setData(array $arData = []): self
    {
        $this->setCastData($arData);
        $this->data = $arData;

        return $this;
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

        $this->log('before apply filters');

        if ($obCollection->methodExists($this->getSortMethod()) && ($sSort = array_get($arSort, 'column'))) {
            if (($sDir = array_get($arSort, 'direction')) && !str_contains($sSort, '|')) {
                $sSort .= '|'.$sDir;
            }

            $obCollection->{$this->getSortMethod()}($sSort);
            $this->log('after sort %s:%s', $this->getSortMethod(), $sSort);
        }

        if (!empty($arFilters)) {
            if ($obCollection->methodExists('filter')) {
                $obCollection->filter($arFilters);
                $this->log('after filter');
            }

            $arSkipMethods = $this->getSkipMethods();

            foreach ($arFilters as $sFilterName => $sFilterValue) {
                if (in_array($sFilterName, $arSkipMethods)) {
                    continue;
                }

                $sMethodName = camel_case($sFilterName);

                if (!$obCollection->methodExists($sMethodName)) {
                    continue;
                }

                $obResult = call_user_func_array(
                    [$obCollection, $sMethodName],
                    array_wrap($sFilterValue)
                );

                $this->log('after %s', $sMethodName);

                if (!is_array($obResult)) {
                    continue;
                }

                $obCollection->intersect(array_keys($obResult));
            }
        }

        return $obCollection;
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
            'direction' => $this->getSortDirection(),
        ];
        $sort        = get('sort', []);

        if (is_string($sort)) {
            $json = json_decode($sort, true);

            $sort = !json_last_error() ? $json : ['column' => $sort];
        }

        $sort    = array_merge($sortDefault, $sort);
        $filters = get('filters', []);

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

        if ($sFilter = array_get($filters, 'sort')) {
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
     * @return LengthAwarePaginator|JsonResponse|ResourceCollection
     */
    public function index(): LengthAwarePaginator|JsonResponse|ResourceCollection
    {
        try {
            $this->log('before extend index');
            $this->extendAction('index');
            $this->log('after extend index');

            /*
             * Extend collection results
             */
            $this->log('before extend index event');
            $this->fireSystemEvent(Plugin::EVENT_API_EXTEND_INDEX, [&$this->collection], false);
            $this->log('after extend index event');

            /** @var \PlanetaDelEste\ApiToolbox\Classes\Resource\Base $obResource */
            $obIndexResource = $this->makeResource([], $this->getIndexResource());
            $sItemResource   = $obIndexResource->collects;
            $obResource      = $this->makeResource([], $sItemResource);
            $arColumns       = $obResource->getColumns();

            if (!empty($arColumns) && $this->collection->isNotEmpty()) {
                $obModel    = $this->getModelObject();
                $arData     = $obModel->query()->select($arColumns)
                                 ->whereIn('id', $this->collection->getIDList())
                                 ->get();
                $obResponse = $this->makeResource($sItemResource::collection($arData), $this->getIndexResource());
            } else {
                $obModelCollection = $this->collection->paginate($this->getItemsPerPage());
                $obResponse        = $this->makeResource($obModelCollection, $this->getIndexResource());
            }

//            $this->log('before index event');
//            $this->fireSystemEvent(Plugin::EVENT_API_AFTER_INDEX, [$obResponse], false);
            $this->log('after index event');

            return $obResponse;
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @return Model
     */
    public function getModelObject(): Model
    {
        $sClass = $this->getModelClass();

        return new $sClass();
    }

    /**
     * @return int
     */
    protected function getItemsPerPage(): int
    {
        return (int) input('limit', $this->itemsPerPage);
    }

    /**
     * @return array|JsonResponse
     */
    public function list(): JsonResponse|array|ResourceCollection
    {
        try {
            $this->extendAction('list');

            /*
             * Extend collection results
             */
            $this->fireSystemEvent(Plugin::EVENT_API_EXTEND_LIST, [&$this->collection], false);

            $arListItems = $this->collection->values();
            $obResponse  = $this->makeResource(collect($arListItems), $this->getListResource());
            $this->fireSystemEvent(Plugin::EVENT_API_AFTER_LIST, [$obResponse], false);

            return $obResponse;
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @param int|string $value
     *
     * @return JsonResponse|ElementItem
     */
    public function show(int|string $value): JsonResponse|JsonResource
    {
        try {
            /*
             * Fire event before show item
             */
            $this->fireSystemEvent(Plugin::EVENT_API_BEFORE_SHOW_COLLECT, [&$value], false);

            $iModelId = $this->getItemId($value);

            if (!$iModelId) {
                throw new RuntimeException(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            $this->item = $this->getItem($iModelId);
            $this->extendAction('show');

            /*
             * Extend collection results
             */
            $this->fireSystemEvent(Plugin::EVENT_API_EXTEND_SHOW, [$this->item]);

            $obResponse = $this->makeResource($this->item, $this->getShowResource());

            $this->fireSystemEvent(Plugin::EVENT_API_AFTER_SHOW, [$obResponse], false);

            return $obResponse;
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
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
    protected function getItem(int|string $iModelID): ElementItem
    {
        /** @var ElementItem $sItemClass */
        $sItemClass = $this->collection::ITEM_CLASS;

        return $sItemClass::make($iModelID);
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

            $this->extendAction('store');
            $this->fireSystemEvent(Plugin::EVENT_BEFORE_SAVE, [$this->obModel, &$this->data]);
            $this->validate();

            if ($this->save()) {
                $message = ApiHelper::tr(static::ALERT_RECORD_CREATED);
            }

            if (!Result::status() && Result::message()) {
                throw new RuntimeException(Result::message());
            }

            $obItem         = $this->getItem($this->obModel->id);
            $obResourceItem = $this->makeResource($obItem, $this->getShowResource());

            return Result::setData($obResourceItem)
                ->setMessage($message)
                ->getJSON();
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
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
     * @param string $action
     *
     * @return bool
     */
    protected function hasPermission(string $action): bool
    {
        return true;
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
                if (!request()->hasFile($sAttachOneKey)) {
                    continue;
                }

                $this->attachOne($sAttachOneKey);
            }
        }

        $arAttachManyAttrList = array_get($this->arFileList, 'attachMany');

        if (!empty($arAttachManyAttrList)) {
            $arAttachManyAttrList = array_wrap($arAttachManyAttrList);
            $bSave                = true;

            foreach ($arAttachManyAttrList as $sAttachManyKey) {
                if (!request()->hasFile($sAttachManyKey)) {
                    continue;
                }

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
    protected function attachOne(string $sAttachKey, ?Model $obModel = null, bool $save = false): void
    {
        if (!$obModel) {
            if (!$this->obModel) {
                return;
            }

            $obModel = $this->obModel;
        }

        if (!$obModel->hasRelation($sAttachKey)) {
            return;
        }

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

        if (!$save) {
            return;
        }

        $obModel->save();
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
     * Attach many files to model, using $arFileList array
     *
     * @param string     $sAttachKey
     * @param Model|null $obModel
     * @param bool       $save
     */
    protected function attachMany(string $sAttachKey, ?Model $obModel = null, bool $save = false): void
    {
        if (!$obModel) {
            if (!$this->obModel) {
                return;
            }

            $obModel = $this->obModel;
        }

        if (!$obModel->hasRelation($sAttachKey)) {
            return;
        }

        $obModel->load($sAttachKey);

        if (request()->hasFile($sAttachKey)) {
            $arFiles = array_wrap(request()->file($sAttachKey));

            if (!empty($arFiles)) {
                // if ($obModel->{$sAttachKey}->count()) {
                // $obModel->{$sAttachKey}->each(
                // function ($obImage) {
                // $obImage->delete();
                // }
                // );
                // }

                foreach ($arFiles as $obFile) {
                    $this->attachFile($obModel, $obFile, $sAttachKey);
                }
            }
        } elseif (!input($sAttachKey)) {
            if ($obModel->{$sAttachKey}->count()) {
                $obModel->{$sAttachKey}->each(
                    static function ($obImage): void {
                        $obImage->delete();
                    }
                );
            }
        }

        if (!$save) {
            return;
        }

        $obModel->save();
    }

    /**
     * Retrieves the count of items in the collection and returns it as a JSON response or an array.
     *
     * @return JsonResponse|array Returns a JSON response containing the count of items or an array with the count.
     *
     * @throws Exception If an error occurs during the count retrieval or the exception handling.
     */
    public function count(): JsonResponse|array
    {
        try {
            $this->extendAction('count');

            /*
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
     * @param int|string $id
     *
     * @return JsonResponse|string
     */
    public function update(int|string $id): JsonResponse|string
    {
        try {
            $this->currentUser();
            $this->setModel($id);
            $this->exists = true;
            $message      = ApiHelper::tr(static::ALERT_RECORD_NOT_UPDATED);
            Result::setFalse();

            if (!$this->obModel) {
                throw new RuntimeException(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            if (!$this->hasPermission('update')) {
                throw new RuntimeException(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            $this->extendAction('update');
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
            $obResourceItem = $this->makeResource($obItem, $this->getShowResource());

            return Result::setData($obResourceItem)
                ->setMessage($message)
                ->getJSON();
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @param string|int $id
     *
     * @return Model
     */
    protected function setModel(string|int $id): Model
    {
        $this->obModel = $this->getModelObject()
            ->query()
            ->where($this->getPrimaryKey(), $id)
            ->firstOrFail();

        return $this->obModel;
    }

    /**
     * @param int|string $id
     *
     * @return JsonResponse|string
     */
    public function attach(int|string $id): JsonResponse|string
    {
        try {
            $this->currentUser();
            $this->setModel($id);
            $this->exists = true;
            $message      = ApiHelper::tr(static::ALERT_RECORD_NOT_UPDATED);
            Result::setFalse();

            if (!$this->obModel) {
                throw new RuntimeException(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            if (!$this->hasPermission('attach')) {
                throw new RuntimeException(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            $this->fireSystemEvent(Plugin::EVENT_BEFORE_ATTACH, [$this->obModel, &$this->data]);

            if ($this->attachFiles(true)) {
                if (!Result::status() && Result::message()) {
                    throw new RuntimeException(Result::message());
                }

                Result::setTrue();
                $message = ApiHelper::tr(static::ALERT_RECORD_UPDATED);
            }

            $obItem         = $this->getItem($this->obModel->id);
            $obResourceItem = $this->makeResource($obItem, $this->getShowResource());

            return Result::setData($obResourceItem)
                ->setMessage($message)
                ->getJSON();
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * Loads a file from the specified path or the default storage path.
     *
     * @param string      $sSource The name of the file to load.
     * @param string|null $sPath   The path to the file. If not provided, the default storage path will be used.
     *
     * @throws RuntimeException If the file is not found.
     *
     * @return Response | BinaryFileResponse The file response.
     */
    public function loadFile(string $sSource, ?string $sPath = null): Response | BinaryFileResponse
    {
        try {
            if (!$sPath) {
                $sPath       = storage_path('app/uploads/public');
                $sSourcePath = \File::name($sSource);
                $arPathParts = array_slice(str_split($sSourcePath, 3), 0, 3);

                if (count($arPathParts) < 3) {
                    throw new RuntimeException(ApiHelper::tr('File :file not found', ['file' => $sSource]), 404);
                }

                $sPath .= '/'.implode('/', $arPathParts);
            }

            $sPath = rtrim($sPath, '/').'/'.$sSource;

            if (!\File::exists($sPath)) {
                throw new RuntimeException(ApiHelper::tr('File :file not found', ['file' => $sPath]), 404);
            }

// $sContent = \File::get($sPath);
            return response()->file($sPath);
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
            $this->setModel($id);

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
     * @return JsonResponse
     */
    public function check(): JsonResponse
    {
        try {
            if ($this->currentUser()) {
                $group = $this->user->getGroups();
                Result::setTrue(compact('group'));
            } else {
                Result::setFalse();
            }
        } catch (Exception $e) {
            Result::setFalse();
        } finally {
            return response()->json(Result::get());
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
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->obModel;
    }

    /**
     * @param string         $sName
     * @param CmsObject|null $cmsObject
     * @param array          $properties
     * @param bool           $isSoftComponent
     *
     * @return ComponentBase
     *
     * @throws SystemException
     * @throws Exception
     */
    public function component(
        string $sName,
        ?CmsObject $cmsObject = null,
        array $properties = [],
        bool $isSoftComponent = false
    ): ComponentBase {
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
     * @param string $sTitle
     * @param        ...$params
     *
     * @return void
     */
    protected function log(string $sTitle, ...$params): void
    {
        if (!$this->obMeasure) {
            return;
        }

        call_user_func_array([$this->obMeasure, 'log'], array_merge([$sTitle], array_wrap($params)));
    }
}
