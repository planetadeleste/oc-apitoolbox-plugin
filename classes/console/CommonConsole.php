<?php namespace PlanetaDelEste\ApiToolbox\Classes\Console;

use Lang;
use Lovata\Toolbox\Classes\Console\CommonCreateFile;
use PHLAK\SemVer\Version;
use Yaml;

/**
 * @property-read string $slug
 * @property-read string $name
 * @property-read string $preview_image
 * @property-read string $file
 * @property-read string $images
 * @property-read string $fields
 * @property-read string $developer
 * @property-read string $author
 * @property-read string $expansion_author
 * @property-read string $expansion_plugin
 * @property-read string $plugin
 * @property-read string $model
 * @property-read string $controller
 * @property-read string $logo
 * @property-read string $import_svg
 * @property-read string $export_svg
 * @property-read string $empty_import_export_svg
 * @property-read string $import_export_svg
 * @property-read string $nested_tree
 * @property-read string $sortable
 * @property-read string $default_sorting
 * @property-read string $sorting
 * @property-read string $empty_sortable_nested_tree
 * @property-read string $view_count
 * @property-read string $active
 * @property-read string $command_parent
 * @property-read string $item
 * @property-read string $collection
 * @property-read string $store
 *
 * Studly case
 * @property-read string $studly_slug
 * @property-read string $studly_name
 * @property-read string $studly_preview_image
 * @property-read string $studly_file
 * @property-read string $studly_images
 * @property-read string $studly_fields
 * @property-read string $studly_developer
 * @property-read string $studly_author
 * @property-read string $studly_expansion_author
 * @property-read string $studly_expansion_plugin
 * @property-read string $studly_plugin
 * @property-read string $studly_model
 * @property-read string $studly_controller
 * @property-read string $studly_logo
 * @property-read string $studly_import_svg
 * @property-read string $studly_export_svg
 * @property-read string $studly_empty_import_export_svg
 * @property-read string $studly_import_export_svg
 * @property-read string $studly_nested_tree
 * @property-read string $studly_sortable
 * @property-read string $studly_default_sorting
 * @property-read string $studly_sorting
 * @property-read string $studly_empty_sortable_nested_tree
 * @property-read string $studly_view_count
 * @property-read string $studly_active
 * @property-read string $studly_command_parent
 * @property-read string $studly_item
 * @property-read string $studly_collection
 * @property-read string $studly_store
 *
 * Lower case
 * @property-read string $lower_slug
 * @property-read string $lower_name
 * @property-read string $lower_preview_image
 * @property-read string $lower_file
 * @property-read string $lower_images
 * @property-read string $lower_fields
 * @property-read string $lower_developer
 * @property-read string $lower_author
 * @property-read string $lower_expansion_author
 * @property-read string $lower_expansion_plugin
 * @property-read string $lower_plugin
 * @property-read string $lower_model
 * @property-read string $lower_controller
 * @property-read string $lower_logo
 * @property-read string $lower_import_svg
 * @property-read string $lower_export_svg
 * @property-read string $lower_empty_import_export_svg
 * @property-read string $lower_import_export_svg
 * @property-read string $lower_nested_tree
 * @property-read string $lower_sortable
 * @property-read string $lower_default_sorting
 * @property-read string $lower_sorting
 * @property-read string $lower_empty_sortable_nested_tree
 * @property-read string $lower_view_count
 * @property-read string $lower_active
 * @property-read string $lower_command_parent
 * @property-read string $lower_item
 * @property-read string $lower_collection
 * @property-read string $lower_store
 */
class CommonConsole extends CommonCreateFile
{
    const CODE_VERSION = 'version';

    /**
     * @var string[]
     */
    protected $arLogoLovata = [
        '▒█▀▀█ █░░ █▀▀█ █▀▀▄ █▀▀ ▀▀█▀▀ █▀▀█ ▒█▀▀▄ █▀▀ █░░ ▒█▀▀▀ █▀▀ ▀▀█▀▀ █▀▀',
        '▒█▄▄█ █░░ █▄▄█ █░░█ █▀▀ ░░█░░ █▄▄█ ▒█░▒█ █▀▀ █░░ ▒█▀▀▀ ▀▀█ ░░█░░ █▀▀',
        '▒█░░░ ▀▀▀ ▀░░▀ ▀░░▀ ▀▀▀ ░░▀░░ ▀░░▀ ▒█▄▄▀ ▀▀▀ ▀▀▀ ▒█▄▄▄ ▀▀▀ ░░▀░░ ▀▀▀',
    ];

    public function __get($sName)
    {
        return array_get($this->arData, 'replace.'.$sName);
    }

    /**
     * Set model
     *
     * @throws \PHLAK\SemVer\Exceptions\InvalidVersionException
     */
    protected function setVersion(): void
    {
        if ($this->checkAdditionList(self::CODE_VERSION)) {
            return;
        }

        $this->setAdditionList(self::CODE_VERSION);
        $sVersion = $this->getNextVersion();
        $sMessage = Lang::get('lovata.toolbox::lang.message.set', [
            'name'    => self::CODE_VERSION,
            'example' => $sVersion ?: 'v1.0.1',
        ]);

        $sModel = $this->ask($sMessage, $sVersion);
        $this->setRegisterString($sModel, self::CODE_VERSION);
    }

    /**
     * @return string|null
     * @throws \PHLAK\SemVer\Exceptions\InvalidVersionException
     */
    protected function getNextVersion(): ?string
    {
        if (!$this->lower_author || !$this->lower_plugin) {
            return null;
        }

        $sAuthor = strtolower($this->lower_author);
        $sPlugin = strtolower($this->lower_plugin);
        $sVersionPath = plugins_path($sAuthor.'/'.$sPlugin.'/updates/version.yaml');

        if (!\File::exists($sVersionPath)) {
            return null;
        }

        $arYAML = Yaml::parseFile($sVersionPath);
        $arVersions = array_keys($arYAML);
        if ($sVersion = array_pop($arVersions)) {
            $sPrefix = $sVersion[0];
            $obVersion = new Version($sVersion);
            $obVersion->incrementPatch();

            return $obVersion->prefix(is_numeric($sPrefix) ? '' : $sPrefix);
        }

        return null;
    }

    protected function getModelCachedAttrs()
    {
        if ($obModel = $this->getObModel()) {
            if (property_exists($obModel, 'cached')) {
                $arColumns = $obModel->cached;
                $sFileContent = \File::get(
                    plugins_path('planetadeleste/apitoolbox/classes/parser/templates/cached_attributes.stub')
                );
                $sContent = \Twig::parse($sFileContent, ['attributes' => $arColumns]);
                array_set($this->arData, 'replace.cached', $sContent);
                $this->setEnableList($arColumns);
            }
        }
    }

    /**
     * @return null|\Model|\Eloquent
     */
    protected function getObModel()
    {
        $sAuthor = $this->studly_expansion_author ?: $this->studly_author;
        $sPlugin = $this->studly_expansion_plugin ?: $this->studly_plugin;
        $sModel = $this->studly_model;
        if (!$sAuthor || !$sPlugin || !$sModel) {
            return null;
        }

        return app(join("\\", [$sAuthor, $sPlugin, 'Models', $sModel]));
    }

}
