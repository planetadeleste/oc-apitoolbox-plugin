<?php

namespace PlanetaDelEste\ApiToolbox\Classes\Console;

use PlanetaDelEste\ApiToolbox\Classes\Parser\Create\UpdateModelCreateFile;

class CreateUpdateModel extends CommonConsole
{
    /** @var string The console command name. */
    protected $name = 'toolbox:create.model.update';

    /** @var string The console command description. */
    protected $description = 'Create a new model migration';

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
        $this->setVersion();
        $this->setAdditionList(self::CODE_COMMAND_PARENT);

        if ($obModel = $this->getObModel()) {
            array_set($this->arData, 'replace.table', $obModel->getTable());
        }

        $this->createFile(UpdateModelCreateFile::class);
    }
}
