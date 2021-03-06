<?php namespace PlanetaDelEste\ApiToolbox\Classes\Console;

use PlanetaDelEste\ApiToolbox\Classes\Parser\Create\ResourceShowCreateFile;

class CreateApiResourceShow extends CommonConsole
{
    /** @var string The console command name. */
    protected $name = 'toolbox:create.api.resourceshow';
    /** @var string The console command description. */
    protected $description = 'Create a new api resource show item.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        parent::handle();

        $this->setAuthor(true);
        $this->setPlugin(true);
        $this->setModel();
//        $this->setFieldList(['external_id', self::CODE_VIEW_COUNT]);
        $this->getModelCachedAttrs();
        $this->setAdditionList(self::CODE_COMMAND_PARENT);
        $this->createFile(ResourceShowCreateFile::class);
    }
}
