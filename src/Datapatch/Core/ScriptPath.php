<?php


namespace Datapatch\Core;

class ScriptPath
{
    const REGEX = "/^([^\s\/]+)(\/([^\s]+))?$/i";

    /**
     * @var string
     */
    private $patch;

    /**
     * @var string
     */
    private $script;

    public function __construct($fullPath)
    {
        $fullPath = $this->normalizePath($fullPath);

        $matches = [];

        if (preg_match(static::REGEX, $fullPath, $matches)) {} else {
            throw new RuntimeException(
                "Invalid patch/script \"{$fullPath}\"!"
            );
        }

        $this->patch = $matches[1];
        $this->script = isset($matches[3]) ? $matches[3] : NULL;
        $this->script = basename($this->script, '.sql');
    }

    /**
     * @return string
     */
    public function getPatch()
    {
        return $this->patch;
    }

    /**
     * @return string
     */
    public function getScript()
    {
        return $this->script;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->pointToPatch() ? 'patch' : 'script';
    }

    /**
     * @return string
     */
    public function getFullPath()
    {
        if ($this->pointToScript()) {
            return "{$this->patch}/{$this->script}.sql";
        } else {
            return $this->patch;
        }
    }

    /**
     * @return bool
     */
    public function pointToScript()
    {
        return ! $this->pointToPatch();
    }

    /**
     * @return bool
     */
    public function pointToPatch()
    {
        return empty($this->script);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getFullPath();
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

        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        // $path = $this->resolve($path);

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
