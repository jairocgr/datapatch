<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\DatabaseServer;
use Datapatch\Datapatch;
use Datapatch\Lang\DataBag;
use Datapatch\Util\ConsoleOutput;
use RuntimeException;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class InitCommand extends Command
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var ConsoleOutput
     */
    protected $console;

    protected function configure()
    {
        $this->setName('init')
             ->setDescription('Init datapatch project')
             ->setHelp('Create configuration file and directories');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->console = new ConsoleOutput($input, $output);

        Datapatch::initDirectory();

        $this->console->puts("Datapatch initialized.");
        $this->console->newLine();
    }
}
