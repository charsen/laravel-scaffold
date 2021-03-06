<?php
namespace Charsen\Scaffold\Command;

use Charsen\Scaffold\Generator\FreshStorageGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Fresh Database Storage Command
 *
 * @author Charsen https://github.com/charsen
 */
class FreshStorageCommand extends Command
{
    /**
     * The console command title.
     *
     * @var string
     */
    protected $title = 'Fresh Sechma Storage Command';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'scaffold:fresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fresh Sechma Storage Command';

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
                'Rebuild All Storage Files.',
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

        $clean  = $this->option('clean') === null;
        $result = (new FreshStorageGenerator($this, $this->filesystem, $this->utility))
            ->start($clean);

        $this->tipDone($result);
    }
}
