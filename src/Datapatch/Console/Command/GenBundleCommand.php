<?php

namespace Datapatch\Console\Command;

use Symfony\Component\Console\Input\InputArgument;

class GenBundleCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('gen:bundle')
             ->setDescription('Create a new bundle')
             ->setHelp('Create a new bundle inside the bundle directory')

             ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Bundle name (eg: "v2.3.5", "2019.10.11")',
                date('Y.m.d')
             );
    }

    protected function exec()
    {
        $name = $this->getChoosedName();

        $bundle = $this->datapatch->genBundle($name);

        $this->console->puts("Bundle <b>{$bundle}</b> created!");

        $this->console->newLine();
    }

    protected function getChoosedName()
    {
        return $this->input->getArgument('name');
    }
}
