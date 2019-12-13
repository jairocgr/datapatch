<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\DatabaseServer;
use Datapatch\Datapatch;
use Datapatch\Lang\DataBag;
use Datapatch\Core\Environment;
use Datapatch\Util\ConsoleOutput;
use RuntimeException;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

abstract class BaseCommand extends Command
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Datapatch
     */
    protected $datapatch;

    /**
     * @var Environment
     */
    protected $env;

    /**
     * @var ConsoleOutput
     */
    protected $console;

    /**
     * @var int
     */
    protected $verbosity;

    protected function configure()
    {
        $this->addOption(
                'config',
                'C',
                InputOption::VALUE_OPTIONAL,
                'Configuration file',
                'datapatch.config.php'
             )

             ->addOption(
                'set',
                's',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Set parameter via "param=val" format'
             )

             ->addOption(
                'env',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Choosed environment',
                'development'
             )

             ->addOption(
                'yes',
                'y',
                InputOption::VALUE_OPTIONAL,
                'Non interactive yes',
                FALSE
             );

        $this->config();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->verbosity = intval(getenv('SHELL_VERBOSITY'));

        $this->console = new ConsoleOutput($input, $output);

        $this->config = $this->loadConfig();

        $this->datapatch = new Datapatch($this->config);

        $this->env = $this->datapatch->getEnv();

        if ($this->env->isProtected()) {
            if ($this->canContinue("Proceed running in {$this->getFormattedEnv()} environment?")) {} else {
                throw new RuntimeException("Denied execution!");
            }
        }

        $this->exec();
    }

    protected function getFormattedEnv()
    {
        $tag = $this->getEnvFmtTag();

        return "<{$tag}>{$this->env}</{$tag}>";
    }

    protected function getEnvFmtTag()
    {
        $color = $this->env->getColor();

        switch ($color) {
            case 'red':
            case 'green':
            case 'blue':
            case 'yellow':
            case 'magenta':
            case 'cyan':
                return "b{$color}";
            default:
                return 'b';
        }
    }

    protected function write($msg = "")
    {
        $this->console->write($msg);
    }

    protected function writeln($msg = "")
    {
        $this->console->writeln($msg);
    }

    protected function puts($msg = "")
    {
        $this->console->puts($msg);
    }

    /** @return array */
    private function loadConfig()
    {
        $configFile = $this->input->getOption('config');

        if (!file_exists($configFile)) {
            throw new RuntimeException(
                "Config file \"{$configFile}\" does not exists!"
            );
        }

        $config = require $configFile;
        $config = new DataBag($config);

        foreach ($this->parseParams() as $key => $value) {
            $config->set($key, $value);
        }

        if ($this->hasEnvOption()) {
            $env = $this->input->getOption('env');
            $config->set('env', $env);
        } else {
            throw new RuntimeException("Invalid empty environment option!");
        }

        return $config->toArray();
    }

    protected function hasEnvOption()
    {
        return !empty($this->input->getOption('env'));
    }

    protected abstract function config();

    protected abstract function exec();

    protected function format($seconds)
    {
        if ($seconds < 1) {
            return number_format($seconds * 1000, 2) . 'ms';
        }

        $seconds = intval(ceil($seconds));

        $secondsInAMinute = 60;
        $secondsInAnHour = 60 * $secondsInAMinute;
        $secondsInADay = 24 * $secondsInAnHour;

        // Extract days
        $days = floor($seconds / $secondsInADay);

        // Extract hours
        $hourSeconds = $seconds % $secondsInADay;
        $hours = floor($hourSeconds / $secondsInAnHour);

        // Extract minutes
        $minuteSeconds = $hourSeconds % $secondsInAnHour;
        $minutes = floor($minuteSeconds / $secondsInAMinute);

        // Extract the remaining seconds
        $remainingSeconds = $minuteSeconds % $secondsInAMinute;
        $seconds = ceil($remainingSeconds);

        // Format and return
        $timeParts = [];
        $sections = [
            'day' => (int)$days,
            'h'   => (int)$hours,
            'min' => (int)$minutes,
            'sec' => (int)$seconds,
        ];

        foreach ($sections as $name => $value) {
            if ($value > 0) {
                $timeParts[] = $value . $name . ($value == 1 ? '' : 's');
            }
        }

        return implode(', ', $timeParts);
    }

    protected function cutoff($string, $maxLenght)
    {
        if (strlen($string) > $maxLenght) {
            $string = substr($string, 0, $maxLenght - 3 );
            $string = rtrim($string);
            return $string . '...';
        }

        return $string;
    }

    protected function hidePwd($password)
    {
        if (empty($password)) {
            return "EMPTY_PASSWORD_STRING";
        }

        $password = strval($password);

        $showedCharacters = intval(ceil(strlen($password) / 6));

        $viseblePart = substr($password, 0, $showedCharacters);

        return str_pad($viseblePart, strlen($password), '*');
    }

    private function parseParams()
    {
        $params = [];

        foreach ($this->input->getOption('set') as $param) {

            if (preg_match('/[^\s]+\=.*/', $param) !== 1) {
                throw new InvalidArgumentException("Invalid parameter \"{$param}\"!");
            }

            $pieces = explode('=', $param);

            $key = trim($pieces[0]);
            $value = trim(isset($pieces[1]) ? $pieces[1] : '');

            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * @return bool
     */
    protected function canContinue($msg)
    {
        // If is a forced execution there is nothing to confirm
        if ($this->forcedExecution()) {
            return TRUE;
        }

        // If --yes
        if ($this->automaticAccept()) {
            return TRUE;
        }

        return $this->confirm($msg);
    }

    /**
     * @return bool
     */
    protected function confirm($msg)
    {
        $msg = $this->console->parse("{$msg} (y/n)");

        if ($this->input->isInteractive()) {

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("{$msg} ", false);

            return $helper->ask($this->input, $this->output, $question);

        } else {
            // If the input is not comming from an interative terminal, then we must
            // safetly unconfirm and let the user know about it
            $this->console->writeln("{$msg} — <options=bold;fg=yellow>Canceled</>");
            return FALSE;
        }
    }

    protected function automaticAccept()
    {
        if ($this->input->hasOption('yes')) {
            return ($this->input->getOption("yes") !== FALSE);
        } else {
            return FALSE;
        }
    }

    protected function forcedExecution()
    {
        if ($this->input->hasOption('force')) {
            return ($this->input->getOption("force") !== FALSE);
        } else {
            return FALSE;
        }
    }
}
