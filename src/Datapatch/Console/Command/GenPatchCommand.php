<?php

namespace Datapatch\Console\Command;

use Symfony\Component\Console\Input\InputArgument;

class GenPatchCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('gen:patch')
             ->setDescription('Create a new patch')
             ->setHelp('Create a new patch inside the patch directory')

             ->addArgument(
                'name',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Patch name (eg: "TASK-25768")'
             );
    }

    protected function exec()
    {
        $name = $this->getChoosedName();

        $patch = $this->datapatch->genPatch($name);

        $this->console->puts("Patch <b>{$patch}</b> created!");

        foreach ($patch->getScripts() as $script) {
            $this->console->fade("Creating <b>{$script}</b><fade> script...");
            $script->create();
        }

        $this->console->newLine();
    }

    protected function getChoosedName()
    {
        return implode(' ', $this->input->getArgument('name'));
    }
}
