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
                'bundle',
                'b',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Choosen bundle to be inspected (ex: 2019.10.27, v3.1.28)'
             )

             ->addOption(
                'patch',
                'p',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Choosen patch to be inspected (ex: TASK-1232)'
             )

             ->addOption(
                'script',
                'c',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Choosen script inspected (ex: TASK-1232/change.sql)'
             )

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

        if ($this->pointToSpecificItens())
        {
            foreach ($this->input->getOption('bundle') as $bundle)
            {
                $bundle = $this->datapatch->getBundle($bundle);
                $this->puts("Bundle <b>{$bundle}</> at {$this->getFormattedEnv()}:");
                $this->printPatchesStatuses($bundle->getPatches());
                $this->puts("");
            }

            foreach ($this->input->getOption('patch') as $patch)
            {
                $patch = $this->datapatch->getPatch($patch);

                if ($patch->isErrored()) {
                    $status = "<err>errored</>";
                } elseif ($patch->isUnfinished()) {
                    $status = "<warn>unfinished</>";
                } elseif ($patch->isPartiallyApplied()) {
                    $status = "<b>partially applied</>";
                } elseif ($patch->isFullyApplied()) {
                    $status = "<ok>fully applied</>";
                } else {
                    $status = "<bfade>not applied</bfade>";
                }

                $this->puts("Patch <b>{$patch}</> is {$status} at {$this->getFormattedEnv()} env:");
                foreach ($patch->getScripts() as $script) {
                    $this->printScriptStatus($script);
                }
                $this->puts("");
            }

            foreach ($this->input->getOption('script') as $script)
            {
                $script = $this->datapatch->getScript($script);
                $this->puts("Script <b>{$script}</> at {$this->getFormattedEnv()} env:");
                $this->printScriptStatus($script);
                $this->puts("");
            }
        }
        else {
            $this->listNonAppliedPatches();
        }
    }

    protected function pointToSpecificItens()
    {
        return !empty($this->input->getOption('bundle'))  ||
               !empty($this->input->getOption('patch')) ||
               !empty($this->input->getOption('script'));
    }
}
