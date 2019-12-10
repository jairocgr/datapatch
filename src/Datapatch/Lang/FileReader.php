<?php

namespace Datapatch\Lang;

use InvalidArgumentException;
use Iterator;
use RuntimeException;

class FileReader implements Iterator
{
    /**
     * @var string
     */
    private $filepath;

    /**
     * @var resource
     */
    private $file;

    /**
     * @var int
     */
    private $currentLine;

    public function __construct($filepath)
    {
        $this->filepath = $filepath;
    }

    public function current()
    {
        return $this->readCurrentLine();
    }

    public function key()
    {
        return $this->currentLine;
    }

    public function next()
    {
        $this->currentLine++;
    }

    public function rewind()
    {
        if ($this->fileOpened())
        {
            $this->close();
        }
    }

    public function valid()
    {
        if ($this->fileOpened())
        {
            return ! $this->endOfFile();
        }

        return TRUE;
    }

    private function fileOpened()
    {
        return $this->file != NULL;
    }

    private function fileClosed()
    {
        return $this->file == NULL;
    }

    private function readCurrentLine()
    {
        if ($this->fileClosed()) {
            $this->open();
        }

        return $this->fgets();
    }

    private function endOfFile()
    {
        return feof($this->file);
    }

    private function open()
    {
        $this->file = $this->fopen();
        $this->currentLine = 0;
    }

    private function fopen()
    {
        $file = fopen($this->filepath, 'r');

        if ($file) {} else {
            throw new RuntimeException(
                "Can not open \"{$this->filepath}\" file!"
            );
        }

        return $file;
    }

    private function fgets()
    {
        $line = fgets($this->file);

        if ($line === FALSE) {
            return "";
        }

        return $line;
    }

    private function close()
    {
        @fclose($this->file);
        $this->file = NULL;
        $this->currentLine = 0;
    }
}
