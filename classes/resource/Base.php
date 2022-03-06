<?php namespace PlanetaDelEste\ApiToolbox\Classes\Resource;

use Carbon\Carbon;
use Event;
use Illuminate\Http\Resources\Json\Resource;
use Illuminate\Support\Collection;
use Lovata\Toolbox\Classes\Collection\ElementCollection;
use Lovata\Toolbox\Classes\Item\ElementItem;
use System\Classes\PluginManager;

/**
 * Class Base
 *
 * @package PlanetaDelEste\ApiToolbox\Classes\Resource
 *
 * @property \October\Rain\Argon\Argon $updated_at
 * @property \October\Rain\Argon\Argon $created_at
 */
abstract class Base extends Resource
{
    /** @var bool Add created_at, updated_at dates */
    public $addDates = true;

    /** @var string[] Property of type Date */
    public $arDates = ['created_at', 'updated_at'];

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray($request): array
    {
        if (empty($this->resource) ||
            (($this->resource instanceof Collection ||
                    $this->resource instanceof ElementItem ||
                    $this->resource instanceof ElementCollection)
                && $this->resource->isEmpty())
        ) {
            return [];
        }

        $arDataKeys = $this->getDataKeys();
        $arDates = $this->getDates();
        $arData = $this->getData();

        if (!empty($arData)) {
            // Filter items by getDataKeys
            $arData = array_intersect_key($arData, array_flip($arDataKeys));
        }

        if (!empty($arDates) && $this->addDates) {
            $arData = $arData + $arDates;
        }

        if (!empty($arDataKeys)) {
            foreach ($arDataKeys as $sKey) {
                if (array_key_exists($sKey, $arData)) {
                    continue;
                }
                $arData[$sKey] = $this->{$sKey};
            }
        }

        $this->translate($arData);

        if (is_string($this->getEvent())) {
            $arResponseData = Event::fire($this->getEvent(), [$this, $arData]);
            if (!empty($arResponseData)) {
                foreach ($arResponseData as $arResponseItem) {
                    if (empty($arResponseItem) || !is_array($arResponseItem)) {
                        continue;
                    }

                    foreach ($arResponseItem as $sKey => $sValue) {
                        $arData[$sKey] = $sValue;
                    }
                }
            }
        }

        return $arData;
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
            $sProp = is_numeric($sKey) ? $sValue : $sKey;
            $sFormat = is_string($sKey) && !is_numeric($sKey) ? $sValue : null;
            $obDate = $this->{$sProp};
            $sDateValue = $obDate instanceof Carbon
                ? $sFormat
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
     * @return string|null
     */
    abstract protected function getEvent(): ?string;

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
            || !$this->resource->getObject()->isClassExtendedWith('RainLab.Translate.Behaviors.TranslatableModel')) {
            return;
        }

        $arTranslatable = $this->resource->getObject()->translatable;
        if (empty($arTranslatable) || !is_array($arTranslatable)) {
            return;
        }

        $obTranslate = \RainLab\Translate\Classes\Translator::instance();

        if (!$sActiveLangCode = request()->header('Accept-Language')) {
            $sActiveLangCode = $obTranslate->getLocale();
        }

        if ($sActiveLangCode === $obTranslate->getDefaultLocale()) {
            return;
        }

        foreach ($arTranslatable as $sField) {
            //Check field name
            if (empty($sField) || !is_string($sField) || !isset($arData[$sField])) {
                continue;
            }

            $sTranslatedValue = $this->resource->getAttribute($sField.'|'.$sActiveLangCode);
            if (!empty($sTranslatedValue)) {
                array_set($arData, $sField, $sTranslatedValue);
            }
        }
    }
}
