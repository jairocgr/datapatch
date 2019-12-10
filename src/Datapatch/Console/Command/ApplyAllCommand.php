<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\DatabaseServer;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Datapatch\Core\Patch;
use Datapatch\Core\Database;
use Datapatch\Core\ScriptPath;

class ApplyAllCommand extends BaseCommand
{
    use ScriptRunner;

    protected function config()
    {
        $this->setName('apply-all')
             ->setDescription('Apply all the non-applied patches')
             ->setHelp('Apply all the non-applied patches')

             ->addOption(
                'force',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Force execution if patch/script was already applied!',
                FALSE
             );
    }

    protected function exec()
    {
        $this->console->newLine();

        $patches = $this->listNonAppliedPatches();

        if (empty($patches)) {
            return;
        }

        $npatches = count($patches);
        $n = 1;

        foreach ($patches as $patch)
        {
            $this->apply($patch, "({$n}/{$npatches})");

            $this->console->newLine();

            $n++;
        }

    }
}
