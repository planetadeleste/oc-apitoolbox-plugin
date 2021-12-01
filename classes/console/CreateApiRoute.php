<?php namespace PlanetaDelEste\ApiToolbox\Classes\Console;

use PlanetaDelEste\ApiToolbox\Classes\Parser\Create\RouteCreateFile;

class CreateApiRoute extends CommonConsole
{
    /** @var string The console command name. */
    protected $name = 'toolbox:create.api.route';

    /** @var string The console command description. */
    protected $description = 'Create a new api route config';

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
        $this->createFile(RouteCreateFile::class);
    }
}
