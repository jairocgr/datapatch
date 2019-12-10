<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\DatabaseServer;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Datapatch\Core\Patch;
use Datapatch\Core\Database;
use Datapatch\Core\ScriptPath;

use RuntimeException;

class ApplyCommand extends BaseCommand
{
    use ScriptRunner;

    protected function config()
    {
        $this->setName('apply')
             ->setDescription('Apply bundles, paches and scripts')
             ->setHelp('Apply paches inside databases')

             ->addArgument(
                'item',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'The choosen bundle/patches/scripts (ex: v1.2.39, TICKET-3232, TASK-328/new_users)'
             )

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

        foreach ($this->getChosenItens() as $item)
        {
            if ($this->datapatch->hasBundle($item))
            {
                $bundle = $this->datapatch->getBundle($item);

                $this->deploy($bundle);
            }
            else
            {
                $path = new ScriptPath($item);

                if ($path->pointToPatch()) {
                    $patch = $path->getPatch();

                    if (!$this->datapatch->hasPatch($patch)) {
                        throw new RuntimeException(
                            "No bundle/patch \"{$patch}\" founded!"
                        );
                    }

                    $patch = $this->datapatch->getPatch($patch);

                    $this->apply($patch);
                }

                else {
                    $patch = $path->getPatch();
                    $script = $path->getScript();

                    $patch = $this->datapatch->getPatch($patch);
                    $script = $patch->getScript($script);

                    $this->puts("Applying script <b>{$script}</b>");
                    $this->execScript($script);
                }

                $this->puts("");
            }
        }
    }

    /**
     * @return ScriptPath[]
     */
    private function getChosenItens()
    {
        return $this->input->getArgument('item');
    }
}
