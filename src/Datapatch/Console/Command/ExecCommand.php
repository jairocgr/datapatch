<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\DatabaseServer;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Datapatch\Core\Patch;
use Datapatch\Core\Database;
use Datapatch\Core\ScriptPath;

class ExecCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('exec')
             ->setDescription('Execute a script inside a database')
             ->setHelp('Execute a script inside a database')

             ->addArgument(
                'scripts',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Path to script (ex: path/to/script.sql)'
             )

             ->addOption(
                'database',
                'd',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Choosen database (ex: erp*, app21)'
             )

             ->addOption(
                'server',
                'r',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Choosen server'
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
            if ($this->databaseWasChosen())
            {
                foreach ($this->getDatabases() as $database)
                {
                    $this->write(
                        "  Running <b>{$script}</b> in <b>{$database}</> ".
                        "at <b>{$database->getServer()}</>... "
                    );

                    $duration = $database->execute($script);

                    $this->writeln("<success>Done ✓</success> <fade>({$this->format($duration)})</fade>");
                }

                $this->console->newLine();
            }
            else
            {
                foreach ($this->getServers() as $server)
                {
                    $this->write("  Running <b>{$script}</b> in <b>{$server}</>... ");

                    $duration = $server->execute($script);

                    $this->writeln("<success>Done ✓</success> <fade>({$this->format($duration)})</fade>");
                }

                $this->console->newLine();
            }
        }
    }

    protected function databaseWasChosen()
    {
        return ! empty($this->input->getOption('database'));
    }

    protected function getServers()
    {
        $servers = $this->input->getOption('server');
        return $this->datapatch->lookupServers($servers);
    }

    protected function getDatabases()
    {
        $databases = $this->input->getOption('database');
        $servers = $this->input->getOption('server');


        return $this->datapatch->lookupDatabases($databases, $servers);
    }

    /**
     * @return string[]
     */
    private function getChosenScripts()
    {
        $scripts = [];

        foreach ($this->input->getArgument('scripts') as $path) {
            $scripts[] = $path;
        }

        return $scripts;
    }
}
