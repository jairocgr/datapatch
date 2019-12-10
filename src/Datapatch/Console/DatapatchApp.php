<?php

namespace Datapatch\Console;

use Datapatch\Datapatch;

use Symfony\Component\Console\Application;

use Dotenv\Dotenv;

use Datapatch\Console\Command\InitCommand;
use Datapatch\Console\Command\GenPatchCommand;
use Datapatch\Console\Command\GenBundleCommand;
use Datapatch\Console\Command\ApplyCommand;
use Datapatch\Console\Command\ApplyAllCommand;
use Datapatch\Console\Command\StatusCommand;
use Datapatch\Console\Command\MarkExecutedCommand;
use Datapatch\Console\Command\DeployBundleCommand;
use Datapatch\Console\Command\ExecCommand;

class DatapatchApp extends Application
{
    public function __construct()
    {
        parent::__construct('Datapatch', Datapatch::getVersion());

        if (file_exists(getcwd() . '/.env')) {
            // If has dotenv, tries to load the env file from the current dir
            $dotenv = new Dotenv(getcwd());
            $dotenv->safeLoad();
        }

        $this->addCommands([
            new InitCommand(),

            new GenPatchCommand(),
            new GenBundleCommand(),

            new ApplyCommand(),
            new MarkExecutedCommand(),
            new StatusCommand(),
            new ApplyAllCommand(),

            new DeployBundleCommand(),

            new ExecCommand(),
        ]);
    }
}
