<?php namespace PlanetaDelEste\ApiToolbox\Classes\Console;

use Lovata\Toolbox\Classes\Parser\Create\ModelCreateFile;

class CreateApiResources extends CommonConsole
{
    /** @var string The console command name. */
    protected $name = 'toolbox:create.api.resources';
    /** @var string The console command description. */
    protected $description = 'Create a new api resource items.';

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
        $this->callCommandList();
    }

    protected function callCommandList()
    {
        $this->call('toolbox:create.api.resourceitem', ['data' => $this->arData]);
        $this->call('toolbox:create.api.resourceshow', ['data' => $this->arData]);
        $this->call('toolbox:create.api.resourceindex', ['data' => $this->arData]);
        $this->call('toolbox:create.api.resourcelist', ['data' => $this->arData]);
    }
}
