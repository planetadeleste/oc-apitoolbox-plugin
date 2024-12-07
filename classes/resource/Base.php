<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Resource;

use Carbon\Carbon;
use Closure;
use Event;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Lovata\Toolbox\Classes\Collection\ElementCollection;
use Lovata\Toolbox\Classes\Item\ElementItem;
use October\Rain\Argon\Argon;
use RainLab\Translate\Classes\Translator;
use System\Classes\PluginManager;

/**
 * Class Base
 *
 * @package PlanetaDelEste\ApiToolbox\Classes\Resource
 *
 * @property Argon $updated_at
 * @property Argon $created_at
 *
 * @method static self make(...$parameters)
 */
abstract class Base extends JsonResource
{
    use HasAttributes;

    /**
     * @var bool Add created_at, updated_at dates
     */
    public bool $addDates = true;

    /**
     * @var array<string> Property of type Date
     */
    public array $arDates = ['created_at', 'updated_at'];

    /**
     * @var array Keys to exclude from array
     */
    public array $arExclude = [];

    /**
     * @param Request $request
     *
     * @return array
     */
    public function toArray(?Request $request = null): array
    {
        if (empty($this->resource)
            || (($this->resource instanceof Collection
            || $this->resource instanceof ElementItem
            || $this->resource instanceof ElementCollection)
            && $this->resource->isEmpty())
        ) {
            return [];
        }

        $arDataKeys = $this->getDataKeys();
        $arDates    = $this->getDates();
        $arData     = $this->getData();

        $this->fire($arData, $arDataKeys);

        if (!empty($this->arExclude)) {
            $arDataKeys = array_diff($arDataKeys, $this->arExclude);
        }

        if (!empty($arData)) {
            // Filter items by getDataKeys
            $arData = array_intersect_key($arData, array_flip($arDataKeys));
        }

        if (!empty($arDates) && $this->addDates) {
            $arData += $arDates;
        }

        if (!empty($arDataKeys)) {
            foreach ($arDataKeys as $sKey) {
                if (array_key_exists($sKey, $arData) || in_array($sKey, $this->arExclude)) {
                    if (($fn = array_get($arData, $sKey)) && $fn instanceof Closure) {
                        array_set($arData, $sKey, $fn());
                    }

                    continue;
                }

                $arData[$sKey] = $this->{$sKey};
            }
        }

        $this->translate($arData);
        $this->castAttributes($arData);

        return $arData;
    }

    /**
     * Fire event
     * @param array $arData
     * @param array $arDataKeys
     *
     * @return void
     */
    protected function fire(array &$arData, array &$arDataKeys): void
    {
        if (!is_string($this->getEvent())) {
            return;
        }

        $arResponseData = Event::fire($this->getEvent(), [$this, $arData]);

        if (empty($arResponseData)) {
            return;
        }

        foreach ($arResponseData as $arResponseItem) {
            if (empty($arResponseItem) || !is_array($arResponseItem)) {
                continue;
            }

            foreach ($arResponseItem as $sKey => $sValue) {
                $arData[$sKey] = $sValue;
                $arDataKeys[]  = $sKey;
            }
        }
    }

    /**
     * Get item dates in DateTimeString format
     *
     * @return array
     */
    public function getDates(): array
    {
        $arDates = [];

        if (empty($this->arDates)) {
            return $arDates;
        }

        foreach ($this->arDates as $sKey => $sValue) {
            $sProp      = is_numeric($sKey) ? $sValue : $sKey;
            $sFormat    = is_string($sKey) && !is_numeric($sKey) ? $sValue : null;
            $obDate     = $this->{$sProp};
            $sDateValue = $obDate instanceof Carbon
                ? !empty($sFormat)
                    ? $obDate->format($sFormat)
                    : $obDate->toDateTimeString()
                : $obDate;

            $arDates[$sProp] = $sDateValue;
        }

        return $arDates;
    }

    /**
     * Returns all used Item key attributes
     *
     * @return array
     */
    public function getDataKeys(): array
    {
        return [];
    }

    /**
     * Key => value array of mapped resource
     * Value can be a Closure with return value
     *
     * @return array
     */
    abstract public function getData(): array;

    /**
     * Get event name of item resource
     *
     * @return string|null
     */
    public function event(): ?string
    {
        return $this->getEvent();
    }

    /**
     * @param array<string>|string $sKey
     *
     * @return void
     */
    public function exclude($sKey): void
    {
        $arKeyList = is_array($sKey) ? $sKey : func_get_args();
        $arKeyList = array_merge($this->arExclude, $arKeyList);
        $arKeyList = array_unique($arKeyList);

        $this->arExclude = $arKeyList;
    }

    /**
     * @param mixed $skey
     *
     * @return $this
     */
    public function without($skey): self
    {
        $this->exclude($skey);

        return $this;
    }

    /**
     * @param string $sKey
     *
     * @return bool
     */
    public function isExcluded(string $sKey): bool
    {
        return !empty($this->arExclude) && in_array($sKey, $this->arExclude);
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        return [];
    }

    /**
     * @return string|null
     */
    abstract protected function getEvent(): ?string;

    /**
     * Add the casted attributes to the attributes array.
     *
     * @param array $arData
     *
     * @return void
     */
    protected function castAttributes(array &$arData): void
    {
        foreach (array_keys($this->getCasts()) as $sKey) {
            if (!isset($arData[$sKey])) {
                continue;
            }

            $arData[$sKey] = $this->castAttribute($sKey, $arData[$sKey]);
        }
    }

    /**
     * Map translated data
     *
     * @param array $arData
     *
     * @return void
     */
    protected function translate(array &$arData): void
    {
        if (!$this->resource instanceof ElementItem
            || empty($this->resource->getObject())
            || !PluginManager::instance()->hasPlugin('RainLab.Translate')
            || !$this->resource->getObject()->isClassExtendedWith('RainLab.Translate.Behaviors.TranslatableModel')
        ) {
            return;
        }

        $arTranslatable = $this->resource->getObject()->translatable;

        if (empty($arTranslatable) || !is_array($arTranslatable)) {
            return;
        }

        $obTranslate = Translator::instance();

        if (!$sActiveLangCode = request()->header('Accept-Language')) {
            $sActiveLangCode = $obTranslate->getLocale();
        }

        if ($sActiveLangCode === $obTranslate->getDefaultLocale()) {
            return;
        }

        foreach ($arTranslatable as $sField) {
            // Check field name
            if (empty($sField) || !is_string($sField) || !isset($arData[$sField])) {
                continue;
            }

            $sTranslatedValue = $this->resource->getAttribute($sField.'|'.$sActiveLangCode);

            if (empty($sTranslatedValue)) {
                continue;
            }

            array_set($arData, $sField, $sTranslatedValue);
        }
    }
}
