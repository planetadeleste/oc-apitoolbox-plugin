<?php namespace PlanetaDelEste\ApiToolbox\Classes\Resource;

use Event;
use Illuminate\Http\Resources\Json\Resource;

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
    public function toArray($request)
    {
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
            $arResponseData = Event::fire($this->getEvent(), [$arData, $this]);
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
     * @return string|null
     */
    abstract protected function getEvent();

    /**
     * @return array
     */
    abstract public function getData();

    /**
     * Returns all used Item key attributes
     *
     * @return array
     */
    public function getDataKeys()
    {
        return [];
    }


    /**
     * Get item dates in DateTimeString format
     *
     * @return array
     */
    public function getDates(): array
    {
        return [
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}
