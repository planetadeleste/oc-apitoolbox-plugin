<?php namespace PlanetaDelEste\ApiToolbox\Classes\Resource;

use Carbon\Carbon;
use Event;
use Illuminate\Http\Resources\Json\Resource;
use Illuminate\Support\Collection;
use Lovata\Toolbox\Classes\Collection\ElementCollection;
use Lovata\Toolbox\Classes\Item\ElementItem;

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
}
