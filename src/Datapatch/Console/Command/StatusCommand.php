<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\DatabaseServer;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Datapatch\Core\Patch;
use Datapatch\Core\Database;
use Datapatch\Core\ScriptPath;

class StatusCommand extends BaseCommand
{
    use ScriptRunner;

    protected function config()
    {
        $this->setName('status')
             ->setDescription('List all non-applied patches')
             ->setHelp('List all non-applied patches')

             ->addOption(
                'force',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Force execution if production',
                FALSE
             );
    }

    protected function exec()
    {
        $this->console->newLine();

        $patches = $this->listNonAppliedPatches();
    }
}
