<?php

namespace Datapatch\Tests;

use Datapatch\Core\Shell;
use Datapatch\Datapatch;

class TestHelper
{
    /**
     * @var string
     */
    private $rootDir;

    public function __construct()
    {
        $this->rootDir = realpath(__DIR__ . '/../../../../');
        $this->loadEnv();
    }

    /**
     * @return string
     */
    public function getRootDir()
    {
        return $this->rootDir;
    }

    public function getDatapatch()
    {
        chdir($this->getRootDir());
        return new Datapatch(include "{$this->rootDir}/datapatch.config.php");
    }

    public function getDatabasesHelper($env = 'development')
    {
        return new DatabasesHelper(include "{$this->rootDir}/datapatch.config.php", $env);
    }

    public function getShell()
    {
        return new Shell($this->rootDir);
    }

    private function loadEnv()
    {
        if (class_exists("\Dotenv\Dotenv")) {
            // If has dotenv, tries to load the env file from the current dir
            $dotenv = new \Dotenv\Dotenv($this->rootDir);

            if (file_exists($this->rootDir . '/.env')) {
                $dotenv->load();
            }
        }
    }
}
