# Ejemplo de uso de AbstractDtoDomain

## Arquitectura mejorada

La nueva implementación de `AbstractDtoDomain` facilita la creación de DTOs mediante:

1. **Método central de mapeo (`mapOutput`)** - Construye la salida final
2. **Métodos específicos retornan solo datos** - `fromArrayData`, `fromModelData`, `toArrayData`
3. **Unificación de `fromRequest` y `fromArray`** - Ambos procesan arrays

## Ejemplo básico

```php
<?php

namespace App\Domain\User\Dtos;

use Model;
use PlanetaDelEste\ApiToolbox\Classes\Domain\AbstractDtoDomain;

/**
 * @implements DtoDomainInterface<User>
 */
class UserData extends AbstractDtoDomain
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly bool $active,
    ) {
    }

    /**
     * Mapea datos desde array (request)
     */
    protected static function fromArrayData(array $arData): array
    {
        return [
            'id'     => $arData['id'] ?? null,
            'name'   => $arData['name'],
            'email'  => $arData['email'],
            'phone'  => $arData['phone'] ?? null,
            'active' => $arData['active'] ?? true,
        ];
    }

    /**
     * Mapea datos desde modelo
     */
    protected static function fromModelData(Model $obModel): array
    {
        return [
            'id'     => $obModel->id,
            'name'   => $obModel->name,
            'email'  => $obModel->email,
            'phone'  => $obModel->phone,
            'active' => $obModel->active,
        ];
    }

    /**
     * Mapea datos para array de salida (DB)
     */
    protected function toArrayData(): array
    {
        return [
            'name'   => $this->name,
            'email'  => $this->email,
            'phone'  => $this->phone,
            'active' => $this->active,
        ];
    }
}
```

## Ejemplo con transformaciones personalizadas

```php
<?php

namespace App\Domain\Product\Dtos;

use Model;
use PlanetaDelEste\ApiToolbox\Classes\Domain\AbstractDtoDomain;

class ProductData extends AbstractDtoDomain
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?float $price,
        public readonly ?array $metadata,
    ) {
    }

    protected static function fromArrayData(array $arData): array
    {
        return [
            'id'       => $arData['id'] ?? null,
            'name'     => $arData['name'],
            'slug'     => $arData['slug'] ?? str_slug($arData['name']),
            'price'    => isset($arData['price']) ? (float) $arData['price'] : null,
            'metadata' => $arData['metadata'] ?? null,
        ];
    }

    protected static function fromModelData(Model $obModel): array
    {
        return [
            'id'       => $obModel->id,
            'name'     => $obModel->name,
            'slug'     => $obModel->slug,
            'price'    => $obModel->price,
            'metadata' => $obModel->metadata,
        ];
    }

    protected function toArrayData(): array
    {
        return [
            'name'     => $this->name,
            'slug'     => $this->slug,
            'price'    => $this->price,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Sobrescribir mapOutput para transformaciones globales
     */
    protected function mapOutput(array $arData): array
    {
        // Filtrar valores null
        $arData = array_filter($arData, fn($value) => !is_null($value));

        // Convertir metadata a JSON si es array
        if (isset($arData['metadata']) && is_array($arData['metadata'])) {
            $arData['metadata'] = json_encode($arData['metadata']);
        }

        return $arData;
    }
}
```

## Ejemplo con DTOs anidados

```php
<?php

namespace App\Domain\Company\Dtos;

use Model;
use PlanetaDelEste\ApiToolbox\Classes\Domain\AbstractDtoDomain;

class CompanyData extends AbstractDtoDomain
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?AddressData $address,
        public readonly ?ConfigData $config,
    ) {
    }

    protected static function fromArrayData(array $arData): array
    {
        return [
            'id'      => $arData['id'] ?? null,
            'name'    => $arData['name'],
            'slug'    => $arData['slug'] ?? str_slug($arData['name']),
            'address' => isset($arData['address'])
                ? AddressData::fromArray($arData['address'])
                : null,
            'config'  => isset($arData['config'])
                ? ConfigData::fromArray($arData['config'])
                : null,
        ];
    }

    protected static function fromModelData(Model $obModel): array
    {
        return [
            'id'      => $obModel->id,
            'name'    => $obModel->name,
            'slug'    => $obModel->slug,
            'address' => $obModel->address
                ? AddressData::from($obModel->address)
                : null,
            'config'  => $obModel->config
                ? ConfigData::from($obModel->config)
                : null,
        ];
    }

    protected function toArrayData(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            // Los DTOs anidados se manejan en mapOutput
        ];
    }

    protected function mapOutput(array $arData): array
    {
        // Convertir DTOs anidados a arrays
        if ($this->address) {
            $arData['address'] = $this->address->toArray();
        }

        if ($this->config) {
            $arData['config'] = $this->config->toArray();
        }

        return $arData;
    }
}
```

## Uso en controladores

```php
// Crear desde request
$dto = CompanyData::fromArray($request->validated());
// o usar el alias
$dto = CompanyData::fromRequest($request->validated());

// Crear desde modelo
$dto = CompanyData::from($company);

// Convertir a array para persistencia
$data = $dto->toArray();
$company->fill($data)->save();
```

## Ventajas

1. **Menos repetición** - Los métodos solo retornan datos, no construyen arrays completos
2. **Centralización** - `mapOutput()` maneja todas las transformaciones globales
3. **Claridad** - Separación clara entre mapeo de entrada y salida
4. **Flexibilidad** - Fácil sobrescribir `mapOutput()` para casos específicos
5. **Unificación** - Un solo método `fromArray()` para procesar arrays (request, manual, etc.)
