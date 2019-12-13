<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\Patch;
use Datapatch\Core\Database;

trait ScriptRunner
{
    protected function deploy($bundle)
    {
        $env = $this->getFormattedEnv();
        $this->puts("Deploying bundle <b>{$bundle}</b> at {$env} env:");

        foreach ($bundle->getPatches() as $patch)
        {
            $this->puts("");
            $this->apply($patch);
        }

        $this->puts("");
    }

    /**
     * @return Patch[]
     */
    protected function listNonAppliedPatches()
    {
        $this->puts("Loading non-applied patches...");
        $this->puts("");

        $patches = $this->datapatch->getNonAppliedPatches();

        if (empty($patches)) {
            $this->puts("No patches to be applied!");
            $this->puts("");
            return;
        }

        $this->puts("Patches to be applied at {$this->getFormattedEnv()} env:");
        $this->puts("");

        foreach ($patches as $patch)
        {
            $this->puts(" * <b>{$patch}</>");
        }

        $this->console->newLine();

        return $patches;
    }

    protected function apply($patch, $annex = NULL)
    {
        $annex = empty($annex) ? $annex : " {$annex}";

        $this->puts("Applying patch <b>{$patch}</b>{$annex}");

        foreach ($patch->getScripts() as $script) {
            $this->execScript($script);
        }
    }


    /**
     * @param $script Script
     */
    protected function execScript($script)
    {
        $patch = $script->getPatch();

        foreach ($script->getDatabases() as $database )
        {
            try {

                $this->write(
                    "  Running <b>{$script->getName()}.sql</b> in <b>{$database}</> ".
                    "at <b>{$database->getServer()}</>... "
                );

                $database->lock();

                $state = $database->getScriptState($script);

                if ($state == Database::SCRIPT_EXECUTED) {
                    $this->writeln("<success>Already executed ✓</success>");
                }

                elseif ($state == Database::SCRIPT_ERRORED && !$this->forcedExecution()) {
                    $this->writeln("<err>Already executed ✖</err>");
                    $this->stderr("");
                    $this->stderr("The script <err>{$script}</err> was previously executed and raised an error!");
                    $this->stderr("");
                    $this->stderr("You may have to fix the script and, if needed, run a stand-alone script");
                    $this->stderr("to correct any wrong database state:");
                    $this->stderr("");
                    $this->stderr("  $ datapatch exec fix.sql -d {$database} --server {$database->getServer()}");
                    $this->stderr("");
                    $this->stderr("Mark it as executed and you will be able to re-apply the <b>{$patch}</b> patch");
                    $this->stderr("in the remaining databases:");
                    $this->stderr("");
                    $this->stderr("  $ datapatch <b>mark-executed</b> {$script} -d <b>{$database}</>");
                    $this->stderr("");
                    $this->stderr("You can also force it using the <err>dangerous</> <b>--force</b> flag.");
                    $this->stderr("");

                    $database->unlock();

                    // Signals abnormal termination
                    exit(32);
                }

                elseif ($state == Database::SCRIPT_UNFINISHED && !$this->forcedExecution()) {
                    $this->writeln("<warn>Unfinished ✖</warn>");
                    $this->stderr("");
                    $this->stderr("The script <warn>{$script}</warn> seems to was previously executed but");
                    $this->stderr("was interrupted before it was done.");
                    $this->stderr("");
                    $this->stderr("You may have to inspect the the database and correct any wrong");
                    $this->stderr("database state left by the unfinished script.");
                    $this->stderr("");
                    $this->stderr("If the script has a single transaction it may have been rollbacked");
                    $this->stderr("without any integrity damage (but it is your job to ensure that).");
                    $this->stderr("");
                    $this->stderr("Mark it as executed and you will be able to re-apply the <b>{$patch}</b> patch");
                    $this->stderr("in the remaining databases:");
                    $this->stderr("");
                    $this->stderr("  $ datapatch <b>mark-executed</b> {$script} -d <b>{$database}</>");
                    $this->stderr("");
                    $this->stderr("You can also force it using the <err>dangerous</> <b>--force</b> flag.");
                    $this->stderr("");

                    $database->unlock();

                    // Signals abnormal termination
                    exit(64);
                }

                else {
                    $duration = $database->execute($script);
                    $this->writeln("<success>Done ✓</success> <fade>({$this->format($duration)})</fade>");
                }

            } finally {
                $database->unlock();
            }
        }
    }
}
