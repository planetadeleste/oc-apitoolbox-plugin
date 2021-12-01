<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Console;

use PlanetaDelEste\ApiToolbox\Classes\Parser\Create\ControllerCreateFile;

class CreateApiAll extends CommonConsole
{
    /** @var string The console command name. */
    protected $name = 'toolbox:create.api.all';
    /** @var string The console command description. */
    protected $description = 'Create a new api collection and resources.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        parent::handle();

        $this->setAuthor(true);
        $this->setPlugin(true);
        $this->setModel();
        $this->setController();
        $this->setAdditionList(self::CODE_COMMAND_PARENT);
        $this->callCommandList();
    }

    protected function callCommandList()
    {
        $this->call('toolbox:create.api.resourceitem', ['data' => $this->arData]);
        $this->call('toolbox:create.api.resourceshow', ['data' => $this->arData]);
        $this->call('toolbox:create.api.resourceindex', ['data' => $this->arData]);
        $this->call('toolbox:create.api.resourcelist', ['data' => $this->arData]);
        $this->call('toolbox:create.api.controller', ['data' => $this->arData]);
        $this->call('toolbox:create.api.route', ['data' => $this->arData]);
    }
}
