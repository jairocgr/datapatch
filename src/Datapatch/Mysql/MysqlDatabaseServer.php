<?php

namespace Datapatch\Mysql;

use Datapatch\Core\Database;
use Datapatch\Core\DatabaseServer;
use Datapatch\Core\DatabaseSnapper;
use Datapatch\Core\EventBus;
use Datapatch\Core\Shell;
use Datapatch\Core\Snap;
use Datapatch\Core\SnapperConfiguration;
use Datapatch\Lang\Asserter;
use Datapatch\Lang\DataBag;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Exception;

class MysqlDatabaseServer implements DatabaseServer
{
    const DATABASE_TIMEOUT = 60 * 60 * 16;

    /**
     * Mysql driver id handle
     */
    const DRIVER_HANDLE = 'mysql';

    /**
     * @var string
     */
    private $name;

    /**
     * @var PDO
     */
    private $conn;

    /**
     * @var Shell
     */
    private $shell;

    /**
     * @var DataBag
     */
    private $data;

    /**
     * @var string
     */
    private $socket;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $user;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $connectionFile;

    /**
     * @var Database[]
     */
    private $databases;

    public function __construct($name, DataBag $data, $shell)
    {
        $this->name = $this->filterName($name);
        $this->data = $data;
        $this->shell = $shell;

        $this->socket = $this->extractSocket($data);
        $this->host = $this->extractHost($data);
        $this->port = $this->extractPort($data);
        $this->user = $this->extractUser($data);
        $this->password = $this->extractPassword($data);


        if (empty($this->socket) && empty($this->host)) {
            throw new InvalidArgumentException(
                "Server \"{$this}\" must have a host or a unix socket!"
            );
        }

        if ($this->viaTcp()) {
            if (empty($this->host)) {
                throw new InvalidArgumentException(
                    "Host for server \"{$this}\" cannot be empty!"
                );
            }

            if (empty($this->port)) {
                throw new InvalidArgumentException(
                    "Port for server \"{$this}\" cannot be empty!"
                );
            }
        }
    }

    /**
     * @return bool
     */
    public function viaTcp()
    {
        return empty($this->socket);
    }

    /**
     * @return string
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    private function extractSocket(DataBag $data)
    {
        return $data->extract('socket', '', function ($value, Asserter $a) {

            if ($a->stringfyable($value) && ("{$value}" === '' || $a->notEmptyString($value))) {
                return strval($value);
            }

            $a->raise("Invalid socket :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    private function extractHost(DataBag $data)
    {
        return $data->extract('host', '', function ($value, Asserter $a) {

            if ($a->stringfyable($value) && ("{$value}" === '' || $a->notEmptyString($value))) {
                return strval($value);
            }

            $a->raise("Invalid host :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    private function extractPort(DataBag $data)
    {
        return $data->extract('port', 3306, function ($value, Asserter $a) {

            if ($a->integerfyable($value)) {
                return intval($value);
            }

            $a->raise("Invalid port :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    private function extractUser(DataBag $data)
    {
        return $data->extract('user', '', function ($value, Asserter $a) {

            if ($a->stringfyable($value) && (empty(strval($value)) || $a->notEmptyString($value))) {
                return strval($value);
            }

            $a->raise("Invalid user :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    private function extractPassword(DataBag $data)
    {
        return $data->extract('password', '', function ($value, Asserter $a) {

            if ($a->stringfyable($value) && (empty(strval($value)) || $a->notEmptyString($value))) {
                return strval($value);
            }

            $a->raise("Invalid password :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    /**
     * @return PDO
     */
    public function openConnection()
    {
        if ($this->viaTcp()) {
            $dsn = "mysql:host={$this->host};port={$this->port};";
        } else {
            $dsn = "mysql:unix_socket={$this->socket};";
        }

        return new PDO($dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_TIMEOUT => static::DATABASE_TIMEOUT,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]);
    }

    private function filterName($name)
    {
        if (is_string($name) && preg_match("/^([a-z][\w\d\-\_\.]*)$/i", $name)) {
            return $name;
        }

        else throw new InvalidArgumentException(
            "Invalid server name \"{$name}\"! Only letters, dashes, underscores, dots, and numbers."
        );
    }

    /**
     * @return string
     */
    public function getName()
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
    public function lookupDatabases($patterns)
    {
        $found = [];

        foreach ($this->wrap($patterns) as $pattern) {
            $found = array_merge($found, $this->findDatabases($pattern));
        }

        return $found;
    }

    /**
     * @return PDO
     */
    private function conn()
    {
        return $this->getConnection();
    }

    /**
     * @return PDO
     */
    private function getConnection()
    {
        if ($this->conn == NULL) {
            $this->conn = $this->openConnection();
        }

        return $this->conn;
    }

    /**
     * @inheritDoc
     */
    public function matches($pattern)
    {
        return fnmatch($pattern, $this->name);
    }

    /**
     * @inheritDoc
     */
    public function execute($script)
    {
        if (file_exists($script)) {} else {
            throw new RuntimeException(
                "Script file \"{$script}\" not found!"
            );
        }

        $this->callMysqlClient(NULL, $script);
    }


    private function invalidDatabasePattern($pattern)
    {
        return (!preg_match("/^[0-9A-Za-z\_\-\.\*]+$/", $pattern));
    }

    private function genTempFilePath()
    {
        return tempnam(sys_get_temp_dir(), '.my.cnf.');
    }

    private function setupConnectionFile()
    {
        register_shutdown_function(function () {
            // connection file clean-up
            @unlink($this->connectionFile);
        });

        $this->connectionFile = $this->genTempFilePath();

        if (($temp = fopen($this->connectionFile, 'w')) === FALSE) {
            throw new RuntimeException(
                "Could not create the connection file " .
                "\"{$this->connectionFile}\"!"
            );
        }

        if ($this->viaTcp()) {
            $connectionParams = "host={$this->host}\n" .
                                "port={$this->port}\n";
        } else {
            $connectionParams = "socket={$this->socket}\n";
        }

        $res = fwrite($temp,
            "[client]\n" .
            $connectionParams .
            "user={$this->user}\n" .
            "password={$this->password}\n"
        );

        if ($res === FALSE) {
            throw new RuntimeException(
                "The connection file \"{$this->connectionFile}\" " .
                "could not be written!"
            );
        }

        if (fflush($temp) === FALSE) {
            throw new RuntimeException(
                "The connection file \"{$this->connectionFile}\" " .
                "could not be written!"
            );
        }

        if (fclose($temp) === FALSE) {
            throw new RuntimeException(
                "The connection file \"{$this->connectionFile}\" " .
                "could not be closed!"
            );
        }
    }

    private function looksLikeAScriptPath($path)
    {
        return $path == 'php://stdin' || is_file($path);
    }

    private function openScriptFile($filepath)
    {
        if ($filepath == "php://stdin") {
            return $this->fopen($filepath, 'r');
        } elseif (file_exists($filepath)) {
            return $this->fopen($filepath, 'r');
        } else {
            throw new InvalidArgumentException(
                "File \"{$filepath}\" does not exists!"
            );
        }
    }

    private function fopen($filepath, $mode)
    {
        if ($handle = fopen($filepath, $mode)) {
            return $handle;
        } else {
            throw new RuntimeException("File \"{$filepath}\" could not be opened!");
        }
    }

    /**
     * @param $database string|null|DatabaseServer
     * @param $input string|resource
     * @return string
     */
    public function callMysqlClient($database, $input, $compressedInput = FALSE)
    {
        if (is_resource($input)) {
            // Input is ready
            $input = $input;
        } elseif ($this->looksLikeAScriptPath($input)) {
            $input = $this->openScriptFile($input);
        } else {
            $input = strval($input);
        }

        if (!$this->commandExists('mysql')) {
            throw new RuntimeException(
                "Command \"mysql\" not found"
            );
        }

        if (!$this->commandExists('cat')) {
            throw new RuntimeException(
                "Command \"cat\" not found"
            );
        }

        if (!$this->commandExists('gunzip')) {
            throw new RuntimeException(
                "Command \"cat\" not found"
            );
        }

        if ($this->connectionFileNotCreated()) {
            $this->setupConnectionFile();
        }

        if ($compressedInput) {
            $cmd = "gunzip | ";
        } else {
            $cmd = "cat -- | ";
        }

        $cmd .= "mysql --defaults-file={$this->connectionFile} -n --table {$database}";

        return $this->shell->run($cmd, $input);
    }

    private function connectionFileExists()
    {
        return file_exists($this->connectionFile);
    }

    private function connectionFileNotCreated()
    {
        return ! $this->connectionFileExists();
    }

    private function commandExists($command)
    {
        $return_var = 1;
        $stdout = [];

        exec(" ( command -v {$command} > /dev/null 2>&1 ) 2>&1 ", $stdout, $return_var);

        return $return_var === 0;
    }

    private function getSchemata($database)
    {
        return $this->fetch("
            SELECT * FROM information_schema.SCHEMATA S
            WHERE schema_name = '{$database}'
        ");
    }

    private function fetch($query)
    {
        return $this->query($query)->fetch(PDO::FETCH_OBJ);
    }

    private function query($query)
    {
        return $this->conn()->query($query);
    }

    /**
     * @return Database[]
     */
    private function getDatabases()
    {
        if ($this->databases == NULL) {
            $this->databases = $this->fetchAllDatabases();
        }

        return $this->databases;
    }

    /**
     * @return Database[]
     */
    private function fetchAllDatabases()
    {
        $databases = [];

        foreach ($this->query("SHOW DATABASES") as $row) {
            $databases[$row->Database] = new MysqlDatabase($row->Database, $this);
        }

        return $databases;
    }

    /**
     * @param $pattern string
     * @return Database[]
     */
    private function findDatabases($pattern)
    {
        if ($this->invalidDatabasePattern($pattern)) {
            throw new InvalidArgumentException(
                "Invalid database identifier \"{$pattern}\"!"
            );
        }

        $matches = [];

        foreach ($this->getDatabases() as $database)
        {
            if ($database->matches($pattern)) {
                $matches[] = $database;
            }
        }

        if (empty($matches)) {
            throw new RuntimeException(
                "Database \"{$pattern}\" not found on \"{$this}\" server!"
            );
        }

        return $matches;
    }

    /**
     * @return string[]
     */
    private function wrap($patterns)
    {
        return is_array($patterns) ? $patterns : [ $patterns ];
    }
}
