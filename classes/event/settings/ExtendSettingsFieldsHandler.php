<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Event\Settings;

use Lovata\Toolbox\Classes\Event\AbstractBackendFieldHandler;
use Lovata\Toolbox\Models\Settings;

/**
 * Class ExtendSettingsFieldsHandler
 *
 * @package PlanetaDelEste\ApiToolbox\Classes\Event\Settings
 */
class ExtendSettingsFieldsHandler extends AbstractBackendFieldHandler
{
    /**
     * Extend fields model
     *
     * @param \Backend\Widgets\Form $obWidget
     */
    protected function extendFields($obWidget)
    {
        /** @var \Backend\Classes\FormField $obField */
        if ($obField = $obWidget->getField('thousands_sep')) {
            $arOptions = $obField->options;
            if (is_array($arOptions) && !empty($arOptions)) {
                $arOptions['comma'] = 'lovata.toolbox::lang.field.comma';
            }
            $obField->options = $arOptions;
        }
    }

    /**
     * Add fields model
     *
     * @param \Backend\Widgets\Form $obWidget
     */
    protected function addField($obWidget)
    {
        $obWidget->addTabFields([]);
    }

    /**
     * Get model class name
     *
     * @return string
     */
    protected function getModelClass(): string
    {
        return Settings::class;
    }

    /**
     * Get controller class name
     *
     * @return string
     */
    protected function getControllerClass(): string
    {
        return \System\Controllers\Settings::class;
    }
}
