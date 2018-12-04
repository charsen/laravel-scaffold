<?php
namespace Charsen\Scaffold\Command;

use Charsen\Scaffold\Generator\UpdateACLGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Update ACL Command
 *
 * @author Charsen https://github.com/charsen
 */
class UpdateACLCommand extends Command
{
    /**
     * The console command title.
     *
     * @var string
     */
    protected $title = 'Update ACL Command';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'scaffold:acl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update ACL File';
    
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->alert($this->title);
    
        $result = (new UpdateACLGenerator($this, $this->filesystem, $this->utility))->start();
    
        $this->tipDone();
    }
}
