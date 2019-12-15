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
            return [];
        }

        $this->puts("Patches to be applied at {$this->getFormattedEnv()} env:");
        $this->puts("");

        $this->printPatchesStatuses($patches);

        $this->console->newLine();

        return $patches;
    }

    protected function printPatchesStatuses($patches)
    {
        foreach ($patches as $patch)
        {
            $this->printPatchStatus($patch);
        }
    }

    protected function printPatchStatus($patch)
    {
        if ($patch->isErrored()) {
            $this->puts(" * <b>{$patch}</> (<err>Error</>)");
            $this->printPatchScriptsStatus($patch);
            $this->puts("");
        } elseif ($patch->isUnfinished()) {
            $this->puts(" * <b>{$patch}</> (<warn>Unfinished</>)");
            $this->printPatchScriptsStatus($patch);
            $this->puts("");
        } elseif ($patch->isPartiallyApplied()) {
            $this->puts(" * <b>{$patch}</> (Partially Applied)");
            $this->printPatchScriptsStatus($patch);
            $this->puts("");
        } elseif ($patch->isFullyApplied()) {
            $this->puts(" * <b>{$patch}</> (<ok>Fully Applied ✓</>)");
        } else {
            $this->puts(" * <b>{$patch}</> <bfade>(not applied)</bfade>");
        }
    }

    protected function printPatchScriptsStatus($patch)
    {
        foreach ($patch->getScripts() as $script)
        {
            $this->printScriptStatus($script, '    ');
        }
    }

    protected function printScriptStatus($script, $indentation = ' ')
    {
        foreach ($script->getDatabases() as $database)
        {
            $state = $database->getScriptState($script);

            if ($state == Database::SCRIPT_EXECUTED) {
                $status = "<success>Done ✓</>";
            }

            elseif ($state == Database::SCRIPT_ERRORED) {
                $status = "<err>Error ✖</>";
            }

            elseif ($state == Database::SCRIPT_UNFINISHED) {
                $status = "<warn>Unfinished ✖</>";
            }

            elseif ($state == Database::SCRIPT_RUNNING) {
                $status = "<b>Running...</>";
            }

            else {
                $status = "<bfade>Not Applied ✖</bfade>";
            }

            $this->puts(
                $indentation .
                "<b>{$script->getName()}.sql</> in <b>{$database}</> " .
                "at <b>{$database->getServer()}</> server " .
                "{$status}"
            );
        }
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
