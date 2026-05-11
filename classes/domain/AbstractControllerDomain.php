<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use PlanetaDelEste\Alvis\Classes\Helper\AlvisHelper;
use PlanetaDelEste\ApiToolbox\Classes\Api\ApiException;
use PlanetaDelEste\ApiToolbox\Classes\Helper\ApiHelper;
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
     * @return ResourceCollection<TResource>|JsonResponse
     */
    public function index(Request $request): ResourceCollection|JsonResponse
    {
        try {
            // Intentar obtener respuesta cacheada
            $sCacheKey   = $this->getCacheKey($request);
            $arCacheTags = $this->getCacheTags();
            $iTtl        = $this->getTtl();

            $sCachedJson = Cache::tags($arCacheTags)->get($sCacheKey);

            if (null !== $sCachedJson) {
                return response()->json(
                    json_decode($sCachedJson, true),
                    200,
                    ['X-Cache' => 'HIT']
                );
            }

            // Sin caché - ejecutar query normal
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
            $obResource       = new $sCollectionClass($obCollection);

            // Cachear la respuesta JSON (string, sin closures)
            $obResponse   = $obResource->response();
            $arData       = $obResponse->getData(true);
            $sJsonToCache = json_encode($arData);

            Cache::tags($arCacheTags)->put($sCacheKey, $sJsonToCache, $iTtl);

            return $obResponse->header('X-Cache', 'MISS');
        } catch (\Throwable $e) {
            return ApiException::exception($e);
        }
    }

      /**
       * @param mixed $iId
       *
       * @return TResource|JsonResponse
       */
    public function show(mixed $iId): JsonResource|JsonResponse
    {
        try {
            // Intentar obtener respuesta cacheada
            $sCacheKey   = $this->getCacheKey(request()).'.show.'.$iId;
            $arCacheTags = $this->getCacheTags();
            $iTtl        = $this->getTtl();

            $sCachedJson = Cache::tags($arCacheTags)->get($sCacheKey);

            if (null !== $sCachedJson) {
                return response()->json(
                    json_decode($sCachedJson, true),
                    200,
                    ['X-Cache' => 'HIT']
                );
            }

            // Sin caché - ejecutar query normal
            $sResourceClass = $this->getResourceClass();
            $obQuery        = $this->query;

            if ($arRelations = array_get($this->arRelations, 'show')) {
                $obQuery->withRelations($arRelations);
            }

            $obModel    = $obQuery->findOrFail($iId);
            $obResource = new $sResourceClass($obModel);

            // Cachear la respuesta JSON (string, sin closures)
            $obResponse   = $obResource->response();
            $arData       = $obResponse->getData(true);
            $sJsonToCache = json_encode($arData);

            Cache::tags($arCacheTags)->put($sCacheKey, $sJsonToCache, $iTtl);

            return $obResponse->header('X-Cache', 'MISS');
        } catch (\Throwable $e) {
            return ApiException::exception($e);
        }
    }

    /**
     * @param TStoreRequest $request
     *
     * @return JsonResponse
     */
    public function store(FormRequest $request): JsonResponse
    {
        try {
            $sResourceClass = $this->getResourceClass();
            $sDtoClass      = $this->getDtoClass();
            $sActionClass   = $this->getCreateActionClass();

            // Filtrar campos excluidos
            $arData = AlvisHelper::arrayFilterRecursive($request->all());
            $arData = array_diff_key(
                $arData,
                array_flip(array_filter($this->getExcludedFields(), 'is_scalar'))
            );

            // Limpiar objetos anidados/relaciones que puedan causar recursión
            // $arData = $this->sanitizeDataForDto($arData);

            $data    = $sDtoClass::fromArray($arData);
            $action  = app($sActionClass);
            $obModel = $action->execute($data);

            $this->attachFiles($request, $obModel);
            $this->flushCache();

            return $this->success('record.created', new $sResourceClass($obModel), 201);
        } catch (\Throwable $e) {
            return ApiException::exception($e);
        }
    }

    /**
     * @param TUpdateRequest $request
     * @param mixed          $iId
     *
     * @return JsonResponse
     */
    public function update(FormRequest $request, mixed $iId): JsonResponse
    {
        try {
            $sResourceClass = $this->getResourceClass();
            $sDtoClass      = $this->getDtoClass();
            $sActionClass   = $this->getUpdateActionClass();

            $obModel = $this->query->findOrFail($iId);

            // Filtrar campos excluidos
            $arData = AlvisHelper::arrayFilterRecursive($request->all());
            $arData = array_diff_key(
                $arData,
                array_flip(array_filter($this->getExcludedFields(), 'is_scalar'))
            );

            // Limpiar objetos anidados/relaciones que puedan causar recursión
            // $arData = $this->sanitizeDataForDto($arData);

            $data    = $sDtoClass::fromArray($arData);
            $action  = app($sActionClass);
            $obModel = $action->execute($obModel, $data);

            $this->attachFiles($request, $obModel);
            $this->flushCache();

            return $this->success('record.updated', new $sResourceClass($obModel));
        } catch (\Throwable $e) {
            return ApiException::exception($e);
        }
    }

    /**
     * @param mixed $iId
     *
     * @return JsonResponse
     */
    public function destroy(mixed $iId): JsonResponse
    {
        try {
            $obModel = $this->query->findOrFail($iId);

            $this->service->delete($obModel);
            $this->flushCache();

            return $this->message('record.deleted');
        } catch (\Throwable $th) {
            return ApiException::exception($th);
        }
    }

    /**
     * Elimina un archivo del modelo
     *
     * @param mixed  $iModelId
     * @param int    $iFileId
     * @param string $sAttribute
     *
     * @return JsonResponse
     */
    public function detachFile(mixed $iModelId, int $iFileId, string $sAttribute): JsonResponse
    {
        try {
            $obModel = $this->query->findOrFail($iModelId);

            // Validar que el atributo existe en $arFileList
            $bIsValidAttribute = in_array($sAttribute, $this->getFileAttributeNames(), true);

            if (!$bIsValidAttribute) {
                return $this->message('file.invalid_attribute', 400);
            }

            // Buscar y eliminar el archivo
            $obFile = $obModel->{$sAttribute}()->find($iFileId);

            if (!$obFile) {
                return $this->message('file.not_found', 404);
            }

            $obFile->delete();
            $this->flushCache();

            return $this->message('file.deleted');
        } catch (\Throwable $th) {
            return ApiException::exception($th);
        }
    }

    /**
     * @param Request $request
     * @param mixed   $iId
     *
     * @return JsonResponse
     */
    public function attachFile(Request $request, mixed $iId): JsonResponse
    {
        try {
            $obModel = $this->query->findOrFail($iId);
            $this->attachFiles($request, $obModel);
            $this->flushCache();

            return $this->message('file.attached');
        } catch (\Throwable $th) {
            return ApiException::exception($th);
        }
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
        $sRouteKey = $request->route()->getName();
        $sRouteKey = str_replace(['api.v1', 'api.v2'], 'domain', $sRouteKey);
        $sFilters  = json_encode($request->get('filters', []));
        $sSort     = $request->get('sort', $this->getSortColumn() ?: 'id|asc');
        $sLimit    = $request->get('limit', 15);
        $sPage     = $request->get('page', 1);

        return sprintf(
            '%s.%s.%s.%s.%s.%s',
            $sRouteKey,
            md5($sFilters),
            $sSort,
            $sLimit,
            $sPage,
            $this->companyId()
        );
    }

    public function getCacheTags(): array
    {
        $sDomainName = $this->getDomainName();
        $sCompanyId  = $this->companyId();

        // Tag compuesto: domain:company para flush específico por empresa
        $arTagList = ['domain', $sDomainName];

        if ($sCompanyId) {
            // Tag compuesto asegura que flush solo afecte a esta empresa+dominio
            $arTagList[] = "{$sDomainName}:company:{$sCompanyId}";
        }

        return $arTagList;
    }

    /**
     * Obtiene los tags para invalidar caché (solo dominio+empresa actual)
     *
     * @return array
     */
    public function getFlushCacheTags(): array
    {
        $sDomainName = $this->getDomainName();
        $sCompanyId  = $this->companyId();

        if ($sCompanyId) {
            // Solo invalida items de este dominio + esta empresa
            return ["{$sDomainName}:company:{$sCompanyId}"];
        }

        // Sin empresa, invalida todo el dominio
        return [$sDomainName];
    }

    /**
     * Invalidar caché del dominio actual (solo empresa actual)
     *
     * @return void
     */
    public function flushCache(): void
    {
        Cache::tags($this->getFlushCacheTags())->flush();
    }

    /**
     * Obtiene el ID de la empresa actual
     *
     * @return string|null
     */
    public function companyId(): ?string
    {
        return AlvisHelper::companyID();
    }

    /**
     * Obtiene el ID de la oficina actual
     *
     * @return string|null
     */
    public function officeId(): ?string
    {
        return AlvisHelper::officeID();
    }

    /**
     * Obtiene el TTL para cachear las respuestas (en segundos) 6hs por defecto
     * Puede ser sobrescrito por las clases hijas para definir un TTL específico
     *
     * @return int
     */
    public function getTtl(): int
    {
        return $this->cacheTtl ?? (3600 * 6);
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
     * Adjunta archivos al modelo basándose en el request y la configuración de $arFileList.
     *
     * Cada entrada de $arFileList puede ser un string simple o un array asociativo
     * con el nombre del atributo como clave y un array de opciones como valor:
     *
     * ```php
     * $this->arFileList = [
     *     'attachOne'  => ['preview_image', 'cover_image' => ['convertToWebp' => true]],
     *     'attachMany' => ['images' => ['convertToWebp' => true]],
     * ];
     * ```
     *
     * Opciones disponibles:
     *   - `convertToWebp` (bool): convierte la imagen a WebP antes de adjuntarla (default: false)
     *   - `webpQuality`   (int):  calidad WebP entre 1 y 100 (default: 85)
     *
     * @param Request $request
     * @param TModel  $obModel
     *
     * @return void
     */
    protected function attachFiles(Request $request, $obModel): void
    {
        foreach ($this->arFileList as $sType => $arAttributes) {
            foreach ($arAttributes as $mKey => $mValue) {
                // Soporte para formato simple (string) y formato con opciones (array)
                if (is_int($mKey)) {
                    $sAttribute = $mValue;
                    $arOptions  = [];
                } else {
                    $sAttribute = $mKey;
                    $arOptions  = is_array($mValue) ? $mValue : [];
                }

                if (!$request->hasFile($sAttribute)) {
                    continue;
                }

                match ($sType) {
                    'attachOne'  => $this->attachOneFile($obModel, $sAttribute, $request->file($sAttribute), $arOptions),
                    'attachMany' => $this->attachManyFiles($obModel, $sAttribute, $request->file($sAttribute), $arOptions),
                    default      => null,
                };
            }
        }
    }

    /**
     * Adjunta un archivo único al modelo, con conversión WebP opcional.
     *
     * @param TModel                        $obModel
     * @param string                        $sAttribute
     * @param \Illuminate\Http\UploadedFile $obFile
     * @param array                         $arOptions  Opciones: convertToWebp, webpQuality
     *
     * @return void
     */
    protected function attachOneFile($obModel, string $sAttribute, \Illuminate\Http\UploadedFile $obFile, array $arOptions = []): void
    {
        // Si ya existe un archivo, lo eliminamos
        if ($obModel->{$sAttribute}) {
            $obModel->{$sAttribute}->delete();
        }

        $obFile = $this->maybeConvertToWebp($obFile, $arOptions);
        $obModel->{$sAttribute}()->create(['data' => $obFile]);
    }

    /**
     * Adjunta múltiples archivos al modelo, con conversión WebP opcional.
     *
     * @param TModel                                          $obModel
     * @param string                                          $sAttribute
     * @param \Illuminate\Http\UploadedFile|array<int, mixed> $arFiles
     * @param array                                           $arOptions  Opciones: convertToWebp, webpQuality
     *
     * @return void
     */
    protected function attachManyFiles($obModel, string $sAttribute, $arFiles, array $arOptions = []): void
    {
        if (!is_array($arFiles)) {
            $arFiles = [$arFiles];
        }

        foreach ($arFiles as $obFile) {
            $obFile = $this->maybeConvertToWebp($obFile, $arOptions);
            $obModel->{$sAttribute}()->create(['data' => $obFile]);
        }
    }

    /**
     * Convierte un UploadedFile a WebP si la opción `convertToWebp` está activa
     * y el archivo es una imagen compatible (JPEG, PNG, GIF, BMP, TIFF).
     * Si el archivo ya es WebP o no es una imagen, lo devuelve sin modificar.
     *
     * @param \Illuminate\Http\UploadedFile $obFile
     * @param array                         $arOptions Opciones: convertToWebp (bool), webpQuality (int 1-100)
     *
     * @return \Illuminate\Http\UploadedFile
     */
    protected function maybeConvertToWebp(\Illuminate\Http\UploadedFile $obFile, array $arOptions = []): \Illuminate\Http\UploadedFile
    {
        if (empty($arOptions['convertToWebp'])) {
            return $obFile;
        }

        $sMime = $obFile->getMimeType();

        // Solo convertir imágenes raster; excluir WebP ya convertido y SVG
        $arConvertibleMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff', 'image/x-bmp'];

        if (!in_array($sMime, $arConvertibleMimes, true)) {
            return $obFile;
        }

        $iQuality  = (int) ($arOptions['webpQuality'] ?? 85);
        $iQuality  = max(1, min(100, $iQuality));
        $sBaseName = pathinfo($obFile->getClientOriginalName(), PATHINFO_FILENAME);
        $sWebpName = $sBaseName.'.webp';
        $sTempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'webp_'.uniqid().'_'.$sWebpName;

        $obManager = new ImageManager(new GdDriver());
        $obManager->read($obFile->getRealPath())->toWebp($iQuality)->save($sTempPath);

        return new \Illuminate\Http\UploadedFile(
            $sTempPath,
            $sWebpName,
            'image/webp',
            null,
            true
            // test mode: no valida si el archivo fue subido via HTTP
        );
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

        if (ApiHelper::isTranslatable($sMessage)) {
            $sMessage = ApiHelper::tr($sMessage);
        }

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
     * @param string $sMessage
     * @param int    $status
     *
     * @return JsonResponse
     */
    protected function message(string $sMessage, int $status = 200): JsonResponse
    {
        if (ApiHelper::isTranslatable($sMessage)) {
            $sMessage = ApiHelper::tr($sMessage);
        }

        return response()->json(['message' => $sMessage], $status);
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
        // Soporta tanto formato simple (string) como formato con opciones (array)
        foreach ($this->arFileList as $arAttributes) {
            foreach ($arAttributes as $mKey => $mValue) {
                $arExcluded[] = is_int($mKey) ? $mValue : $mKey;
            }
        }

        return $arExcluded;
    }

    protected function getDomainName(): string
    {
        $arParts = explode('\\', static::class);

        return strtolower($arParts[4]);
    }

    /**
     * Retorna la lista plana de nombres de atributos de archivo definidos en $arFileList,
     * soportando tanto el formato simple (string) como el formato con opciones (array).
     *
     * @return array<string>
     */
    protected function getFileAttributeNames(): array
    {
        $arNames = [];

        foreach ($this->arFileList as $arAttributes) {
            foreach ($arAttributes as $mKey => $mValue) {
                $arNames[] = is_int($mKey) ? $mValue : $mKey;
            }
        }

        return $arNames;
    }

    /**
     * Sanitiza el array de datos para evitar recursión al crear DTOs
     * Detecta y elimina objetos Model/Collection pero preserva arrays de datos planos del request
     *
     * @param array $arData
     *
     * @return array
     */
    protected function sanitizeDataForDto(array $arData): array
    {
        foreach ($arData as $sKey => $value) {
            // Preservar null y valores primitivos
            if (is_null($value) || is_scalar($value)) {
                continue;
            }

            // Preservar UploadedFile
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            // Si es un objeto PHP (Model, Collection, DTO, etc), convertir a null
            if (is_object($value)) {
                $arData[$sKey] = null;

                continue;
            }

            // Si es un array, verificar su contenido
            if (!is_array($value)) {
                continue;
            }

            // Array vacío - preservar
            if (empty($value)) {
                continue;
            }

            // Verificar si contiene objetos PHP (no arrays asociativos)
            $bHasModelObjects = false;

            foreach ($value as $item) {
                // Si encontramos un objeto que NO es un array asociativo
                if (is_object($item) && !($item instanceof \Illuminate\Http\UploadedFile)) {
                    $bHasModelObjects = true;

                    break;
                }
            }

            if ($bHasModelObjects) {
                // Es una colección de modelos eager-loaded - eliminar
                $arData[$sKey] = null;

                continue;
            }

            // Es un array de datos planos (del request JSON) - sanitizar recursivamente
            $arData[$sKey] = $this->isAssociativeArray($value)
                ? $this->sanitizeDataForDto($value)
// Array asociativo único
                : array_map(fn($item) => is_array($item) ? $this->sanitizeDataForDto($item) : $item, $value);
// Array de arrays
        }

        return $arData;
    }

    /**
     * Verifica si un array es asociativo (tiene claves string) vs numérico secuencial
     *
     * @param array $array
     *
     * @return bool
     */
    protected function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
