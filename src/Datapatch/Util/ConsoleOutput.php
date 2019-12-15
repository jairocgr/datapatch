<?php

namespace Datapatch\Util;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class ConsoleOutput
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var OutputInterface
     */
    private $out;

    public function __construct($input, $output)
    {
        $this->out = $output;
        $this->io = new SymfonyStyle($input, $output);
    }

    public function write($message = "")
    {
        return $this->io->write($this->parse($message));
    }

    public function writeln($message = "")
    {
        return $this->io->writeln($this->parse($message));
    }

    public function puts($message = "")
    {
        return $this->io->text($this->parse($message));
    }

    public function stderr($message = "")
    {
        $stderr = $this->getErrorOutput();
        $message = ' ' . $this->parse($message);

        return $stderr->writeln($message);
    }

    private function getErrorOutput()
    {
        if (!$this->out instanceof ConsoleOutputInterface) {
            return $this->out;
        }

        return $this->out->getErrorOutput();
    }

    public function bold($message)
    {
        return $this->puts("<b>{$message}</b>");
    }

    public function fade($message)
    {
        return $this->puts("<fade>{$message}</fade>");
    }

    public function success($message = "")
    {
        $this->puts("<success>{$message}</success>");
    }

    public function warning($message = "")
    {
        $this->puts("<warn>{$message}</warn>");
    }

    public function warn($message = "")
    {
        $this->warning($message);
    }

    public function fail($message = "")
    {
        $this->puts("<error>{$message}</error>");
    }

    public function newLine($count = 1)
    {
        $this->io->newLine($count);
    }

    /**
     * Parse tags inside the message (<b>, <warn>, etc)
     *
     * @return string
     */
    public function parse($message)
    {
        $message = str_replace("<b>", "<options=bold>", $message);

        $message = str_replace("<normal>", "\e[22m", $message);
        $message = str_replace("</normal>", "\e[22m", $message);

        $message = str_replace("<fade>", "\e[2m", $message);
        $message = str_replace("</fade>", "\e[22m", $message);

        $message = str_replace("<bfade>", "\033[1;30m", $message);
        $message = str_replace("</bfade>", "\033[0m", $message);

        $message = str_replace("<f>", "\e[2m", $message);
        $message = str_replace("</f>", "\e[22m", $message);

        $message = str_replace("<success>", "<bgreen>", $message);
        $message = str_replace("<good>", "<bgreen>", $message);
        $message = str_replace("<ok>", "<bgreen>", $message);

        $message = str_replace("<err>", "<bred>", $message);
        $message = str_replace("<fail>", "<bred>", $message);
        $message = str_replace("<error>", "<bred>", $message);
        $message = str_replace("<danger>", "<bred>", $message);

        $message = str_replace("<warn>", "<byellow>", $message);
        $message = str_replace("<warning>", "<byellow>", $message);

        $message = preg_replace("/\<b([a-z]+)\>/", "<options=bold;fg=$1>", $message);
        $message = preg_replace("/\<([a-z]+)\>/", "<fg=$1>", $message);

        $message = preg_replace("/\<\/[^>]*\>/", "</>", $message);

        return $message;
    }

    public function trace($message)
    {
        return $this->writeln("<fade>{$message}</fade>");
    }
}
