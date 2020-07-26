<?php namespace PlanetaDelEste\ApiToolbox\Traits\Controllers;

/**
 * Trait ApiCastTrait
 *
 * @package PlanetaDelEste\ApiToolbox\Traits\Controllers
 *
 * @method string getModelClass()
 */
trait ApiCastTrait
{
    /**
     * @param array $data
     */
    protected function setCastData(array &$data)
    {
        if (!method_exists($this, 'getModelClass')) {
            return;
        }

        if (empty($this->getModelClass())) {
            return;
        }

        $casts = app($this->getModelClass())->getCasts();

        if (empty($casts)) {
            return;
        }

        foreach ($casts as $column => $type) {
            $value = array_get($data, $column);
            if (is_null($value)) {
                continue;
            }
            switch ($type) {
                case 'boolean':
                    $value = $this->toBool($value);
                    break;
                case 'int':
                    $value = $this->toInt($value);
                    break;
                case 'float':
                    $value = $this->toFloat($value);
                    break;
            }

            array_set($data, $column, $value);
        }
    }

    protected function toBool($value)
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function toInt($value)
    {
        return is_int($value) ? $value : filter_var($value, FILTER_VALIDATE_INT);
    }

    protected function toFloat($value)
    {
        return is_float($value) ? $value : filter_var($value, FILTER_VALIDATE_FLOAT);
    }
}
