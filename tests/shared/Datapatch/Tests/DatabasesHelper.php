<?php


namespace Datapatch\Tests;


use Datapatch\Lang\DataBag;
use PDO;
use PDOStatement;

class DatabasesHelper
{
    /**
     * @var DataBag
     */
    private $config;

    /**
     * @var string
     */
    private $env;

    public function __construct($config, $env)
    {
        $this->config = new DataBag($config);
        $this->env = $env;
    }

    public function queryFirst($server, $database, $sql)
    {
        $res = $this->query($server, $database, $sql);

        return $res->fetchObject();
    }

    /**
     * @return false|PDOStatement
     */
    private function query($server, $database, $sql)
    {
        $conn = $this->connect($server, $database);
        return $conn->query($sql);
    }

    /**
     * @param $server
     * @param $database
     * @return PDO
     */
    private function connect($server, $database)
    {
        $config = $this->config->get("database_servers.{$server}");

        // If has a environment scoped configuration
        if ($config->exists($this->env)) {
            $envConf = $config->get($this->env);
            $config->merge($envConf);
        }

        $dsn = "mysql:host={$config->host};port={$config->port};" .
               "dbname={$database}";

        return new PDO($dsn, $config->user, $config->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]);
    }
}
