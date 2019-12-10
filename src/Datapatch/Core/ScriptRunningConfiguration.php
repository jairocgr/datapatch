<?php

namespace Datapatch\Core;

use Datapatch\Lang\DataBag;
use Datapatch\Datapatch;

class ScriptRunningConfiguration {

    /**
     * @var string[]
     */
     private $databases;

    /**
     * @var string[]
     */
     private $after;

     /**
      * @var string[]
      */
     private $servers;

     /**
      * @var boolean
      */
     private $generateScript;

     /**
      * @var Datapatch
      */
     private $datapatch;

     public function __construct($databases, $servers, $generateScript, $after = [], $datapatch) {
         $this->databases = $databases;
         $this->servers = $servers;
         $this->generateScript = $generateScript;
         $this->after = $after;
         $this->datapatch = $datapatch;
     }

     /**
      * @return string
      */
     public function getScriptPhysicalPath($script)
     {
         return $this->datapatch->getScriptPhysicalPath($script);
     }

     /**
      * @return DataBag
      */
     public function toData()
     {
         return new DataBag([
             'databases' => $this->databases,
             'servers' => $this->servers,
             'generate_script' => $this->generateScript,
             'after' => $this->after
         ]);
     }

     /**
      * @return bool
      */
     public function executeAfterSomething()
     {
         return ! $this->anyOrderOfExecution();
     }

     /**
      * @return bool
      */
     public function anyOrderOfExecution()
     {
         return empty($this->after);
     }

     /**
      * @return string[]
      */
     public function getAfter()
     {
         return $this->after;
     }

     /**
      * @return string[]
      */
     public function getDatabases()
     {
         return $this->databases;
     }

     /**
      * @return string[]
      */
     public function getServers()
     {
         return $this->servers;
     }

     /**
      * @return DatabaseServer[]
      */
     private function fetchServers()
     {
         return $this->datapatch->lookupServers($this->servers);
     }

     /**
      * @return Database[]
      */
     public function fetchDatabases()
     {
         $found = [];

         foreach ($this->fetchServers() as $server) {
             foreach ($this->databases as $patterns) {
                 $found = array_merge($found, $server->lookupDatabases($patterns));
             }
         }

         return $found;
     }

     public function mustBeGenerated()
     {
         return $this->generateScript;
     }
}
