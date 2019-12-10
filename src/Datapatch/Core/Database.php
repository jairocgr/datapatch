<?php

namespace Datapatch\Core;

use PDOStatement;

interface Database
{
    const SCRIPT_RUNNING      = 'running';
    const SCRIPT_EXECUTED     = 'executed';
    const SCRIPT_ERRORED      = 'error';
    const SCRIPT_NOT_EXECUTED = 'not_executed';

    /**
     * Get the database's name
     *
     * @return string
     */
    function getName();

    /**
     * Get the database's server
     *
     * @return DatabaseServer
     */
    function getServer();

    /**
     * Magical string conversion method
     *
     * @return string
     */
    function __toString();

    /**
     * Check if the pattern matches the database name
     *
     * @param $pattern
     * @return boolean
     */
    function matches($pattern);

    /**
     * Execute stand-alone script or a patched script
     *
     * @param $script string|Script
     */
    function execute($script);

    /**
     * @return string
     */
    function getScriptState($script);

    /**
     * @param Script $script
     */
    function markAsExecuted($script);

    /**
     * Aquire a lock to execute a patched script
     */
    function lock();

    /**
     * Release the lock used to execute a patched script
     */
    function unlock();
}
