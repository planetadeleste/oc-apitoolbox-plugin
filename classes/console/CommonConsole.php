<?php namespace PlanetaDelEste\ApiToolbox\Classes\Console;

use Lovata\Toolbox\Classes\Console\CommonCreateFile;

class CommonConsole extends CommonCreateFile
{
    /**
     * @var string[]
     */
    protected $arLogoLovata = [
        '▒█▀▀█ █░░ █▀▀█ █▀▀▄ █▀▀ ▀▀█▀▀ █▀▀█ ▒█▀▀▄ █▀▀ █░░ ▒█▀▀▀ █▀▀ ▀▀█▀▀ █▀▀',
        '▒█▄▄█ █░░ █▄▄█ █░░█ █▀▀ ░░█░░ █▄▄█ ▒█░▒█ █▀▀ █░░ ▒█▀▀▀ ▀▀█ ░░█░░ █▀▀',
        '▒█░░░ ▀▀▀ ▀░░▀ ▀░░▀ ▀▀▀ ░░▀░░ ▀░░▀ ▒█▄▄▀ ▀▀▀ ▀▀▀ ▒█▄▄▄ ▀▀▀ ░░▀░░ ▀▀▀',
    ];

    protected function getModelCachedAttrs()
    {
        $sExpansionAuthor = array_get($this->arData, 'replace.studly_expansion_author');
        $sExpansionPlugin = array_get($this->arData, 'replace.studly_expansion_plugin');
        $sModel = array_get($this->arData, 'replace.studly_model');
        if (!$sExpansionAuthor || !$sExpansionPlugin || !$sModel) {
            return;
        }

        /** @var \Model|\Eloquent $obModel */
        $obModel = app(join("\\", [$sExpansionAuthor, $sExpansionPlugin, 'Models', $sModel]));
        if ($obModel) {
            if (property_exists($obModel, 'cached')) {
                $arColumns = $obModel->cached;
                $sFileContent = \File::get(plugins_path('planetadeleste/apitoolbox/classes/parser/templates/cached_attributes.stub'));
                $sContent = \Twig::parse($sFileContent, ['attributes' => $arColumns]);
                array_set($this->arData, 'replace.cached', $sContent);
                $this->setEnableList($arColumns);
            }
        }
    }
}
