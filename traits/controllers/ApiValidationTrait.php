<?php namespace PlanetaDelEste\ApiToolbox\Traits\Controllers;

use Illuminate\Support\Facades\Validator;
use October\Rain\Database\ModelException;
use October\Rain\Exception\ValidationException;

/**
 * Trait ApiValidationTrait
 *
 * @package PlanetaDelEste\ApiToolbox\Traits\Controllers
 *
 * @property array  $data
 * @property \Model $obModel
 */
trait ApiValidationTrait
{

    /**
     * @var array
     */
    public $rules = [];

    /**
     * @var array
     */
    public $attributeNames = [];

    /**
     * @var array
     */
    public $customMessages = [];

    /**
     * @throws \October\Rain\Exception\ValidationException
     */
    public function validate()
    {
        if (empty($this->rules)) {
            return;
        }

        $validator = Validator::make($this->data, $this->rules, $this->customMessages, $this->attributeNames);

        if (!$validator->passes()) {
            throw new ValidationException($validator);
        }
    }
}
