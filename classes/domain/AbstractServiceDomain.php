<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Domain;

use DB;
use Model;
use October\Rain\Exception\ApplicationException;
use PlanetaDelEste\ApiToolbox\Contracts\ServiceDomainInterface;
use PlanetaDelEste\ApiToolbox\Traits\EmitterTrait;

/**
 * Class AbstractServiceDomain
 *
 * Base abstract class for service domain logic
 *
 * @template TModel of \Model
 *
 * @implements ServiceDomainInterface<TModel>
 *
 * @method void onBeforeCreate(array &$data)
 * @method void onAfterCreate(TModel $model, array $data)
 * @method void onBeforeUpdate(TModel $model, array &$data)
 * @method void onAfterUpdate(TModel $model, array $data)
 * @method void onBeforeDelete(TModel $model)
 * @method void onAfterDelete(TModel $model)
 * @method void bindEvent(string $event, \Closure $callback, int $priority = 0)
 */
abstract class AbstractServiceDomain implements ServiceDomainInterface
{
    use EmitterTrait;

    protected ?string $mainEvent = 'service';

    /**
     * @var array Validation rules
     */
    protected $rules = [];

    /**
     * @var array Custom validation messages
     */
    protected $messages = [];

    /**
     * @var array Custom attribute names for validation
     */
    protected $attributeNames = [];

    /**
     * @return class-string<TModel>
     */
    abstract public function getModelClass(): string;

    /**
     * @param TModel $obModel
     *
     * @return bool
     */
    public function delete(Model $obModel): bool
    {
        return DB::transaction(function () use ($obModel) {
            $this->fireBeforeEvent('delete', [$obModel]);

            $result = $obModel->delete();

            $this->fireAfterEvent('delete', [$obModel]);

            return $result;
        });
    }

    /**
     * @param TModel $obModel
     * @param array  $data
     *
     * @return TModel
     */
    public function update(Model $obModel, array $data): ?Model
    {
        $this->fireBeforeEvent('update', [$obModel, &$data]);

        $obModel->update($data);

        $this->fireAfterEvent('update', [$obModel, $data]);

        return $obModel;
    }

    /**
     * @param array $data
     *
     * @return TModel
     */
    public function create(array $data): Model
    {
        $this->fireBeforeEvent('create', [&$data]);

        $obModel = new ($this->getModelClass())();
        $obModel->fill($data);
        $obModel->save();

        $this->fireAfterEvent('create', [$obModel, $data]);

        return $obModel;
    }

    /**
     * @param array  $data
     * @param string $sKeyColumn Model key column to check for existing record
     *
     * @return TModel|null
     */
    public function createOrUpdate(array $data, string $sKeyColumn = 'id'): Model
    {
        $iId = $data[$sKeyColumn] ?? null;

        if ($iId) {
            $obModel = ($this->getModelClass())::find($iId);

            if ($obModel) {
                return $this->update($obModel, $data);
            }
        }

        return $this->create($data);
    }

    /**
     * Validate the input data
     *
     * @param array $data
     *
     * @return bool
     *
     * @throws ApplicationException
     */
    protected function validate(array $data): bool
    {
        if (empty($this->rules)) {
            return true;
        }

        $validator = \Validator::make($data, $this->rules, $this->messages, $this->attributeNames);

        if ($validator->fails()) {
            throw new ApplicationException($validator->messages()->first());
        }

        return true;
    }
}
