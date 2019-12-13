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
                    $this->puts("");
                    $this->puts("The script <err>{$script}</err> was previously executed and raised an error!");
                    $this->puts("");
                    $this->puts("You may have to fix the script and, if needed, run a stand-alone script");
                    $this->puts("to correct any wrong database state:");
                    $this->puts("");
                    $this->puts("  $ datapatch exec fix.sql -d {$database} --server {$database->getServer()}");
                    $this->puts("");
                    $this->puts("Mark it as executed and you will be able to re-apply the <b>{$patch}</b> patch");
                    $this->puts("in the remaining databases:");
                    $this->puts("");
                    $this->puts("  $ datapatch <b>mark-executed</b> {$script} -d <b>{$database}</>");
                    $this->puts("");
                    $this->puts("You can also force it using the <b>--force</b> flag.");
                    $this->puts("");

                    $database->unlock();

                    // Signals abnormal termination
                    exit(32);
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
