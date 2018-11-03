<?php
namespace Charsen\Scaffold\Command;

use Charsen\Scaffold\Generator\CreateModelGenerator;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Create Model Command
 *
 * @author   Charsen <780537@gmail.com>
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
            ['schema_name', InputArgument::REQUIRED, 'The name of the schema. (Ex: Personnels)'],
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
        ];
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->alert($this->title);
        $schema_name = $this->argument('schema_name');
        $force       = $this->option('force') === null;

        $result = (new CreateModelGenerator($this, $this->filesystem, $this->utility))
            ->start($schema_name, $force);

        $this->info('done!');
    }
}
