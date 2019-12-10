<?php

namespace Datapatch\Core;

use Datapatch\Datapatch;

class Script
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var Patch
     */
    private $patch;

    /**
     * @var ScriptRunningConfiguration
     */
    private $config;

    public function __construct($name, $config)
    {
        $this->name = $name;
        $this->config = $config;
    }

    /**
     * @return bool
     */
    public function notFullyApplied()
    {
        return ! $this->isFullyApplied();
    }

    /**
     * @return bool
     */
    public function isFullyApplied()
    {
        foreach ($this->getDatabases() as $database)
        {
            $state = $database->getScriptState($this);

            if ($state != Database::SCRIPT_EXECUTED) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * @param $patch Patch
     */
    public function setPatch($patch)
    {
        $this->patch = $patch;
    }

    /**
     * @return Patch
     */
    public function getPatch()
    {
        return $this->patch;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Database[]
     */
    public function getDatabases()
    {
        return $this->config->fetchDatabases();
    }

    /**
     * @return ScriptRunningConfiguration
     */
    public function getRunningConfiguration()
    {
        return $this->config;
    }

    /**
     * @return string[]
     */
    public function getAfter()
    {
        return $this->config->getAfter();
    }

    public function create()
    {
        $this->patch->create($this);
    }

    /**
     * @return ScriptPath
     */
    public function getPath()
    {
        $path = '';

        if (isset($this->patch)) {
            $path = $this->patch->getName();
            $path = $path . '/';
        }

        $path = $path . $this->name;

        return new ScriptPath($path);
    }

    /**
     * @return string
     */
    public function getPhysicalPath()
    {
        return $this->config->getScriptPhysicalPath($this);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return strval($this->getPath());
    }
}
