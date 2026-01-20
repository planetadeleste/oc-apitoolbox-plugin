<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Comando para generar estructura completa de dominio
 *
 * php artisan apitoolbox:make-domain Model --plugin=Author.Plugin
 */
class MakeDomainCommand extends Command
{
    protected $signature = 'toolbox:create.domain
    {model : Nombre del modelo (sin namespace)}
    {--plugin= : Nombre del plugin donde se generará el dominio (Author.Plugin)}
    {--model-plugin= : Nombre del plugin del modelo si es diferente (Author.Plugin)}
    {--domain-name= : Nombre personalizado para el dominio (por defecto usa el nombre del modelo)}
    {--force : Sobrescribir archivos existentes}
    {--all : Generar todos los componentes}
    {--dto : Generar DTOs}
    {--actions : Generar acciones}
    {--services : Generar servicios}
    {--queries : Generar consultas}
    {--controller : Generar controlador}
    {--requests : Generar solicitudes}
    {--resources : Generar recursos}
    {--routes : Generar rutas}';

    /**
     * @var string Descripción del comando
     */
    protected $description = 'Genera estructura completa de dominio (DDD) para un modelo';

    /**
     * @var string Ruta del plugin
     */
    protected string $pluginPath;

    /**
     * @var string Namespace del plugin
     */
    protected string $namespace;

    /**
     * @var string Clase del modelo
     */
    protected string $modelClass;

    /**
     * @var string Nombre del modelo
     */
    protected string $modelName;

    /**
     * @var string Namespace del modelo
     */
    protected string $modelNamespace;

    /**
     * @var string Nombre del dominio
     */
    protected string $domainName;

    /**
     * @var string Ruta del dominio generado
     */
    protected string $domainPath;

    /**
     * @var array Propiedades del modelo detectadas
     */
    protected array $properties = [];

    /**
     * @var array Relaciones del modelo detectadas
     */
    protected array $relations = [];

    /**
     * @var bool Forzar sobrescritura de archivos existentes
     */
    protected bool $force;

    /**
     * @return int
     */
    public function handle(): int
    {
        $this->force = $this->option('force');

        // 1. Validar y preparar datos
        if (!$this->prepareData()) {
            return 1;
        }

        // 2. Analizar modelo
        $this->analyzeModel();

        // 3. Mostrar información
        $this->displayInfo();

        // 4. Confirmar generación
        if (!$this->force && !$this->confirm('¿Desea continuar con la generación?')) {
            $this->info('Cancelado.');

            return 0;
        }

        // 5. Generar archivos
        $this->generateFiles();

        $this->newLine();
        $this->info('✅ Dominio generado exitosamente!');
        $this->info("📁 Ubicación: {$this->domainPath}");

        return 0;
    }

    /**
     * @return bool
     */
    protected function prepareData(): bool
    {
        $sModel       = $this->argument('model');
        $sPlugin      = $this->option('plugin');
        $sModelPlugin = $this->option('model-plugin');

        if (!$sPlugin) {
            $this->error('Debe especificar el plugin de destino con --plugin=Author.Plugin');

            return false;
        }

        // Parsear plugin de destino
        [$sAuthor, $sPluginName] = explode('.', $sPlugin);
        $this->pluginPath        = plugins_path(strtolower($sAuthor).'/'.strtolower($sPluginName));

        if (!File::isDirectory($this->pluginPath)) {
            $this->error("El plugin {$sPlugin} no existe en {$this->pluginPath}");

            return false;
        }

        $this->namespace = str_replace('/', '\\', $sAuthor.'\\'.$sPluginName);

        // Preparar clase del modelo (puede estar en otro plugin)
        $this->modelName = Str::studly($sModel);

        if ($sModelPlugin) {
            if (!str_contains($sModelPlugin, '.')) {
                $sModelPluginName = $sModelPlugin;
                $sModelPluginPath = base_path('modules/'.strtolower($sModelPluginName));
            } else {
              // El modelo está en otro plugin
                [$sModelAuthor, $sModelPluginName] = explode('.', $sModelPlugin);
                $sModelPluginPath                  = plugins_path(strtolower($sModelAuthor).'/'.strtolower($sModelPluginName));
            }

            if (!File::isDirectory($sModelPluginPath)) {
                $this->error("El plugin del modelo {$sModelPlugin} no existe en {$sModelPluginPath}");

                return false;
            }

            $this->modelNamespace = isset($sModelAuthor) ? str_replace('/', '\\', $sModelAuthor.'\\'.$sModelPluginName) : str_replace('/', '\\', $sModelPlugin);
            $this->modelClass     = $this->modelNamespace.'\\Models\\'.$this->modelName;
        } else {
            // El modelo está en el mismo plugin
            $this->modelNamespace = $this->namespace;
            $this->modelClass     = $this->namespace.'\\Models\\'.$this->modelName;
        }

        if (!class_exists($this->modelClass)) {
            $this->error("El modelo {$this->modelClass} no existe.");

            return false;
        }

        // Preparar path del dominio
        $sDomainName      = $this->option('domain-name') ?: Str::lower($this->modelName);
        $this->domainName = $sDomainName;
        $this->domainPath = strtolower($this->pluginPath.'/app/domain/'.$sDomainName);

        return true;
    }

    /**
     * @return void
     */
    protected function analyzeModel(): void
    {
        $this->info('🔍 Analizando modelo '.$this->modelClass.'...');

        $obModel      = new $this->modelClass();
        $obReflection = new ReflectionClass($obModel);

        // Obtener tabla del modelo
        $sTable  = $obModel->getTable();
        $arCasts = $obModel->getCasts();

        // Obtener columnas desde la base de datos
        $obSchema  = \Schema::connection($obModel->getConnectionName());
        $arColumns = $obSchema->getColumnListing($sTable);

        foreach ($arColumns as $sColumn) {
            // Obtener tipo de columna desde DB
            $sDbType = $obSchema->getColumnType($sTable, $sColumn);

            $this->properties[$sColumn] = [
                'type'     => $this->inferTypeFromDb($sColumn, $sDbType, $arCasts),
                'nullable' => true,
                'cast'     => $arCasts[$sColumn] ?? null,
                'db_type'  => $sDbType,
            ];
        }

        // Detectar relaciones
        $this->detectRelations($obReflection);

        $this->info('   ✓ Propiedades: '.count($this->properties));
        $this->info('   ✓ Relaciones: '.count($this->relations));
    }

    /**
     * @param ReflectionClass $obReflection
     *
     * @return void
     */
    protected function detectRelations(ReflectionClass $obReflection): void
    {
        $obModel = new $this->modelClass();

        // October CMS define las relaciones en propiedades de clase
        $arRelationTypes = [
            'hasOne',
            'hasMany',
            'belongsTo',
            'belongsToMany',
            'morphOne',
            'morphMany',
            'morphToMany',
            'morphTo',
            'attachOne',
            'attachMany',
            'hasManyThrough',
        ];

        foreach ($arRelationTypes as $sRelationType) {
            if (!property_exists($obModel, $sRelationType)) {
                continue;
            }

            $arRelations = $obModel->{$sRelationType};

            if (empty($arRelations)) {
                continue;
            }

            foreach ($arRelations as $sRelationName => $arConfig) {
                $this->relations[$sRelationName] = $sRelationType;
            }
        }
    }

    /**
     * @param ReflectionMethod $obMethod
     *
     * @return string
     */
    protected function getMethodContent(ReflectionMethod $obMethod): string
    {
        $sFileName  = $obMethod->getFileName();
        $iStartLine = $obMethod->getStartLine();
        $iEndLine   = $obMethod->getEndLine();

        $arLines = file($sFileName);

        return implode('', array_slice($arLines, $iStartLine - 1, $iEndLine - $iStartLine + 1));
    }

    /**
     * @param string $sField
     * @param string $sDbType
     * @param array  $arCasts
     *
     * @return string
     */
    protected function inferTypeFromDb(string $sField, string $sDbType, array $arCasts): string
    {
        // Prioridad 1: Si tiene cast definido, usar ese
        if (isset($arCasts[$sField])) {
            return match ($arCasts[$sField]) {
                'int', 'integer' => 'int',
                'bool', 'boolean' => 'bool',
                'float', 'double' => 'float',
                'array', 'json' => 'array',
                'datetime', 'date', 'timestamp' => 'string',
                default => 'string',
            };
        }

        // Prioridad 2: Inferir desde tipo de DB
        return match (true) {
            str_contains($sDbType, 'int') => 'int',
            str_contains($sDbType, 'bool') || str_contains($sDbType, 'tinyint(1)') => 'bool',
            str_contains($sDbType, 'float') || str_contains($sDbType, 'double') || str_contains($sDbType, 'decimal') => 'float',
            str_contains($sDbType, 'json') => 'array',
            str_contains($sDbType, 'date') || str_contains($sDbType, 'time') => 'string',
            str_contains($sDbType, 'text') || str_contains($sDbType, 'char') || str_contains($sDbType, 'varchar') => 'string',
            default => 'string',
        };
    }

    /**
     * @param string $sField
     * @param array  $arCasts
     *
     * @return string
     *
     * @deprecated Use inferTypeFromDb instead
     */
    protected function inferType(string $sField, array $arCasts): string
    {
        if (isset($arCasts[$sField])) {
            return match ($arCasts[$sField]) {
                'int', 'integer' => 'int',
                'bool', 'boolean' => 'bool',
                'float', 'double' => 'float',
                'array', 'json' => 'array',
                default => 'string',
            };
        }

        // Inferir por nombre de campo
        if (str_ends_with($sField, '_id')) {
            return 'int';
        }

        if (str_contains($sField, 'is_') || str_contains($sField, 'has_')) {
            return 'bool';
        }

        if (in_array($sField, ['created_at', 'updated_at', 'deleted_at'])) {
            return 'string';
        }

        return 'string';
    }

    /**
     * @return void
     */
    protected function displayInfo(): void
    {
        $this->newLine();
        $this->info('📋 Información del dominio:');
        $this->table(
            ['Item', 'Valor'],
            [
                ['Modelo', $this->modelName],
                ['Clase del Modelo', $this->modelClass],
                ['Nombre del Dominio', $this->domainName],
                ['Namespace Destino', $this->namespace],
                ['Path', $this->domainPath],
                ['Propiedades', count($this->properties)],
                ['Relaciones', count($this->relations)],
            ]
        );

        if (!empty($this->properties)) {
            $this->newLine();
            $this->info('📝 Propiedades detectadas:');
            $arRows = [];

            foreach ($this->properties as $sName => $arInfo) {
                $arRows[] = [
                    $sName,
                    $arInfo['type'],
                    $arInfo['db_type'] ?? '-',
                    $arInfo['cast'] ?? '-'
                ];
            }

            $this->table(['Campo', 'Tipo PHP', 'Tipo DB', 'Cast'], $arRows);
        }

        if (empty($this->relations)) {
            return;
        }

        $this->newLine();
        $this->info('🔗 Relaciones detectadas:');
        $arRows = [];

        foreach ($this->relations as $sName => $sType) {
            $arRows[] = [$sName, $sType];
        }

        $this->table(['Relación', 'Tipo'], $arRows);
    }

    protected function generateFiles(): void
    {
        $bAll      = $this->option('all');
        $arOptions = [
            'dto',
            'actions',
            'services',
            'queries',
            'controller',
            'requests',
            'resources',
            'routes'
        ];

        if (!$bAll && !array_any($arOptions, fn($option) => $this->option($option))) {
            $bAll = true;
        }

        $this->newLine();
        $this->info('📦 Generando archivos...');

        if ($bAll || $this->option('dto')) {
            $this->generateDto();
        }

        if ($bAll || $this->option('actions')) {
            $this->generateActions();
        }

        if ($bAll || $this->option('services')) {
            $this->generateService();
        }

        if ($bAll || $this->option('queries')) {
            $this->generateQuery();
        }

        if ($bAll || $this->option('controller')) {
            $this->generateController();
        }

        if ($bAll || $this->option('requests')) {
            $this->generateRequests();
        }

        if ($bAll || $this->option('resources')) {
            $this->generateResources();
        }

        if (!$bAll && !$this->option('routes')) {
            return;
        }

        $this->generateRoutes();
    }

    protected function generateDto(): void
    {
        $sPath = $this->domainPath.'/dtos';
        $this->ensureDirectory($sPath);

        $sContent = $this->getDtoTemplate();
        $sFile    = $sPath.'/'.$this->modelName.'Data.php';

        if (!$this->writeFile($sFile, $sContent)) {
            return;
        }

        $this->line('   ✓ DTO: '.$this->modelName.'Data.php');
    }

    protected function generateActions(): void
    {
        $sPath = $this->domainPath.'/actions';
        $this->ensureDirectory($sPath);

        // CreateAction
        $sContent = $this->getCreateActionTemplate();
        $sFile    = $sPath.'/Create'.$this->modelName.'Action.php';

        if ($this->writeFile($sFile, $sContent)) {
            $this->line('   ✓ Action: Create'.$this->modelName.'Action.php');
        }

        // UpdateAction
        $sContent = $this->getUpdateActionTemplate();
        $sFile    = $sPath.'/Update'.$this->modelName.'Action.php';

        if (!$this->writeFile($sFile, $sContent)) {
            return;
        }

        $this->line('   ✓ Action: Update'.$this->modelName.'Action.php');
    }

    protected function generateService(): void
    {
        $sPath = $this->domainPath.'/services';
        $this->ensureDirectory($sPath);

        $sContent = $this->getServiceTemplate();
        $sFile    = $sPath.'/'.$this->modelName.'Service.php';

        if (!$this->writeFile($sFile, $sContent)) {
            return;
        }

        $this->line('   ✓ Service: '.$this->modelName.'Service.php');
    }

    protected function generateQuery(): void
    {
        $sPath = $this->domainPath.'/queries';
        $this->ensureDirectory($sPath);

        $sContent = $this->getQueryTemplate();
        $sFile    = $sPath.'/'.$this->modelName.'Query.php';

        if (!$this->writeFile($sFile, $sContent)) {
            return;
        }

        $this->line('   ✓ Query: '.$this->modelName.'Query.php');
    }

    protected function generateController(): void
    {
        $sPath = $this->domainPath.'/http/controllers';
        $this->ensureDirectory($sPath);

        $sContent = $this->getControllerTemplate();
        $sFile    = $sPath.'/'.$this->modelName.'Controller.php';

        if (!$this->writeFile($sFile, $sContent)) {
            return;
        }

        $this->line('   ✓ Controller: '.$this->modelName.'Controller.php');
    }

    protected function generateRequests(): void
    {
        $sPath = $this->domainPath.'/http/requests';
        $this->ensureDirectory($sPath);

        // StoreRequest
        $sContent = $this->getStoreRequestTemplate();
        $sFile    = $sPath.'/Store'.$this->modelName.'Request.php';

        if ($this->writeFile($sFile, $sContent)) {
            $this->line('   ✓ Request: Store'.$this->modelName.'Request.php');
        }

        // UpdateRequest
        $sContent = $this->getUpdateRequestTemplate();
        $sFile    = $sPath.'/Update'.$this->modelName.'Request.php';

        if (!$this->writeFile($sFile, $sContent)) {
            return;
        }

        $this->line('   ✓ Request: Update'.$this->modelName.'Request.php');
    }

    protected function generateResources(): void
    {
        $sPath = $this->domainPath.'/http/resources';
        $this->ensureDirectory($sPath);

        // Resource
        $sContent = $this->getResourceTemplate();
        $sFile    = $sPath.'/'.$this->modelName.'Resource.php';

        if ($this->writeFile($sFile, $sContent)) {
            $this->line('   ✓ Resource: '.$this->modelName.'Resource.php');
        }

        // Collection
        $sContent = $this->getCollectionTemplate();
        $sFile    = $sPath.'/'.$this->modelName.'Collection.php';

        if (!$this->writeFile($sFile, $sContent)) {
            return;
        }

        $this->line('   ✓ Collection: '.$this->modelName.'Collection.php');
    }

    protected function ensureDirectory(string $sPath): void
    {
        if (File::isDirectory($sPath)) {
            return;
        }

        File::makeDirectory($sPath, 0755, true);
    }

    protected function writeFile(string $sFile, string $sContent): bool
    {
        if (File::exists($sFile) && !$this->force) {
            $this->warn('   ⚠ Ya existe: '.basename($sFile).' (use --force para sobrescribir)');

            return false;
        }

        File::put($sFile, $sContent);

        return true;
    }

    // ========== TEMPLATES ==========

    /**
     * @param string $sStubName
     *
     * @return string
     */
    protected function getStubPath(string $sStubName): string
    {
        return __DIR__.'/../parser/templates/domain/'.$sStubName.'.stub';
    }

    protected function loadStub(string $sStubName): string
    {
        $sPath = $this->getStubPath($sStubName);

        if (!File::exists($sPath)) {
            throw new RuntimeException("Template stub not found: {$sPath}");
        }

        return File::get($sPath);
    }

    protected function getDtoTemplate(): string
    {
        return $this->parse(
            'dto',
            '\\Dtos',
            ['{{properties}}', '{{fromArrayData}}', '{{fromModelData}}', '{{toArrayData}}', '{{mapOutput}}'],
            [
                $this->buildDtoProperties(),
                $this->buildDtoFromRequest(),
                $this->buildDtoFromModel(),
                $this->buildDtoToArray(),
                $this->buildDtoMapOutput(),
            ]
        );
    }

    protected function buildDtoProperties(): string
    {
        $arLines = ['        public readonly ?int $id,'];

        foreach ($this->properties as $sName => $arInfo) {
            // Excluir 'id' ya que se agrega manualmente al inicio
            if ('id' === $sName) {
                continue;
            }

            $sType      = $arInfo['type'];
            $sPhpType   = 'bool' === $sType ? 'bool' : ('int' === $sType ? 'int' : ('array' === $sType ? 'array' : 'string'));
            $sCamelCase = Str::camel($sName);
            $arLines[]  = sprintf('        public readonly ?%s $%s,', $sPhpType, $sCamelCase);
        }

        return implode("\n", $arLines);
    }

    protected function buildDtoFromRequest(): string
    {
        $arLines = [];

        foreach ($this->properties as $sName => $arInfo) {
            $sCamelCase = Str::camel($sName);
            $arLines[]  = sprintf("            '%s' => \$arData['%s'] ?? null,", $sCamelCase, $sName);
        }

        return implode("\n", $arLines);
    }

    protected function buildDtoFromModel(): string
    {
        $arLines = [];

        foreach ($this->properties as $sName => $arInfo) {
            $sCamelCase = Str::camel($sName);
            $arLines[]  = sprintf("            '%s' => \$obModel->%s,", $sCamelCase, $sName);
        }

        return implode("\n", $arLines);
    }

    protected function buildDtoToArray(): string
    {
        $arLines = [];

        foreach ($this->properties as $sName => $arInfo) {
            $sCamelCase = Str::camel($sName);
            $arLines[]  = sprintf("            '%s' => \$this->%s,", $sName, $sCamelCase);
        }

        return implode("\n", $arLines);
    }

    protected function buildDtoMapOutput(): string
    {
        if (empty($this->relations)) {
            return '        return $arData;';
        }

        $arLines   = [];
        $arLines[] = '        // Solo cargar relaciones si están presentes en el modelo';
        $arLines[] = '        $obModel = $this->getModel();';
        $arLines[] = '';
        $arLines[] = '        if (!$obModel) {';
        $arLines[] = '            return $arData;';
        $arLines[] = '        }';
        $arLines[] = '';

        foreach ($this->relations as $sRelationName => $sRelationType) {
            $sCamelCase = Str::camel($sRelationName);
            $arLines[]  = sprintf("        if (\$obModel->relationLoaded('%s') && \$this->%s) {", $sRelationName, $sCamelCase);
            $arLines[]  = sprintf("            \$arData['%s'] = \$this->%s->toArray();", $sCamelCase, $sCamelCase);
            $arLines[]  = '        }';
            $arLines[]  = '';
        }

        $arLines[] = '        return $arData;';

        return implode("\n", $arLines);
    }

    protected function getCreateActionTemplate(): string
    {
        $sDtoNamespace = $this->namespace.'\\App\\Domain\\'.Str::studly($this->domainName).'\\Dtos';

        return $this->parse(
            'create-action',
            '\\Actions',
            ['{{dtoNamespace}}'],
            [$sDtoNamespace]
        );
    }

    protected function getUpdateActionTemplate(): string
    {
        $sDtoNamespace = $this->namespace.'\\App\\Domain\\'.Str::studly($this->domainName).'\\Dtos';

        return $this->parse('update-action', '\\Actions', ['{{dtoNamespace}}'], [$sDtoNamespace]);
    }

    protected function getServiceTemplate(): string
    {
        return $this->parse('service', '\\Services');
    }

    protected function getQueryTemplate(): string
    {
        return $this->parse('query', '\\Queries');
    }

    protected function getControllerTemplate(): string
    {
        return $this->parse(
            'controller',
            '\\Http\\Controllers'
        );
    }

    protected function getStoreRequestTemplate(): string
    {
        return $this->parse(
            'store-request',
            '\\Http\\Requests',
            ['{{rules}}'],
            [$this->buildValidationRules(true)]
        );
    }

    protected function getUpdateRequestTemplate(): string
    {
        return $this->parse(
            'update-request',
            '\\Http\\Requests',
            ['{{rules}}'],
            [$this->buildValidationRules(false)]
        );
    }

    protected function buildValidationRules(bool $bRequired): string
    {
        $arLines = [];

        foreach ($this->properties as $sName => $arInfo) {
            $sRule = $bRequired ? 'required' : 'sometimes';
            $sType = match ($arInfo['type']) {
                'int' => 'integer',
                'bool' => 'boolean',
                'array' => 'array',
                default => 'string',
            };

            $arLines[] = "            '{$sName}' => ['{$sRule}', '{$sType}'],";
        }

        return implode("\n", $arLines);
    }

    protected function getResourceTemplate(): string
    {
        return $this->parse(
            'resource',
            '\\Http\\Resources',
            ['{{toArray}}'],
            [$this->buildResourceToArray()]
        );
    }

    protected function buildResourceToArray(): string
    {
        $arLines = [];

        foreach ($this->properties as $sName => $arInfo) {
            if (in_array($sName, ['created_at', 'updated_at'])) {
                continue;
            }

            $arLines[] = sprintf("            '%s' => \$this->%s,", $sName, $sName);
        }

        return implode("\n", $arLines);
    }

    protected function getCollectionTemplate(): string
    {
        return $this->parse('collection', '\\Http\\Resources');
    }

    protected function generateRoutes(): void
    {
        $sPath = $this->domainPath.'/routes';
        $this->ensureDirectory($sPath);

        $sContent = $this->getRoutesTemplate();
        $sFile    = $sPath.'/'.Str::lower($this->modelName).'.php';

        if (!$this->writeFile($sFile, $sContent)) {
            return;
        }

        $this->line('   ✓ Routes: '.Str::lower($this->modelName).'.php');
    }

    protected function getRoutesTemplate(): string
    {
        $sPrefix = Str::plural(Str::snake($this->modelName));

        return $this->parse(
            'routes',
            '',
            ['{{prefix}}'],
            [$sPrefix]
        );
    }

    /**
     * @param string $sStub
     * @param string $sNamespacePart
     * @param array  $arKeys
     * @param array  $arValues
     *
     * @return string
     */
    protected function parse(string $sStub, string $sNamespacePart = '', array $arKeys = [], array $arValues = []): string
    {
        $sNamespace       = sprintf('%s\\App\\Domain\\%s%s', $this->namespace, Str::studly($this->domainName), $sNamespacePart);
        $sDomainNamespace = $this->namespace.'\\App\\Domain\\'.Str::studly($this->domainName);
        $sModelNamespace  = $this->modelNamespace.'\\Models';
        $sTemplate        = $this->loadStub($sStub);

        return str_replace(
            array_merge(['{{namespace}}', '{{modelName}}', '{{modelNamespace}}', '{{domainNamespace}}'], $arKeys),
            array_merge([$sNamespace, $this->modelName, $sModelNamespace, $sDomainNamespace], $arValues),
            $sTemplate
        );
    }
}
