<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\DatabaseServer;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Datapatch\Core\Bundle;
use Datapatch\Core\Patch;
use Datapatch\Core\Database;
use Datapatch\Core\ScriptPath;

class DeployBundleCommand extends BaseCommand
{
    use ScriptRunner;

    protected function config()
    {
        $this->setName('deploy')
             ->setDescription('Deploy a bundle')
             ->setHelp('Deploy a bundle and his patches inside a databases')

             ->addArgument(
                'bundle',
                InputArgument::REQUIRED,
                'The choosen bundle (ex: v12.20.12, 2019.11.11)'
             )

             ->addOption(
                'force',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Force execution non-interactive',
                FALSE
             );
    }

    protected function exec()
    {
        $this->console->newLine();

        $bundle = $this->getChoosenBundle();

        $this->deploy($bundle);
    }

    /**
     * @return Bundle[]
     */
    private function getChoosenBundle()
    {
        $bundle = $this->input->getArgument('bundle');
        return $this->datapatch->getBundle($bundle);
    }
}
