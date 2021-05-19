<?php namespace PlanetaDelEste\ApiToolbox\Classes\Event;

use Lovata\Toolbox\Classes\Collection\ElementCollection;
use Lovata\Toolbox\Classes\Item\ElementItem;
use October\Rain\Events\Dispatcher;
use PlanetaDelEste\ApiToolbox\Classes\Api\Base;
use PlanetaDelEste\ApiToolbox\Plugin;

/**
 * Class ApiControllerHandler
 *
 * @package PlanetaDelEste\ApiToolbox\Classes\Event
 *
 * @method void beforeSave(Base $obController, \Model $obModel, array &$arData)
 * @method void afterSave(Base $obController, \Model $obModel, array $arData)
 * @method void beforeDestroy(Base $obController, \Model $obModel)
 * @method void extendIndex(Base $obController, ElementCollection &$obCollection)
 * @method void extendList(Base $obController, ElementCollection &$obCollection)
 * @method void extendShow(Base $obController, ElementItem $obItem)
 * @method void beforeShowCollect(Base $obController, mixed &$value)
 */
abstract class ApiControllerHandler
{
    public function subscribe(Dispatcher $obEvent)
    {
        if (method_exists($this, 'beforeSave')) {
            $obEvent->listen(
                Plugin::EVENT_BEFORE_SAVE,
                function ($obController, $obModel, &$arData) {
                    if (!$this->valid($obController, $obModel)) {
                        return;
                    }

                    $this->beforeSave($obController, $obModel, $arData);
                }
            );
        }

        if (method_exists($this, 'afterSave')) {
            $obEvent->listen(
                Plugin::EVENT_AFTER_SAVE,
                function ($obController, $obModel, $arData) {
                    if (!$this->valid($obController, $obModel)) {
                        return;
                    }

                    $this->afterSave($obController, $obModel, $arData);
                }
            );
        }

        if (method_exists($this, 'beforeDestroy')) {
            $obEvent->listen(
                Plugin::EVENT_BEFORE_DESTROY,
                function ($obController, $obModel) {
                    if (!$this->valid($obController, $obModel)) {
                        return;
                    }

                    $this->beforeDestroy($obController, $obModel);
                }
            );
        }

        if (method_exists($this, 'extendIndex')) {
            $obEvent->listen(
                Plugin::EVENT_API_EXTEND_INDEX,
                function ($obController, &$obCollection) {
                    if (!$this->valid($obController)) {
                        return;
                    }

                    $this->extendIndex($obController, $obCollection);
                }
            );
        }

        if (method_exists($this, 'extendList')) {
            $obEvent->listen(
                Plugin::EVENT_API_EXTEND_LIST,
                function ($obController, &$obCollection) {
                    if (!$this->valid($obController)) {
                        return;
                    }

                    $this->extendList($obController, $obCollection);
                }
            );
        }

        if (method_exists($this, 'extendShow')) {
            $obEvent->listen(
                Plugin::EVENT_API_EXTEND_SHOW,
                function ($obController, $obItem) {
                    if (!$this->valid($obController)) {
                        return;
                    }

                    $this->extendShow($obController, $obItem);
                }
            );
        }

        if (method_exists($this, 'beforeShowCollect')) {
            $obEvent->listen(
                Plugin::EVENT_API_EXTEND_SHOW,
                function ($obController, &$value) {
                    if (!$this->valid($obController)) {
                        return;
                    }

                    $this->beforeShowCollect($obController, $value);
                }
            );
        }
    }

    /**
     * Get controller class name
     *
     * @return string
     */
    abstract protected function getControllerClass(): string;

    /**
     * Get model class name
     *
     * @return string
     */
    abstract protected function getModelClass(): string;

    protected function valid($obController, $obModel = null): bool
    {
        if (get_class($obController) !== $this->getControllerClass()) {
            return false;
        }

        if ($obModel === null) {
            return true;
        }

        if (get_class($obModel) !== $this->getModelClass()) {
            return false;
        }

        return true;
    }
}
