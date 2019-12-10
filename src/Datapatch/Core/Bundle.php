<?php

namespace Datapatch\Core;

use Datapatch\Datapatch;

class Bundle
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var Patch[]
     */
    private $patches;

    /**
     * @var Datapatch
     */
    private $datapatch;

    public function __construct($name, $patches = [], $datapatch) {
        $this->name = $name;
        $this->patches = $patches;
        $this->datapatch = $datapatch;
    }

    public function getName() {
        return $this->name;
    }

    public function __toString() {
        return $this->getName();
    }

    public function createFile()
    {
        $this->datapatch->createBundleFile($this);
    }

    /**
     * @return Patch[]
     */
    public function getPatches()
    {
        return $this->patches;
    }
}
