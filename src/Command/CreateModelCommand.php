<?php

namespace Charsen\Scaffold\Command;

use Charsen\Scaffold\Generator\CreateControllerGenerator;
use Charsen\Scaffold\Generator\CreateMigrationGenerator;
use Charsen\Scaffold\Generator\CreateModelGenerator;
use Charsen\Scaffold\Generator\CreateRepositoryGenerator;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Create Model Command
 *
 * @author Charsen https://github.com/charsen
 */
class CreateModelCommand extends Command
{
    /**
     * The console command title.
     *
     * @var string
     */
    protected $title = 'Create Model Command';
    
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'scaffold:model';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Model Command';
    
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
                'force',
                '-f',
                InputOption::VALUE_OPTIONAL,
                'Overwrite Model File.',
                false,
            ],
            [
                'controller',
                '-c',
                InputOption::VALUE_OPTIONAL,
                'Create Controller File.',
                false,
            ],
            [
                'repository',
                '-r',
                InputOption::VALUE_OPTIONAL,
                'Create Repository File.',
                false,
            ],
            [
                'migration',
                '-m',
                InputOption::VALUE_OPTIONAL,
                'Create migration File.',
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
        if (empty($schema_name))
        {
            $file_names  = $this->utility->getSchemaNames();
            $schema_name = $this->choice('What is schema name?', $file_names);
        }
        
        $force       = $this->option('force') === null;
        $controller  = $this->option('controller') === null;
        $repository  = $this->option('repository') === null;
        $migration   = $this->option('migration') === null;
    
        $this->tipCallCommand('scaffold:model');
        $result = (new CreateModelGenerator($this, $this->filesystem, $this->utility))
            ->start($schema_name, $force);
        
        if ($controller) {
            $this->tipCallCommand('scaffold:controller');
            $result = (new CreateControllerGenerator($this, $this->filesystem, $this->utility))
                ->start($schema_name, $force);
        }
        if ($repository) {
            $this->tipCallCommand('scaffold:repository');
            $result = (new CreateRepositoryGenerator($this, $this->filesystem, $this->utility))
                ->start($schema_name, $force);
        }
        if ($migration) {
            $this->tipCallCommand('scaffold:migration');
            $result = (new CreateMigrationGenerator($this, $this->filesystem, $this->utility))
                ->start($schema_name, $force);
            
            if ($this->confirm("Do you want to Execute 'artisan migrate' ?", 'yes'))
            {
                $this->tipCallCommand('migrate');
                $this->call('migrate');
            }
        }
    
        $this->tipDone();
    }
}
