<?php

namespace Datapatch;

use Closure;
use Datapatch\Core\DatabaseServer;
use Datapatch\Core\Shell;
use Datapatch\Core\Patch;
use Datapatch\Core\Bundle;
use Datapatch\Core\Script;
use Datapatch\Core\ScriptPath;
use Datapatch\Lang\Asserter;
use Datapatch\Lang\DataBag;
use Datapatch\Lang\FileReader;
use Datapatch\Mysql\MysqlDatabaseServer;
use InvalidArgumentException;
use RuntimeException;
use Exception;
use Datapatch\Core\ScriptRunningConfiguration;
use SplDoublyLinkedList;

class Datapatch
{
    const OUTPUT = 'output';

    public static function getVersion()
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'));

        return $composer->version;
    }

    public static function getPackageName()
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'));

        return $composer->name;
    }

    public static function getPackageUrl()
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'));

        return $composer->homepage;
    }

    public static function initDirectory()
    {
        @mkdir("db/patches", 0755, TRUE);
        @mkdir("db/bundles", 0755, TRUE);

        if (!file_exists("datapatch.config.php")) {

            $template = realpath(__DIR__ . "/../../template/datapatch.config.php");
            $dst = "datapatch.config.php";

            if (!copy($template, $dst)) {
                throw new RuntimeException("Could not create configuration file");
            }
        }
    }

    const RUNIN_REGEX = "/^\-\- \@runin (\w[\w\_\-\.\*\[\]\,\ ]+) at ([\w\_\-\.]+)$/i";
    const RUNAS_REGEX = "/^\-\- \@runas ([\w\_\-\.]+)$/i";

    /**
     * @var string
     */
    private $dir;

    /**
     * @var DataBag
     */
    private $config;

    /**
     * @var Shell
     */
    private $shell;

    /**
     * @var DatabaseServer[]
     */
    private $servers;

    /**
     * @var ScriptRunningConfiguration[]
     */
    private $configs;

    /**
     * @var Patch[]
     */
    private $patches;

    /**
     * @var Bundle[]
     */
    private $bundles;

    public function __construct(array $config)
    {
        $this->shell = new Shell();
        $this->config = $this->wrap($config);

        $this->dir = $this->config->extract('patches', 'db/patches', function ($dir) {
            if (is_dir($dir)) {} else {
                throw new InvalidArgumentException(
                    "Patches directory \"{$dir}\" not found!"
                );
            }

            return $dir;
        });

        $this->bundleDir = $this->config->extract('bundles', 'db/bundles', function ($dir) {
            if (is_dir($dir)) {} else {
                throw new InvalidArgumentException(
                    "Bundles directory \"{$dir}\" not found!"
                );
            }

            return $dir;
        });

        $this->env = $this->config->get('env', 'development');

        $this->parseDatabaseServers();
        $this->parseRunningConfiguration();

        $this->loadPatches();
        $this->loadBundles();
    }

    /**
     * @return Patch[]
     */
    public function getNonAppliedPatches()
    {
        $patches = [];

        foreach ($this->patches  as $patch) {
            if ($patch->notFullyApplied()) {
                $patches[] = $patch;
            }
        }

        return $patches;
    }

    /**
     * @return string
     */
    public function getEnv()
    {
        return $this->config->get('env', 'development');
    }

    /**
     * @return bool
     */
    public function runningInProduction()
    {
        $env = $this->getEnv();
        $env = strtolower(trim($env));
        return in_array($env, [ 'production', 'live', 'prod' ]);
    }

    /**
     * @param $patch Patch
     * @param $script Script
     */
    public function createScript($patch, $script)
    {
        $conf = $script->getRunningConfiguration();

        $databases = implode(", ", $conf->getDatabases());
        $servers = implode(", ", $conf->getServers());

        if ($conf->executeAfterSomething()) {
            $after = $conf->getAfter();
            $after = implode(", ", $after);
            $after = "-- @after {$after}\n";
        } else {
            $after = "";
        }

        $content = "--\n" .
                   "-- {$script->getPath()}\n" .
                   "--\n" .
                   "-- @databases {$databases}\n" .
                   "-- @servers {$servers}\n" .
                   $after .
                   "--\n\n";



        $path = $this->getScriptPhysicalPath($script);
        $this->createFile($path, $content);
    }

    public function getScriptPhysicalPath($script)
    {
        return "{$this->dir}/{$script->getPath()}";
    }

    private function loadPatches()
    {
        $patches = [];

        foreach (glob("{$this->dir}/*", GLOB_ONLYDIR) as $path) {
            $name = basename($path);
            $patches[$name] = $this->loadPatch($name, $path);
        }

        $this->patches = $this->sortPatches($patches);
    }

    private function loadBundles()
    {
        $this->bundles = [];

        foreach (glob("{$this->bundleDir}/*.yml") as $path) {
            $name = basename($path, '.yml');
            $this->bundles[$name] = $this->loadBundle($name);
        }
    }

    private function loadBundle($name)
    {
        $patches = $this->loadBundlePatches($name);

        return new Bundle($name, $patches, $this);
    }

    private function loadBundlePatches($bundle)
    {
        $path = $this->bundlePath($bundle);

        $patches = [];

        foreach ($this->readLine($path) as $line)
        {
            if (preg_match("/^\ *\#.*$/", $line)) {} else {
                // If line is not a comment, then is empty
                // or has a patch name
                $line = trim($line);

                if (!empty($line)) {
                    try {
                        $patch = $this->getPatch($line);

                        if (in_array($patch, $patches)) {
                            throw new RuntimeException(
                                "Repeated patch \"$patch\" inside \"$bundle\" bundle!"
                            );
                        }

                        $patches[] = $patch;
                    } catch (Exception $ex) {
                        if (preg_match('/.*not found.*/', $ex->getMessage())) {
                            throw new RuntimeException(
                                "Patch \"$line\" listed in bundle " .
                                "\"$bundle\" was not found!"
                            );
                        } else {
                            throw $ex;
                        }
                    }
                }
            }
        }

        if (count($patches) < 1) {
            throw new RuntimeException(
                "Bundle \"$bundle\" doesn't have any patch!"
            );
        }

        return $patches;
    }

    private function loadPatch($name, $path)
    {
        return new Patch($name, $this->loadScripts($path), $this->loadAfterFile($path), $this);
    }

    private function loadAfterFile($path)
    {
        $afterFile = "{$path}/after";

        if (file_exists($afterFile)) {

            $after = [];

            foreach ($this->readLine($afterFile) as $line) {
                $patch = trim($line);

                if (!empty($patch)) {

                    if ($this->isHashedComment($patch)) {
                        // Nothing to do
                    } elseif ($this->hasPatch($patch)) {
                        $after[] = $patch;
                    } else {
                        throw new RuntimeException(
                            "Patch \"{$patch}\" not found at \"{$afterFile}\"!"
                        );
                    }
                }
            }

            return $after;
        }

        else return [];
    }

    private function loadScripts($path)
    {
        $scripts = [];

        foreach(glob("{$path}/*.sql") as $filepath) {
            $scripts[] = $this->loadScript($filepath);
        }

        return $this->sort($scripts);
    }

    private function loadScript($path)
    {
        $name = basename($path, '.sql');

        return new Script($name, $this->loadScriptConfiguration($path));
    }

    private function loadScriptConfiguration($path)
    {
        $scriptName = basename($path, '.sql');

        if ($this->existConfig($scriptName)) {
            // If is a pre-configured script than use it
            return $this->getConfig($scriptName);
        }

        $tags = $this->readConfigTagsFrom($path);


        // The @runas tag urges datapatch to apply the same running conf
        // that the referenced pre-configurated script
        if ($tags->exists('runas')) {
            $baseConfig = $tags->get('runas');
            $baseConfig = trim($baseConfig);
            $baseConfig = basename($baseConfig, '.sql');

            if ($this->existConfig($baseConfig)) {
                $baseConfig = $this->getConfig($baseConfig);
                $baseConfig = $baseConfig->toData();
                $baseConfig->merge($tags);

                $tags = $baseConfig;
            } else {
                throw new RuntimeException(
                    "@runas configuration \"{$baseConfig}\" not found from \"{$path}\"!"
                );
            }
        }

        $servers = $tags->extract('servers', function ($servers, Asserter $a) use ($path) {

            if (empty($servers)) {
                $a->raise("Missing database servers on \"{$path}\"!");
            }

            if (is_string($servers)) {
                return [ $servers ];
            } elseif ($a->collectionOf($servers, 'string')) {
                return $servers;
            } else {
                $a->raise("Invalid servers on \"{$path}\"!");
            }
        });

        $databases = $tags->extract('databases', function ($databases, Asserter $a) use ($path) {

            if (empty($databases)) {
                $a->raise("No databases on \"{$path}\"!");
            }

            if (is_string($databases)) {
                return [ $databases ];
            } elseif ($a->collectionOf($databases, 'string')) {
                return $databases;
            } else {
                $a->raise("Invalid databases on \"{$path}\"!");
            }
        });

        $after = $tags->extract('after', [], function ($after, Asserter $a) use ($path) {

            if (is_string($after)) {
                return [ $after ];
            } elseif ($a->collectionOf($after, 'string')) {

                foreach ($after as $i => $name) {
                    $name = basename($name, '.sql');
                    $after[$i] = $name;
                }

                return $after;
            } else {
                $a->raise("Invalid after on \"{$path}\"!");
            }
        });

        return new ScriptRunningConfiguration(
            $databases,
            $servers,
            FALSE,
            $after,
            $this
        );
    }

    private function existConfig($name)
    {
        return isset($this->configs[$name]);
    }

    private function readConfigTagsFrom($path)
    {
        $data = new DataBag();

        foreach ($this->readTags($path) as $tag => $value)
        {
            switch ($tag) {
                case 'databases':
                case 'servers':
                case 'after':
                    if (is_string($value)) {
                        $values = explode(',', $value);
                        $values = array_map('trim', $values);
                        $data->set($tag, $values);
                        break;
                    }

                default:
                    $data->set($tag, $value);
                    break;
            }
        }

        return $data;
    }

    private function readTags($path)
    {
        $tags = [];

        foreach ($this->readLine($path) as $line)
        {
            if ($this->isComment($line))
            {
                if ($this->isTagLine($line))
                {
                    list($tag, $value) = $this->breakTagLine($line);
                    $tags[$tag] = $value;
                }
            } else {
                 // Reach end of comment block
                break;
            }
        }

        return $tags;
    }

    private function isTagLine($line)
    {
        return boolval(preg_match("/^-- +@[\w]/i", $line));
    }

    private function breakTagLine($line)
    {
        $matches = [];

        preg_match("/^-- +\@([\w\-\.\_]+)( (.+))?$/i", trim($line), $matches);

        $tag = $matches[1];
        $value = isset($matches[3]) ? $matches[3] : TRUE;

        return [ $tag, $value ];
    }

    /**
     * @param $bundle Bundle
     */
    public function createBundleFile($bundle)
    {
        $path = $this->bundlePath($bundle);

        $success = file_put_contents($path,
           "#\n" .
           "# Bundle {$bundle}\n" .
           "#\n" .
           "# List the patches that make up this bundle\n" .
           "#\n"
        );

        if (!$success) {
            throw new RuntimeException(
                "Can not create \"{$bundle}\" file!"
            );
        }
    }

    private function createFile($path, $content) {
        $success = file_put_contents($path, $content);

        if (!$success) {
            throw new RuntimeException(
                "Can not create \"{$path}\" file!"
            );
        }
    }

    public function genPatch($name = NULL)
    {
        $name = $this->generateName($name);

        if ($this->hasPatch($name)) {
            throw new RuntimeException(
                "Patch \"{$name}\" already exists!"
            );
        }

        $scripts = $this->getGeneratedScripts();

        $patch = new Patch($name, $scripts, [], $this);

        $patch->createDir();

        return $patch;
    }

    public function genBundle($name = NULL)
    {
        $name = $this->generateBundleName($name);

        if ($this->hasBundle($name)) {
            throw new RuntimeException(
                "Bundle \"{$name}\" already exists!"
            );
        }

        $bundle = new Bundle($name, [], $this);

        $bundle->createFile();

        return $bundle;
    }

    /**
     * @return Bundle
     */
    public function getBundle($name)
    {
        $name = strval($name);

        if ($this->hasBundle($name)) {
            return $this->bundles[$name];
        }

        else throw new InvalidArgumentException(
            "Bundle \"{$name}\" not found!"
        );
    }

    /**
     * @return Patch
     */
    public function getPatch($name)
    {
        $name = strval($name);

        if ($this->hasPatch($name)) {
            return $this->patches[$name];
        }

        else throw new InvalidArgumentException(
            "Patch \"{$name}\" not found!"
        );
    }


    /**
     * Sort for the correct execution order
     *
     * @param $patches Patch[]
     * @return Patch[]
     */
     private function sortPatches($patches)
     {
         if (empty($patches)) {
             // Nothing to sort
             return $patches;
         }

         // Alphabetical order
         usort($patches, function ($a, $b) {
             return strcmp($a, $b);
         });

         // Script hastable for further referencing
         $table = [];

         foreach ($patches as $patch) {
             $name = $patch->getName();
             $table[$name] = $patch;
         }

         $sorter = new \MJS\TopSort\Implementations\StringSort();

         foreach ($patches as $patch) {
             $name = $patch->getName();

             $dependencies = [];

             foreach ($patch->getAfter() as $dependency) {
                 // Check if dependency exists inside the patch collection
                 if (isset($table[$dependency])) {
                     $dependencies[] = $dependency;
                 }
             }

             $sorter->add($name, $dependencies);
         }

         $orded = $sorter->sort();
         $result = [];

         foreach($orded as $patchName) {
             $result[$patchName] = $table[$patchName];
         }

         return $result;
     }

    /**
     * Sort for the correct execution order
     *
     * @param $scripts Script[]
     * @return Script[]
     */
     private function sort($scripts)
     {
         if (empty($scripts)) {
             // Nothing to sort
             return $scripts;
         }

         // Alphabetical order
         usort($scripts, function ($a, $b) {
             return strcmp($a, $b);
         });

         // Script hastable for further referencing
         $table = [];

         foreach ($scripts as $script) {
             $name = $script->getName();
             $table[$name] = $script;
         }

         $sorter = new \MJS\TopSort\Implementations\StringSort();

         foreach ($scripts as $script) {
             $name = $script->getName();

             $dependencies = [];

             foreach ($script->getAfter() as $dependency) {
                 // Check if dependency exists inside the script collection
                 if (isset($table[$dependency])) {
                     $dependencies[] = $dependency;
                 }
             }

             $sorter->add($name, $dependencies);
         }

         $orded = $sorter->sort();
         $result = [];

         foreach($orded as $scriptName) {
             $result[] = $table[$scriptName];
         }

         return $result;
     }

    /**
     * @return ScriptRunningConfiguration
     */
    public function getRunningConfiguration(Script $script)
    {
        $name = $script->getName();

        if (isset($this->configs[$name])) {
            return $this->configs[$name];
        }

        $meta = $this->parseMetaData($script);

        var_dump($meta);

        $line = $this->loadConfigLine($script);

        if (preg_match(static::RUNIN_REGEX, $line)) {
            $matches = [];

            preg_match(static::RUNIN_REGEX, $line, $matches);

            $databases = explode(',', $matches[1]);
            $databases = array_map('trim', $databases);
            $server = $this->getServer($matches[2]);

            return new ScriptRunningConfiguration($databases, $server, FALSE);
        } else {
            $matches = [];
            preg_match(static::RUNAS_REGEX, $line, $matches);
            return $this->getConfig($matches[1]);
        }
    }

    /**
     * @return ScriptRunningConfiguration
     */
    private function getConfig($name)
    {
        if (!$this->existConfig($name)) {
            throw new RuntimeException("Running configuration \"{$name}\" not found!");
        }

        return $this->configs[$name];
    }

    private function loadConfigLine($filepath)
    {
        foreach ($this->readLine($filepath) as $line)
        {
            if ($this->isComment($line)) {
                if ($this->isConfigLine($line)) {
                     return $line;
                }
            } else {
                throw new RuntimeException(
                    "Can not get running configurations from \"{$filepath}\"!"
                );
            }
        }
    }

    private function parseMetaData($script)
    {
        $meta = [];

        foreach ($this->readLine($script) as $line)
        {
            if ($this->isComment($line)) {
                if ($this->isMarkedLine($line)) {

                    $matches = [];

                    preg_match("/^-- (\@[\w]+) ?(.+)?$/i", $line, $matches);

                    $key = $matches[1];
                    $value = isset($matches[2]) ? $matches[2] : TRUE;

                    $meta[$key] = $value;
                }
            } else {
                break;
            }
        }

        return $meta;
    }

    private function isMarkedLine($line)
    {
        return preg_match("/^-- (\@[\w]+).+$/i", $line);
    }

    private function readLine($filepath)
    {
        return new FileReader($filepath);
    }

    private function isComment($line)
    {
        return substr($line, 0, 2) === "--";
    }

    private function isHashedComment($line)
    {
        return preg_match("/^\ *\#.*$/", $line) ? TRUE : FALSE;
    }

    private function isConfigLine($line)
    {
        return preg_match(static::RUNAS_REGEX, $line) ||
               preg_match(static::RUNIN_REGEX, $line);
    }

    /**
     * @return bool
     */
    public function hasPatch($name)
    {
        return $this->patchDirExists($name);
    }

    private function patchDirExists($name)
    {
        return is_dir($this->patchPath($name));
    }

    /**
     * @return bool
     */
    public function hasBundle($name)
    {
        return $this->bundleFileExists($name);
    }

    private function bundleFileExists($name)
    {
        return file_exists($this->bundlePath($name));
    }

    private function bundlePath($name)
    {
        return "{$this->bundleDir}/{$name}.yml";
    }

    private function hasScript($name)
    {
        return count($this->getScriptFiles($name)) > 0;
    }

    private function getScriptFiles($name)
    {
        return glob("{$this->patchPath($name)}/*.sql");
    }

    private function patchPath($name)
    {
        return "{$this->dir}/{$name}";
    }

    public function createPatchDir($patch)
    {
        $success = mkdir("{$this->dir}/{$patch}");

        if (!$success) {
            throw new RuntimeException(
                "Can not create \"{$patch}\" directory!"
            );
        }
    }

    private function generateName($name = NULL)
    {
        $name = $this->namefy($name);

        if (empty($name)) {
            $date = date('Ymdhis');
            return $date;
        } else {
            return $name;
        }
    }

    private function generateBundleName($name = NULL)
    {
        $name = $this->namefy($name);

        if (empty($name)) {
            return date('Y.m.d');
        } else {
            return $name;
        }
    }

    private function namefy($name)
    {
        // replace non letter, digits, underscore and dots by "-"
        $name = preg_replace('~[^\pL\d\_\.]+~u', '-', $name);

        // transliterate
        $name = iconv('utf-8', 'us-ascii//TRANSLIT', $name);

        // remove unwanted characters
        $name = preg_replace('~[^-\w\.]+~', '', $name);

        // trim
        $name = trim($name, '-');

        // remove duplicate -
        $name = preg_replace('~-+~', '-', $name);

        return $name;
    }

    private function parseDatabaseServers()
    {
        $servers = $this->config->extract('database_servers', function ($value, Asserter $a) {

            if (empty($value)) $a->raise("Require at least one database server!");

            if ($a->transversable($value) && $a->collectionOf($value, DataBag::class)) {
                return $value;
            }

            $a->raise("Invalid database_servers!");
        });

        $this->servers = [];

        foreach ($servers as $server => $config) {
            $this->servers[$server] = $this->buildDatabaseServer($server, $config);
        }
    }

    private function parseRunningConfiguration()
    {
        $scripts = $this->config->extract('scripts', function ($value, Asserter $a) {

            if (empty($value)) {
                return [];
            }

            if ($a->transversable($value)) {
                return $value;
            }

            $a->raise("Invalid scripts!");
        });

        $this->configs = [];

        foreach ($scripts as $name => $config) {
            $this->configs[$name] = $this->buildRunningConfig($name, $config);
        }
    }

    /**
     * @param $databases string[]
     * @param $servers string[]
     * @return Database[]
     */
    public function lookupDatabases($databases, $servers)
    {
        $found = [];

        foreach ($this->lookupServers($servers) as $server) {
            foreach ($databases as $patterns) {
                $res = $server->lookupDatabases($patterns);
                $found = array_merge($found, $res);
            }
        }

        return $found;
    }

    /**
     * @param $path ScriptPath
     * @return Script[]
     */
    public function getScript($path)
    {
        if ($path->pointToScript()) {
            $patch = $path->getPatch();
            $script = $path->getScript();

            $patch = $this->getPatch($patch);

            return $patch->getScript($script);
        }

        throw new RuntimeException("\"{$path}\" is not a script identifier!");
    }

    /**
     * @param $patterns string|string[]
     * @return DatabaseServer[]
     */
    public function lookupServers($patterns)
    {
        $found = [];

        foreach ($this->wrapPatterns($patterns) as $pattern) {
            // Array union that combine found servers with new found servers
            $found = array_merge($found, $this->findServers($pattern));
        }

        return $found;
    }

    private function invalidServerNamePattern($pattern)
    {
        return (!preg_match("/^[0-9A-Za-z\_\-\.\*]+$/", $pattern));
    }

    /**
     * @param $pattern string
     * @return DatabaseServer[]
     */
    private function findServers($pattern)
    {
        if ($this->invalidServerNamePattern($pattern)) {
            throw new InvalidArgumentException(
                "Invalid server identifier \"{$pattern}\"!"
            );
        }

        $matches = [];

        foreach ($this->servers as $server)
        {
            if ($server->matches($pattern)) {
                $matches[] = $server;
            }
        }

        if (empty($matches)) {
            throw new RuntimeException(
                "Database server \"{$pattern}\" not found!"
            );
        }

        return $matches;
    }

    /**
     * @return string[]
     */
    private function wrapPatterns($patterns)
    {
        return is_array($patterns) ? $patterns : [ $patterns ];
    }

    private function buildRunningConfig($scriptName, $config)
    {
        if (is_string($config)) {

            $serverName = $config;

            $databases = [ $scriptName ];
            $servers = [ $serverName ];

            return new ScriptRunningConfiguration(
                $databases,
                $servers,
                TRUE,
                [],
                $this );
        } elseif ($config instanceof DataBag) {

            $databases = $config->get('databases', $scriptName);
            $databases = is_string($databases) ? [ $databases ] : $databases;

            $servers = $config->get('servers');
            $servers = is_string($servers) ? [ $servers ] : $servers;

            $generate = $config->get('generate_script', TRUE);

            $after = $config->get('after', []);
            $after = is_string($after) ? [ $after ] : $after;

            return new ScriptRunningConfiguration(
                $databases,
                $servers,
                $generate,
                $after,
                $this);
        } else {
            throw new RuntimeException(
                "Invalid script configuration \"{$scriptName}\"!"
            );
        }
    }

    private function wrap(array $data)
    {
        if (empty($data)) {
            throw new RuntimeException("Configuration array cannot be empty!");
        }

        return new DataBag($data);
    }

    private function buildDatabaseServer($name, DataBag $config)
    {
        $driver = $config->extract('driver', function ($value, Asserter $a) use ($name) {

            if (empty($value)) $a->raise("Require driver on :db server!", [
                'db' => $name
            ]);

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid driver :value on :db server!", [
                'value' => $value,
                'db' => $name
            ]);
        });

        $env = $this->getEnv();

        // If has a environment scoped configuration
        if ($config->exists($env)) {
            $envConf = $config->get($env);
            $config->merge($envConf);
        }

        if ($driver == MysqlDatabaseServer::DRIVER_HANDLE) {
            return new MysqlDatabaseServer($name, $config, $this->shell);
        }

        throw new InvalidArgumentException(
            "Invalid database driver \"{$driver}\"!"
        );
    }

    /**
     * @return Script[]
     */
    private function getGeneratedScripts()
    {
        $scripts = [];

        foreach ($this->configs as $name => $conf) {
            if ($conf->mustBeGenerated()) {
                $scripts[] = new Script($name, $conf);
            }
        }

        return $scripts;
    }

    /**
     * @return DatabaseServer
     */
    public function getServer($server)
    {
        if (!$this->existsServer($server)) {
            throw new InvalidArgumentException(
                "Database server \"{$server}\" not found!"
            );
        }

        return $this->servers[$server];
    }

    private function existsServer($server)
    {
        return isset($this->servers[$server]);
    }

    public function run($task, $args = [])
    {
        $closure = $this->findTask($task);

        $this->setArguments($args);

        call_user_func($closure, $this);
    }

    private function setArguments($args)
    {
        $this->args = new DataBag($args);
    }

    public function arg($name, $defaultValue = NULL)
    {
        return $this->args->get($name, $defaultValue);
    }

    private function findTask($task)
    {
        return $this->config->extract($task, NULL, function ($value) {

            if ($value == NULL) throw new InvalidArgumentException(
                "Task \"{$value}\" not found!"
            );

            if (!is_callable($value)) throw new InvalidArgumentException(
                "Invalid \"{$value}\" task!"
            );

            return $value;
        });
    }

    private function normalizePath($path)
    {
        $path = trim(strval($path));

        // Normalize back-slashes to forward slashes
        $path = str_replace('\\', '/', $path);

        // Normalize multiples forward slashes. Usually cause by
        // ill made concatenations
        $path = preg_replace("/([\/]{2,})/", '/', $path);

        $path = $this->removeFunkyWhiteSpace($path);

        return $path;
    }

    /**
     * Removes unprintable characters and invalid unicode characters.
     */
    private function removeFunkyWhiteSpace($path) {
        // We do this check in a loop, since removing invalid unicode characters
        // can lead to new characters being created.
        while (preg_match('#\p{C}+|^\./#u', $path)) {
            $path = preg_replace('#\p{C}+|^\./#u', '', $path);
        }

        return $path;
    }

}
