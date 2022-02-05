<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Item;

use Cms\Classes\Page as CmsPage;
use Lovata\Toolbox\Classes\Helper\PageHelper;
use Lovata\Toolbox\Classes\Item\ElementItem as ToolboxElementItem;

class ElementItem extends ToolboxElementItem
{
    /**
     * Returns URL of a brand page.
     *
     * @param string|null $sPageCode
     *
     * @return string
     */
    public function getPageUrl(string $sPageCode = null): string
    {
        if (!$sPageCode) {
            $sPageCode = $this->getPageCode();
        }

        //Get URL params
        $arParamList = $this->getPageParamList($sPageCode);

        //Generate page URL
        return CmsPage::url($sPageCode, $arParamList);
    }

    /**
     * Get URL param list by page code
     *
     * @param string $sPageCode
     *
     * @return array
     */
    public function getPageParamList(string $sPageCode): array
    {
        $arPageParamList = [];

        //Get URL params for page
        $arParamList = PageHelper::instance()->getUrlParamList($sPageCode, $this->getComponentName());
        if (!empty($arParamList)) {
            $sPageParam = array_shift($arParamList);
            $arPageParamList[$sPageParam] = $this->slug;
        }

        return $arPageParamList;
    }

    protected function getPageCode(): string
    {
        $sClass = class_basename(get_called_class());
        $sClass = camel_case($sClass);
        if (ends_with($sClass, '_item')) {
            $arClassItems = explode('_', $sClass);
            array_pop($arClassItems);
            $sClass = join('_', $arClassItems);
        }

        return $sClass;
    }

    protected function getComponentName(): string
    {
        $sClass = class_basename(get_called_class());
        $sClass = ends_with($sClass, 'Item') ? substr($sClass, 0, -4) : $sClass;

        return $sClass.'Page';
    }
}
