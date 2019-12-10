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

class MarkExecutedCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('mark-executed')
             ->setDescription('Mark a script as executed')
             ->setHelp('Mark a script as executed')

             ->addArgument(
                'scripts',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'The choosen scripts (ex: TICKET-3232/changes, TASK-328/new_users)'
             )

             ->addOption(
                'database',
                'd',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Choosen database to be marked (ex: erp*, app21)'
             )

             ->addOption(
                'force',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Force non-interactive execution',
                FALSE
             );
    }

    protected function exec()
    {
        $this->console->newLine();

        foreach ($this->getChosenScripts() as $script)
        {
            $marked = 0;

            foreach ($script->getDatabases() as $database)
            {
                if ($this->markAll() || $this->shoulMark($database))
                {
                    $this->write(
                        "  Marking <b>{$script}</b> as <b>executed</> in <b>{$database}</> ".
                        "at <b>{$database->getServer()}</>... "
                    );

                    $database->markAsExecuted($script);

                    $this->writeln("<success>Done ✓</success>");

                    $marked++;
                }
            }

            if ($marked == 0) throw new RuntimeException(
                "Script \"{$script}\" can not be marked in neither ".
                "of those databases!"
            );

            $this->console->newLine();
        }
    }

    private function markAll()
    {
        return empty($this->input->getOption('database'));
    }

    private function shoulMark($database)
    {
        foreach ($this->input->getOption('database') as $dbname)
        {
            if ($database->matches($dbname)) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * @return Script[]
     */
    private function getChosenScripts()
    {
        $scripts = [];

        foreach ($this->input->getArgument('scripts') as $path) {
            $path = new ScriptPath($path);

            $scripts[] = $this->datapatch->getScript($path);
        }

        return $scripts;
    }
}
