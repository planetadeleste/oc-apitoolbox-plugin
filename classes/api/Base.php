<?php namespace PlanetaDelEste\ApiToolbox\Classes\Api;

use Cms\Classes\CmsObject;
use Cms\Classes\ComponentManager;
use Event;
use Exception;
use JWTAuth;
use Kharanenka\Helper\Result;
use Lovata\Buddies\Models\User;
use PlanetaDelEste\ApiToolbox\Plugin;
use PlanetaDelEste\ApiToolbox\Traits\Controllers\ApiBaseTrait;
use Tymon\JWTAuth\Exceptions\JWTException;

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
class Base
{
    use ApiBaseTrait;

    const ALERT_TOKEN_NOT_FOUND = 'token_not_found';
    const ALERT_USER_NOT_FOUND = 'user_not_found';
    const ALERT_ACCESS_DENIED = 'access_denied';
    const ALERT_PERMISSIONS_DENIED = 'insufficient_permissions';
    const ALERT_RECORD_NOT_FOUND = 'record_not_found';
    const ALERT_RECORDS_NOT_FOUND = 'records_not_found';
    const ALERT_RECORD_UPDATED = 'record_updated';
    const ALERT_RECORDS_UPDATED = 'records_updated';
    const ALERT_RECORD_CREATED = 'record_created';
    const ALERT_RECORD_DELETED = 'record_deleted';
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

    public function __construct()
    {
        $this->data = input();
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
            if (method_exists($this, 'extendIndex')) {
                $this->extendIndex();
            }

            /**
             * Extend collection results
             */
            Event::fire(Plugin::EVENT_API_EXTEND_INDEX, [$this, &$this->collection], true);

            $obModelCollection = $this->collection->paginate($this->itemsPerPage);
            return $this->getIndexResource()
                ? app($this->getIndexResource(), [$obModelCollection])
                : $obModelCollection;
        } catch (Exception $e) {
            trace_log($e);
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function list()
    {
        try {
            if (method_exists($this, 'extendList')) {
                $this->extendList();
            }

            /**
             * Extend collection results
             */
            Event::fire(Plugin::EVENT_API_EXTEND_LIST, [$this, &$this->collection], true);

            $arListItems = $this->collection->values();
            return $this->getListResource()
                ? app($this->getListResource(), [collect($arListItems)])
                : $arListItems;
        } catch (Exception $e) {
            trace_log($e);
            return response()->json(['error' => $e->getMessage()], 403);
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
            $iModelId = app($this->getModelClass())->where($this->primaryKey, $value)->value('id');

            if (!$iModelId) {
                throw new Exception(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            $sItemClass = $this->collection::ITEM_CLASS;
            $this->item = $sItemClass::make($iModelId);

            if (method_exists($this, 'extendShow')) {
                $this->extendShow();
            }

            /**
             * Extend collection results
             */
            Event::fire(Plugin::EVENT_API_EXTEND_SHOW, [$this, $this->item], true);

            return $this->getShowResource()
                ? app($this->getShowResource(), [$this->item])
                : $this->item;
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function store()
    {
        try {
            $this->currentUser();

            $model = app($this->getModelClass());
            $this->exists = false;
            $success = false;
            $message = static::tr(static::ALERT_RECORD_NOT_CREATED);

            if (!$this->hasPermission($model, 'store')) {
                throw new JWTException(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            if ($this->save($model, $this->data)) {
                $success = true;
                $message = static::tr(static::ALERT_RECORD_CREATED);
            }

            return Result::setData(compact('success', 'message', 'model'))->getJSON();
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
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

            /** @var \Model $model */
            $model = app($this->getModelClass())->where($this->primaryKey, $id)->firstOrFail();
            $this->exists = true;
            $message = static::tr(static::ALERT_RECORD_NOT_UPDATED);
            Result::setFalse();
            if (!$model) {
                throw new JWTException(static::ALERT_RECORD_NOT_FOUND, 403);
            }

            if (!$this->hasPermission($model, 'update')) {
                throw new JWTException(static::ALERT_PERMISSIONS_DENIED, 403);
            }

            if ($this->save($model, $this->data)) {
                Result::setTrue();
                $message = static::tr(static::ALERT_RECORD_UPDATED);
            }

            return Result::setData($model)
                ->setMessage($message)
                ->getJSON();
        } catch (Exception $e) {
            Result::setFalse()->setMessage($e->getMessage());
            return response()->json(Result::get(), 403);
        }
    }

    /**
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            throw new Exception('Comming soon');
//            if (!$this->hasPermission($model, 'destroy')) {
//                throw new JWTException(static::ALERT_PERMISSIONS_DENIED, 403);
//            }
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @param \Model $model
     * @param array  $data
     *
     * @return mixed
     */
    protected function save($model, $data)
    {
        $model->fill($data);
        return $model->save();
    }

    /**
     * @param \Model $obModel
     * @param string $action
     *
     * @return bool
     */
    protected function hasPermission($obModel, $action)
    {
        return true;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function check()
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
            Result::setFalse()->setMessage($e->getMessage());
            return response()->json(Result::get(), 403);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function csrfToken()
    {
        Result::setData(['token' => csrf_token()]);

        return response()->json(Result::get());
    }

    /**
     * @throws \Tymon\JWTAuth\Exceptions\JWTException
     * @throws \Exception
     */
    protected function currentUser()
    {
        if ($this->user) {
            return $this->user;
        }

        if (!JWTAuth::getToken()) {
            throw new JWTException(static::tr(static::ALERT_TOKEN_NOT_FOUND));
        }

        if (!$userId = JWTAuth::parseToken()->authenticate()->id) {
            throw new JWTException(static::tr(static::ALERT_USER_NOT_FOUND));
        }

        /** @var User $user */
        $user = User::active()->find($userId);

        if (!$user) {
            throw new JWTException(static::tr(static::ALERT_USER_NOT_FOUND));
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
    protected function filters()
    {
        $sortDefault = [
            'column'    => $this->sortColumn,
            'direction' => $this->sortDirection
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

        if (method_exists($this, 'extendFilters')) {
            $this->extendFilters($filters);
        }

        return compact('sort', 'filters');
    }

    /**
     * @return \Lovata\Toolbox\Classes\Collection\ElementCollection|mixed|null
     */
    protected function applyFilters()
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

        if ($this->collection->methodExists('sort') && $arSort['column'] !== 'no') {
            $obCollection = $obCollection->sort($arSort['column'].'|'.$arSort['direction']);
        }

        return $obCollection;
    }

    /**
     * @param string    $sName
     *
     * @param CmsObject $cmsObject
     * @param array     $properties
     * @param bool      $isSoftComponent
     *
     * @return \Cms\Classes\ComponentBase
     * @throws \SystemException
     * @throws \Exception
     */
    protected function component($sName, $cmsObject = null, $properties = [], $isSoftComponent = false)
    {
        if (array_key_exists($sName, static::$components)) {
            return static::$components[$sName];
        }

        $component = ComponentManager::instance()->makeComponent($sName, $cmsObject, $properties, $isSoftComponent);
        if (!$component) {
            throw new \Exception('component not found');
        }

        static::$components[$sName] = $component;
        return $component;
    }
}
