<?php

namespace Charsen\Scaffold\Command;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Input\InputOption;
use Charsen\Scaffold\Generator\CreateApiGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Charsen\Scaffold\Generator\CreateModelGenerator;
use Charsen\Scaffold\Generator\FreshStorageGenerator;
use Charsen\Scaffold\Generator\CreateMigrationGenerator;
use Charsen\Scaffold\Generator\CreateControllerGenerator;
use Charsen\Scaffold\Generator\UpdateMultilingualGenerator;
use Charsen\Scaffold\Generator\UpdateAuthorizationGenerator;
/**
 * Free : Release your hands
 *
 * @author Charsen https://github.com/charsen
 */
class FreeCommand extends Command
{
    /**
     * The console command title.
     *
     * @var string
     */
    protected $title = 'Free : Release your hands';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'scaffold:free';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Controllers, Models, Migrations ...';

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['schema_name', InputArgument::OPTIONAL, 'The name of the schema. (Ex: Personnels)'],
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
            [
                'clean',
                '-c',
                InputOption::VALUE_OPTIONAL,
                'Overwrite All Storage Files.',
                false,
            ],
            [
                'force',
                '-f',
                InputOption::VALUE_OPTIONAL,
                'Overwrite Models/Controllers Files.',
                false,
            ],
        ];
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        $this->alert($this->title);

        $schema_name = $this->argument('schema_name');
        if (empty($schema_name)) {
            $file_names  = $this->utility->getSchemaNames();
            $schema_name = $this->choice('What is schema name?', $file_names);
        }

        $clean         = $this->option('clean') === null;
        $force         = $this->option('force') === null;

        $schema_path   = $this->utility->getDatabasePath('schema');
        $yaml          = new Yaml;
        $data          = $yaml::parseFile($schema_path . $schema_name . '.yaml');

        $this->tipCallCommand('scaffold:fresh');
        (new FreshStorageGenerator($this, $this->filesystem, $this->utility))->start($clean);

        $this->tipCallCommand('scaffold:model');
        (new CreateModelGenerator($this, $this->filesystem, $this->utility))->start($schema_name, $force);

        $this->tipCallCommand('scaffold:controller');
        (new CreateControllerGenerator($this, $this->filesystem, $this->utility))->start($schema_name, $force);

        $this->tipCallCommand('scaffold:api');
        //$namespace     = "{$data['package']['folder']}/{$data['module']['folder']}";
        $namespace     = "{$data['module']['folder']}";
        (new CreateApiGenerator($this, $this->filesystem, $this->utility))->start($namespace, false, $force);

        $this->tipCallCommand('scaffold:i18n');
        (new UpdateMultilingualGenerator($this, $this->filesystem, $this->utility))->start();

        $this->tipCallCommand('scaffold:auth');
        (new UpdateAuthorizationGenerator($this, $this->filesystem, $this->utility))->start();

        $this->tipCallCommand('scaffold:migration');
        (new CreateMigrationGenerator($this, $this->filesystem, $this->utility))->start($schema_name);

        if ($this->confirm("Do you want to Execute 'artisan migrate' ?", 'yes'))
        {
            $this->tipCallCommand('migrate');
            $this->call('migrate');
        }

        $this->tipDone();
    }
}
