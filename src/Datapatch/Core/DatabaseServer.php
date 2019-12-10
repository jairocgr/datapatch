<?php

namespace Datapatch\Core;

use Datapatch\Lang\DataBag;
use PDOStatement;

interface DatabaseServer
{
    /**
     * Get the server's name
     *
     * @return string
     */
    function getName();

    /**
     * Magical string conversion method
     *
     * @return string
     */
    function __toString();

    /**
     * @param $patterns string|string[]
     * @return Database[]
     */
    function lookupDatabases($patterns);

    /**
     * Execute stand-alone script inside server
     *
     * @param $script string
     */
    function execute($script);

    /**
     * Check if the pattern matches the server name
     *
     * @param $pattern
     * @return boolean
     */
    function matches($pattern);
}
