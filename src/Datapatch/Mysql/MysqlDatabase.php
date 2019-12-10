<?php

namespace Datapatch\Mysql;

use Datapatch\Core\Database;
use Datapatch\Core\DatabaseServer;
use Datapatch\Core\Snap;
use Datapatch\Core\SnapLocation;
use Datapatch\Core\SnapperConfiguration;
use Datapatch\Core\Script;
use PDO;
use RuntimeException;

class MysqlDatabase implements Database
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var MysqlDatabaseServer
     */
    private $server;

    /**
     * @var PDO
     */
    private $conn;

    public function __construct($name, MysqlDatabaseServer $server)
    {
        $this->name = $name;
        $this->server = $server;
    }

    /**
     * @inheritDoc
     */
    public function markAsExecuted($script)
    {
        $this->mark($script, Database::SCRIPT_EXECUTED);
    }

    /**
     * @inheritDoc
     */
    public function getScriptState($script)
    {
        $row = $this->fetch("
            select status from __datapatch
            where patch  = '{$script->getPatch()}' and
                  script = '{$script->getName()}'
        ");

        return (empty($row))
            ? Database::SCRIPT_NOT_EXECUTED
            : $row->status;
    }

    private function exec($command)
    {
        return $this->conn()->exec($command);
    }

    private function query($sql)
    {
        return $this->conn()->query($sql);
    }

    private function fetch($query)
    {
        return $this->query($query)->fetch(PDO::FETCH_OBJ);
    }

    /**
     * @inheritDoc
     */
    public function execute($script)
    {
        $start = microtime(true);

        if (is_string($script)) {
            $this->executeStandAloneScript($script);
        } else {
            $this->executePatchedScript($script);
        }

        $end = microtime(true);

        return $end - $start;
    }

    /**
     * @param $script Script
     */
    private function executePatchedScript($script)
    {
        try {

            $done = FALSE;

            $this->mark($script, Database::SCRIPT_RUNNING);

            $file = $script->getPhysicalPath();
            $this->run($file);

            $this->mark($script, Database::SCRIPT_EXECUTED);

            $done = TRUE;


        } finally {
            if (!$done) {
                try {
                    @$this->mark($script, Database::SCRIPT_ERRORED);
                } catch (Exception $ex) {
                    // Silently mark as errored
                }
            }
        }
    }

    private function executeStandAloneScript($script)
    {
        if (file_exists($script)) {} else {
            throw new RuntimeException(
                "Script file \"{$script}\" not found!"
            );
        }

        $this->run($script);
    }

    private function mark($script, $nextStatus)
    {
        $status = $this->getScriptState($script);

        if ($status == Database::SCRIPT_NOT_EXECUTED) {
            $this->exec("
                insert into __datapatch (patch, script, status)
                values (
                    '{$script->getPatch()}',
                    '{$script->getName()}',
                    '{$nextStatus}'
                )
            ");
        } else {
            $this->exec("
                update __datapatch set status = '{$nextStatus}'
                where patch  = '{$script->getPatch()}' and
                      script = '{$script->getName()}'
            ");
        }
    }

    /**
     * @inheritDoc
     */
    function getServer()
    {
        return $this->server;
    }

    /**
     * @inheritDoc
     */
    function getName()
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->getName();
    }

    /**
     * @inheritDoc
     */
    public function lock()
    {
        $this->exec("LOCK TABLES __datapatch WRITE");
        $this->updateRunningStates();
    }

    private function updateRunningStates()
    {
        // All script running are assumed to be errored
        $this->exec("
            update __datapatch set status = 'error'
            where status = 'running'
        ");
    }

    /**
     * @inheritDoc
     */
    public function unlock()
    {
        $this->exec("UNLOCK TABLES");
    }

    private function run($command)
    {
        $this->server->callMysqlClient($this, $command);
    }

    /**
     * @inheritDoc
     */
    function matches($pattern)
    {
        return fnmatch($pattern, $this->name);
    }

    /**
     * @return PDO
     */
    private function conn()
    {
        if ($this->conn == NULL) {
            $this->conn = $this->buildConnection();
            $this->bootstrapDatabaseMetadata();
        }

        return $this->conn;
    }

    /**
     * @return PDO
     */
    private function buildConnection()
    {
        $conn = $this->server->openConnection();
        $conn->exec("USE `{$this}`");

        return $conn;
    }

    /**
     * @param $conn PDO
     */
    private function bootstrapDatabaseMetadata()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS __datapatch (
              id integer not null auto_increment primary key,
              patch varchar(128) not null,
              script varchar(128) not null,
              status varchar(32) not null,

              CONSTRAINT __datapatch_script UNIQUE (patch, script)
            );
        ");
    }
}
