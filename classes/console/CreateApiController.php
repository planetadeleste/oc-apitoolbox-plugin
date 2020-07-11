<?php namespace PlanetaDelEste\ApiToolbox\Classes\Console;

use October\Rain\Scaffold\GeneratorCommand;
use October\Rain\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreateApiController extends GeneratorCommand
{
    /**
     * @var string The console command name.
     */
    protected $name = 'apitoolbox:create:controller';

    /**
     * @var string The console command description.
     */
    protected $description = 'Create a new Api controller class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Api Controller';

    /**
     * A mapping of stub to generated file.
     *
     * @var array
     */
    protected $stubs = [
        'controllers/Controller.stub' => 'controllers/api/{{studly_name}}.php',
    ];

    /**
     * @inheritDoc
     */
    protected function prepareVars()
    {
        $pluginCode = $this->argument('plugin');

        $parts = explode('.', $pluginCode);
        $plugin = array_pop($parts);
        $author = array_pop($parts);

        $controller = $this->argument('controller');

        /*
         * Determine the model name to use,
         * either supplied or singular from the controller name.
         */
        $model = $this->option('model');
        if (!$model) {
            $model = Str::singular($controller);
        }

        return [
            'name' => $controller,
            'model' => $model,
            'author' => $author,
            'plugin' => $plugin
        ];
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['plugin', InputArgument::REQUIRED, 'The name of the plugin to create. Eg: RainLab.Blog'],
            ['controller', InputArgument::REQUIRED, 'The name of the controller to create. Eg: Posts'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Overwrite existing files with generated ones.'],
            ['model', null, InputOption::VALUE_OPTIONAL, 'The name of the model. Eg: Post'],
        ];
    }
}
