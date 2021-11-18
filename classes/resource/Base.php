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
        return [
            'updated_at' => $this->updated_at && $this->updated_at instanceof Carbon
                ? $this->updated_at->toDateTimeString()
                : $this->updated_at,
            'created_at' => $this->created_at && $this->created_at instanceof Carbon
                ? $this->created_at->toDateTimeString()
                : $this->created_at,
        ];
    }

    /**
     * @return array
     */
    abstract public function getData(): array;

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
     * @return string|null
     */
    abstract protected function getEvent(): ?string;
}
