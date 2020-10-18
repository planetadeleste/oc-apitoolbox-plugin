<?php namespace PlanetaDelEste\ApiToolbox\Classes\Api;

use Cms\Classes\CmsObject;
use Cms\Classes\ComponentManager;
use Event;
use Exception;
use Illuminate\Http\UploadedFile;
use Kharanenka\Helper\Result;
use Lovata\Buddies\Models\User;
use Lovata\Toolbox\Classes\Collection\ElementCollection;
use October\Rain\Extension\Extendable;
use PlanetaDelEste\ApiToolbox\Plugin;
use PlanetaDelEste\ApiToolbox\Traits\Controllers\ApiBaseTrait;
use PlanetaDelEste\ApiToolbox\Traits\Controllers\ApiCastTrait;
use PlanetaDelEste\ApiToolbox\Traits\Controllers\ApiValidationTrait;
use System\Classes\PluginManager;
use System\Models\File;

/**
 * Class Base
 *
 * @method void extendIndex()
 * @method void extendList()
 * @method void extendShow()
 * @method void extendDestroy()
 * @method void extendFilters(array &$filters)
 *
 * @package PlanetaDelEste\ApiToolbox\Classes\Api
 */
class Base extends Extendable
{
    use ApiBaseTrait;
    use ApiCastTrait;
    use ApiValidationTrait;

    const ALERT_TOKEN_NOT_FOUND = 'token_not_found';
    const ALERT_USER_NOT_FOUND = 'user_not_found';
    const ALERT_JWT_NOT_FOUND = 'jwt_auth_not_found';
    const ALERT_ACCESS_DENIED = 'access_denied';
    const ALERT_PERMISSIONS_DENIED = 'insufficient_permissions';
    const ALERT_RECORD_NOT_FOUND = 'record_not_found';
    const ALERT_RECORDS_NOT_FOUND = 'records_not_found';
    const ALERT_RECORD_UPDATED = 'record_updated';
    const ALERT_RECORDS_UPDATED = 'records_updated';
    const ALERT_RECORD_CREATED = 'record_created';
    const ALERT_RECORD_DELETED = 'record_deleted';
    const ALERT_RECORD_NOT_DELETED = 'record_not_deleted';
    const ALERT_RECORD_NOT_UPDATED = 'record_not_updated';
    const ALERT_RECORD_NOT_CREATED = 'record_not_created';

    /**
     * @var array
     */
    protected $data = [];

    /** @var array */
    public static $components = [];

    /** @var int Items per page in pagination */
    public $itemsPerPage = 10;

    protected $arFileList = [
        'attachOne'  => ['preview_image'],
        'attachMany' => ['images']
    ];

    public function __construct()
    {
        parent::__construct();

        $this->data = input();
        $this->setCastData($this->data);
        $this->setResources();
        $this->collection = $this->makeCollection();
        $this->collection = $this->applyFilters();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            if ($this->methodExists('extendIndex')) {
                $this->extendIndex();
            }

            /**
             * Extend collection results
             */
            Event::fire(Plugin::EVENT_API_EXTEND_INDEX, [$this, &$this->collection]);

            $obModelCollection = $this->collection->paginate($this->itemsPerPage);
            return $this->getIndexResource()
                ? app($this->getIndexResource(), [$obModelCollection])
                : $obModelCollection;
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function list()
    {
        try {
            if ($this->methodExists('extendList')) {
                $this->extendList();
            }

            /**
             * Extend collection results
             */
            Event::fire(Plugin::EVENT_API_EXTEND_LIST, [$this, &$this->collection]);

            $arListItems = $this->collection->values();
            return $this->getListResource()
                ? app($this->getListResource(), [collect($arListItems)])
                : $arListItems;
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @param int|string $value
     *
     * @return \Illuminate\Http\JsonResponse|\Lovata\Toolbox\Classes\Item\ElementItem
     */
    public function show($value)
    {
        try {
            /**
             * Fire event before show item
             */
            Event::fire(Plugin::EVENT_API_BEFORE_SHOW_COLLECT, [$this, $value]);

            /** @var int|null $iModelId */
            $iModelId = app($this->getModelClass())->where($this->getPrimaryKey(), $value)->value('id');

            if (!$iModelId) {
                throw new Exception(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            /** @var \Lovata\Toolbox\Classes\Collection\ElementCollection $sItemClass */
            $sItemClass = $this->collection::ITEM_CLASS;
            $this->item = $sItemClass::make($iModelId);

            if ($this->methodExists('extendShow')) {
                $this->extendShow();
            }

            /**
             * Extend collection results
             */
            Event::fire(Plugin::EVENT_API_EXTEND_SHOW, [$this, $this->item]);

            return $this->getShowResource()
                ? app($this->getShowResource(), [$this->item])
                : $this->item;
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function store()
    {
        try {
            $this->currentUser();

            $this->obModel = app($this->getModelClass());
            $this->exists = false;
            $message = static::tr(static::ALERT_RECORD_NOT_CREATED);

            if (!$this->hasPermission('store')) {
                throw new Exception(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            $this->validate();

            if ($this->save()) {
                $message = static::tr(static::ALERT_RECORD_CREATED);
            }

            return Result::setData($this->obModel)
                ->setMessage($message)
                ->getJSON();
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @param int|string $id
     *
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function update($id)
    {
        try {
            $this->currentUser();
            $this->obModel = app($this->getModelClass())->where($this->getPrimaryKey(), $id)->firstOrFail();
            $this->exists = true;
            $message = static::tr(static::ALERT_RECORD_NOT_UPDATED);
            Result::setFalse();

            if (!$this->obModel) {
                throw new Exception(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            if (!$this->hasPermission('update')) {
                throw new Exception(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            $this->validate();

            if ($this->save()) {
                Result::setTrue();
                $message = static::tr(static::ALERT_RECORD_UPDATED);
            }

            return Result::setData($this->obModel)
                ->setMessage($message)
                ->getJSON();
        } catch (Exception $e) {
            return static::exceptionResult($e);
        }
    }

    /**
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function destroy($id)
    {
        try {
            $this->currentUser();
            $this->obModel = app($this->getModelClass())->where($this->getPrimaryKey(), $id)->firstOrFail();

            if (!$this->obModel) {
                throw new Exception(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            if (!$this->hasPermission('destroy')) {
                throw new Exception(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            if ($this->obModel->delete()) {
                Result::setTrue()
                    ->setMessage(static::tr(static::ALERT_RECORD_DELETED));
            } else {
                Result::setFalse()
                    ->setMessage(static::tr(static::ALERT_RECORD_NOT_DELETED));
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
        return $this->saveAndAttach();
    }

    /**
     * @return bool
     */
    protected function saveAndAttach(): bool
    {
        $bResponse = $this->obModel->save();
        $this->attachFiles();

        return $bResponse;
    }

    /**
     * Attach files related to model
     */
    protected function attachFiles()
    {
        $bSaveModel = false;
        $arAttachOneAttrList = array_get($this->arFileList, 'attachOne');
        if (!empty($arAttachOneAttrList)) {
            $arAttachOneAttrList = array_wrap($arAttachOneAttrList);
            $bSaveModel = true;
            foreach ($arAttachOneAttrList as $sAttachOneKey) {
                $this->attachOne($sAttachOneKey);
            }
        }

        $arAttachManyAttrList = array_get($this->arFileList, 'attachMany');
        if (!empty($arAttachManyAttrList)) {
            $arAttachManyAttrList = array_wrap($arAttachManyAttrList);
            $bSaveModel = true;
            foreach ($arAttachManyAttrList as $sAttachManyKey) {
                $this->attachMany($sAttachManyKey);
            }
        }

        if ($bSaveModel) {
            $this->obModel->save();
        }
    }

    /**
     * Attach one file to model, using $arFileList array
     *
     * @param string      $sAttachKey
     * @param null|\Model $obModel
     * @param bool        $save
     */
    protected function attachOne(string $sAttachKey, $obModel = null, $save = false)
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
            } elseif (!array_get($this->data, $sAttachKey)) {
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
     * @param string      $sAttachKey
     * @param null|\Model $obModel
     * @param bool        $save
     */
    protected function attachMany(string $sAttachKey, $obModel = null, $save = false)
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
            } elseif (!array_get($this->data, $sAttachKey)) {
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
     * @param \Model       $obModel
     * @param UploadedFile $obFile
     * @param string       $sAttachKey
     */
    protected function attachFile(\Model $obModel, UploadedFile $obFile, string $sAttachKey)
    {
        $obSystemFile = new File;
        $obSystemFile->data = $obFile;
        $obSystemFile->is_public = true;
        $obSystemFile->save();

        $obModel->{$sAttachKey}()->add($obSystemFile);
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(): \Illuminate\Http\JsonResponse
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function csrfToken(): \Illuminate\Http\JsonResponse
    {
        Result::setData(['token' => csrf_token()]);

        return response()->json(Result::get());
    }

    /**
     * @throws \Exception
     */
    protected function currentUser()
    {
        if ($this->user) {
            return $this->user;
        }

        if (!class_exists('JWTAuth')) {
            throw new Exception(static::tr(static::ALERT_JWT_NOT_FOUND));
        }

        if (!\JWTAuth::getToken()) {
            throw new Exception(static::tr(static::ALERT_TOKEN_NOT_FOUND));
        }

        if (!$userId = \JWTAuth::parseToken()->authenticate()->id) {
            throw new Exception(static::tr(static::ALERT_USER_NOT_FOUND));
        }

        /** @var User $user */
        $user = User::active()->find($userId);

        if (!$user) {
            throw new Exception(static::tr(static::ALERT_USER_NOT_FOUND));
        }

        $this->user = $user;
        return $this->user;
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
        $sort = get('sort', []);
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

        if ($this->methodExists('extendFilters')) {
            $this->extendFilters($filters);
        }

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

        $data = $this->filters();
        $arFilters = array_get($data, 'filters');
        $arSort = array_get($data, 'sort');
        $obCollection = $this->collection;

        if (!empty($arFilters)) {
            if ($obCollection->methodExists('filter')) {
                $obCollection = $obCollection->filter($arFilters);
            }
            foreach ($arFilters as $sFilterName => $sFilterValue) {
                if ($sFilterName == 'page') {
                    continue;
                }
                $sMethodName = camel_case($sFilterName);
                if ($obCollection->methodExists($sMethodName)) {
                    $obCollection = call_user_func_array(
                        [$obCollection, $sMethodName],
                        array_wrap($sFilterValue)
                    );
                }
            }
        }

        if ($obCollection->methodExists('sort') && $arSort['column']) {
            $sSort = $arSort['column'];
            if ($sSort != 'no') {
                $sSort .= '|'.$arSort['direction'];
            }
            $obCollection = $obCollection->sort($sSort);
        }

        return $obCollection;
    }

    /**
     * @param string         $sName
     * @param CmsObject|null $cmsObject
     * @param array          $properties
     * @param bool           $isSoftComponent
     *
     * @return \Cms\Classes\ComponentBase
     * @throws \SystemException
     * @throws \Exception
     */
    public function component(
        string $sName,
        $cmsObject = null,
        $properties = [],
        $isSoftComponent = false
    ): \Cms\Classes\ComponentBase {
        if (array_key_exists($sName, static::$components)) {
            return static::$components[$sName];
        }

        $component = ComponentManager::instance()->makeComponent($sName, $cmsObject, $properties, $isSoftComponent);
        if (!$component) {
            throw new Exception('component not found');
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
}
