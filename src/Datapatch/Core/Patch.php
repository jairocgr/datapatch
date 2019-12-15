<?php

namespace Datapatch\Core;

use Datapatch\Datapatch;
use RuntimeException;

class Patch
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var Script[]
     */
    private $scripts;

    /**
     * @var Datapatch
     */
    private $datapatch;

    /**
     * @var string[]
     */
    private $after;

    public function __construct($name, $scripts = [], $after = [], $datapatch) {
        $this->name = $name;
        $this->datapatch = $datapatch;
        $this->after = $after;

        $this->setScripts($scripts);
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
        return $this->getNumberOfAppliedScript() == count($this->scripts);
    }

    private function getNumberOfAppliedScript()
    {
        $count = 0;

        foreach ($this->scripts as $script) {
            if ($script->isFullyApplied()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return bool
     */
    public function isErrored()
    {
        foreach ($this->scripts as $script) {
            if ($script->isErrored()) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * @return bool
     */
    public function isUnfinished()
    {
        foreach ($this->scripts as $script) {
            if ($script->isUnfinished()) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * @return bool
     */
    public function isPartiallyApplied()
    {
        $applied = $this->getNumberOfAppliedScript();
        return $applied > 0 && $applied < count($this->scripts);
    }

    private function setScripts($scripts)
    {
        if (empty($scripts)) {
            throw new RuntimeException(
                "No script inside \"$this\" patch! " .
                "A patch must have a least one script."
            );
        }

        foreach ($scripts as $script)
        {
            $script->setPatch($this);
        }

        $this->scripts = $scripts;
    }

    public function getName() {
        return $this->name;
    }

    public function __toString() {
        return $this->getName();
    }

    public function createDir()
    {
        $this->datapatch->createPatchDir($this);
    }

    /**
     * @param $script Script
     */
    public function create($script)
    {
        $this->datapatch->createScript($this, $script);
    }

    /**
     * @return Script[]
     */
    public function getScripts()
    {
        return $this->scripts;
    }

    /**
     * @return string[]
     */
    public function getAfter()
    {
        return $this->after;
    }

    /**
     * @return Script
     */
    public function getScript($name)
    {
        foreach ($this->scripts as $script) {
            if ($script->getName() == $name) {
                return $script;
            }
        }

        throw new RuntimeException(
            "Script not found \"{$name}\" at \"$this\" patch!"
        );
    }
}
