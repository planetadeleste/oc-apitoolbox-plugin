<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use PlanetaDelEste\Alvis\Classes\Helper\AlvisHelper;
use TService;

/**
 * Class AbstractControllerDomain
 *
 * @template TModel of \Model
 * @template TService of \PlanetaDelEste\ApiToolbox\Contracts\ServiceDomainInterface<TModel>
 * @template TResource of \Illuminate\Http\Resources\Json\JsonResource
 * @template TDto of \stdClass
 * @template TStoreRequest of \Illuminate\Http\Request
 * @template TUpdateRequest of \Illuminate\Http\Request
 * @template TCreateAction of object
 * @template TUpdateAction of object
 *
 * @property TService $service
 * @property \PlanetaDelEste\ApiToolbox\Contracts\QueryDomainInterface<TModel> $query
 * @property int $cacheTtl
 */
abstract class AbstractControllerDomain extends Controller
{
    /**
     * @var array
     */
    protected array $arFileList = [
        'attachOne'  => ['preview_image', 'avatar'],
        'attachMany' => ['images'],
    ];

    /**
     * @var array
     */
    protected array $arRelations = [];

    /**
     * @param TStoreRequest|TUpdateRequest|Request $request
     *
     * @return ResourceCollection<TResource>
     */
    public function index(Request $request): ResourceCollection
    {
        [$sSort, $sDir] = str_contains($this->getSortColumn() ?: 'id|asc', '|')
            ? explode('|', $this->getSortColumn())
            : [$this->getSortColumn(), 'asc'];
        $obQuery        = $this->query
            ->applyFilters($request->get('filters', []))
            ->applySorting($request->get('sort', $sSort), $sDir);

        if ($arRelations = array_get($this->arRelations, 'index')) {
            $obQuery->withRelations($arRelations);
        }

        $obCollection     = $obQuery->paginate($request->get('limit', 15));
        $sCollectionClass = $this->getCollectionClass();

        return new $sCollectionClass($obCollection);
    }

      /**
       * @param mixed $iId
       *
       * @return TResource
       */
    public function show(mixed $iId): JsonResource
    {
        $sResourceClass = $this->getResourceClass();
        $obQuery        = $this->query;

        if ($arRelations = array_get($this->arRelations, 'show')) {
            $obQuery->withRelations($arRelations);
        }

        $obModel = $obQuery->findOrFail($iId);

        return new $sResourceClass($obModel);
    }

    /**
     * @param TStoreRequest $request
     *
     * @return JsonResponse
     */
    public function store(FormRequest $request): JsonResponse
    {
        $sResourceClass = $this->getResourceClass();
        $sDtoClass      = $this->getDtoClass();
        $sActionClass   = $this->getCreateActionClass();

        // Filtrar campos excluidos
        $arData = array_diff_key(
            $request->all(),
            array_flip($this->getExcludedFields())
        );

        $data    = $sDtoClass::fromArray($arData);
        $action  = app($sActionClass);
        $obModel = $action->execute($data);

        $this->attachFiles($request, $obModel);

        return $this->success('record.created', new $sResourceClass($obModel), 201);
    }

    /**
     * @param TUpdateRequest $request
     * @param mixed          $iId
     *
     * @return JsonResponse
     */
    public function update(FormRequest $request, mixed $iId): JsonResponse
    {
        $sResourceClass = $this->getResourceClass();
        $sDtoClass      = $this->getDtoClass();
        $sActionClass   = $this->getUpdateActionClass();

        $obModel = $this->query->findOrFail($iId);

        // Filtrar campos excluidos
        $arData = array_diff_key(
            $request->all(),
            array_flip($this->getExcludedFields())
        );

        $data    = $sDtoClass::fromArray($arData);
        $action  = app($sActionClass);
        $obModel = $action->execute($obModel, $data);

        $this->attachFiles($request, $obModel);

        return $this->success('record.updated', new $sResourceClass($obModel));
    }

    /**
     * @param mixed $iId
     *
     * @return JsonResponse
     */
    public function destroy(mixed $iId): JsonResponse
    {
        $obCompany = $this->query->findOrFail($iId);

        $this->service->delete($obCompany);

        return $this->message('record.deleted');
    }

    /**
     * Elimina un archivo del modelo
     *
     * @param int    $iModelId
     * @param int    $iFileId
     * @param string $sAttribute
     *
     * @return JsonResponse
     */
    public function detachFile(int $iModelId, int $iFileId, string $sAttribute): JsonResponse
    {
        $obModel = $this->query->findOrFail($iModelId);

        // Validar que el atributo existe en $arFileList
        $bIsValidAttribute = collect($this->arFileList)
            ->flatten()
            ->contains($sAttribute);

        if (!$bIsValidAttribute) {
            return $this->message('file.invalid_attribute', 400);
        }

        // Buscar y eliminar el archivo
        $obFile = $obModel->{$sAttribute}()->find($iFileId);

        if (!$obFile) {
            return $this->message('file.not_found', 404);
        }

        $obFile->delete();

        return $this->message('file.deleted');
    }

    /**
     * @param Request $request
     * @param mixed   $iId
     *
     * @return JsonResponse
     */
    public function attachFile(Request $request, mixed $iId): JsonResponse
    {
        $obModel = $this->query->findOrFail($iId);
        $this->attachFiles($request, $obModel);

        return $this->message('file.attached');
    }

    /**
     * Generate a cache key based on the request
     *
     * @param Request $request
     *
     * @return string
     */
    public function getCacheKey(Request $request): string
    {
        $sRouteKey  = $request->route()->getName();
        $sRouteKey  = str_replace(['api.v1', 'api.v2'], 'domain', $sRouteKey);
        $sFilters   = json_encode($request->get('filters', []));
        $sSort      = $request->get('sort', $this->getSortColumn() ?: 'id|asc');
        $sLimit     = $request->get('limit', 15);
        $iCompanyId = AlvisHelper::companyID();

        return sprintf(
            '%s.%s.%s.%s.%s',
            $sRouteKey,
            md5($sFilters),
            $sSort,
            $sLimit,
            $iCompanyId
        );
    }

    public function getCacheTags(): array
    {
        return ['domain', $this->getDomainName()];
    }

    /**
     * @return class-string<TResource>
     */
    abstract public function getResourceClass(): string;

    /**
     * @return class-string<TDto>
     */
    abstract public function getDtoClass(): string;

    /**
     * @return class-string<ResourceCollection<TModel>>
     */
    abstract public function getCollectionClass(): string;

    /**
     * @return class-string<TCreateAction>
     */
    abstract public function getCreateActionClass(): string;

    /**
     * @return class-string<TUpdateAction>
     */
    abstract public function getUpdateActionClass(): string;

    /**
     * @return string
     */
    abstract public function getSortColumn(): string;

    /**
     * Adjunta archivos al modelo basándose en el request y la configuración de $arFileList
     *
     * @param Request $request
     * @param TModel  $obModel
     *
     * @return void
     */
    protected function attachFiles(Request $request, $obModel): void
    {
        foreach ($this->arFileList as $sType => $arAttributes) {
            foreach ($arAttributes as $sAttribute) {
                if (!$request->hasFile($sAttribute)) {
                    continue;
                }

                match ($sType) {
                    'attachOne'  => $this->attachOneFile($obModel, $sAttribute, $request->file($sAttribute)),
                    'attachMany' => $this->attachManyFiles($obModel, $sAttribute, $request->file($sAttribute)),
                    default      => null,
                };
            }
        }
    }

    /**
     * Adjunta un archivo único al modelo
     *
     * @param TModel                        $obModel
     * @param string                        $sAttribute
     * @param \Illuminate\Http\UploadedFile $obFile
     *
     * @return void
     */
    protected function attachOneFile($obModel, string $sAttribute, \Illuminate\Http\UploadedFile $obFile): void
    {
        // Si ya existe un archivo, lo eliminamos
        if ($obModel->{$sAttribute}) {
            $obModel->{$sAttribute}->delete();
        }

        $obModel->{$sAttribute}()->create(['data' => $obFile]);
    }

    /**
     * Adjunta múltiples archivos al modelo
     *
     * @param TModel                                          $obModel
     * @param string                                          $sAttribute
     * @param \Illuminate\Http\UploadedFile|array<int, mixed> $arFiles
     *
     * @return void
     */
    protected function attachManyFiles($obModel, string $sAttribute, $arFiles): void
    {
        if (!is_array($arFiles)) {
            $arFiles = [$arFiles];
        }

        foreach ($arFiles as $obFile) {
            $obModel->{$sAttribute}()->create(['data' => $obFile]);
        }
    }

    /**
     * Handles an error and sends a JSON response
     *
     * @param Exception $ex
     * @param int       $iStatus
     *
     * @return JsonResponse
     */
    protected function error(Exception $ex, int $iStatus = 403): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $ex->getMessage(),
            'code'    => $ex->getCode() ?: $iStatus,
        ], $ex->getCode() ?: $iStatus);
    }

    /**
     * Sends a JSON success response
     *
     * @param mixed $sMessage
     * @param mixed $arData
     * @param int   $iStatus
     *
     * @return JsonResponse
     */
    protected function success(?string $sMessage, mixed $arData = [], int $iStatus = 200): JsonResponse
    {
        $arJsonData = ['success' => true];

        if (null !== $sMessage) {
            $arJsonData['message'] = $sMessage;
        }

        if (!empty($arData)) {
            if ($arData instanceof JsonResource) {
                $arData = $arData->toArray(request());
            }

            $arJsonData['data'] = array_wrap($arData);
        }

        return response()->json($arJsonData, $iStatus);
    }

    /**
     * Sends a JSON message response
     *
     * @param string $sValue
     * @param int    $status
     *
     * @return JsonResponse
     */
    protected function message(string $sValue, int $status = 200): JsonResponse
    {
        return response()->json(['message' => tr($sValue)], $status);
    }

    /**
     * Retorna los campos que deben excluirse al crear/actualizar
     * Por defecto excluye campos de fecha y archivos (manejados por attachFiles)
     * Puede ser sobrescrito por las clases hijas para agregar más campos
     *
     * @return array
     */
    protected function getExcludedFields(): array
    {
        // Excluir campos de fecha automáticos
        $arExcluded = [
            'created_at',
            'updated_at',
            'deleted_at',
            'subscribed_at',
            'subscription_expires_at',
        ];

        // Agregar archivos de $arFileList (manejados por attachFiles)
        foreach ($this->arFileList as $arAttributes) {
            $arExcluded = array_merge($arExcluded, $arAttributes);
        }

        return $arExcluded;
    }

    protected function getDomainName(): string
    {
        $arParts = explode('\\', static::class);

        return strtolower($arParts[4]);
    }
}
