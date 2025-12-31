<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
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
     * @param TStoreRequest|TUpdateRequest|Request $request
     *
     * @return ResourceCollection<TResource>
     */
    public function index(Request $request): ResourceCollection
    {
        $obQuery = $this->query
            ->applyFilters($request->only(['filter']))
            ->applySorting($request->get('sort', 'name'))
            ->withRelations(['office', 'company_type', 'preview_image']);

        $obCompanies      = $obQuery->paginate($request->get('per_page', 15));
        $sCollectionClass = $this->getCollectionClass();

        return new $sCollectionClass($obCompanies);
    }

    /**
     * @param mixed $iId
     *
     * @return TResource
     */
    public function show(mixed $iId): JsonResource
    {
        $sResourceClass = $this->getResourceClass();
        $obCompany      = $this->query
            ->withRelations(['office', 'users', 'company_type', 'config', 'contract'])
            ->findOrFail($iId);

        return new $sResourceClass($obCompany);
    }

    /**
     * @param TStoreRequest $request
     * @param TCreateAction $action
     *
     * @return JsonResponse
     */
    public function store(Request $request, object $action): JsonResponse
    {
        $sResourceClass = $this->getResourceClass();
        $sDtoClass      = $this->getDtoClass();
        $data           = $sDtoClass::fromRequest($request->validated());
        $obCompany      = $action->execute($data);

        $this->attachFiles($request, $obCompany);

        return $this->success('record.created', new $sResourceClass($obCompany), 201);
    }

    /**
     * @param TUpdateRequest $request
     * @param int            $iId
     * @param TUpdateAction  $action
     *
     * @return JsonResponse
     */
    public function update(Request $request, mixed $iId, object $action): JsonResponse
    {
        $sResourceClass = $this->getResourceClass();
        $sDtoClass      = $this->getDtoClass();
        $obModel        = $this->query->findOrFail($iId);
        $data           = $sDtoClass::fromRequest($request->validated());
        $obModel        = $action->execute($obModel, $data);

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
}
