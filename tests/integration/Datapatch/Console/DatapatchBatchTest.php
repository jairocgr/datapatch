<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\Shell;
use Datapatch\Tests\Databases;
use Datapatch\Tests\DatabasesHelper;
use Datapatch\Tests\TestHelper;
use PHPUnit\Framework\TestCase;
use Exception;

class DatapatchBatchTest extends TestCase
{
    /**
     * @var Shell
     */
    private $shell;

    /**
     * @var DatabasesHelper
     */
    private $databases;

    /**
     * @var TestHelper
     */
    private $helper;

    public function setUp()
    {
        $this->helper = new TestHelper();
        $this->shell = $this->helper->getShell();
        $this->databases = $this->helper->getDatabasesHelper();

        $this->setupBatchPatch();

        $this->bootTestDatabase();
    }

    public function tearDown()
    {
        $this->deleteBatch();
    }

    private function setupBatchPatch()
    {
        $this->deleteBatch();
        $this->shell->run("
            php bin/datapatch gen:patch BATCH
            rm -rf db/patches/BATCH/*.sql
            cp script/batch_inserts.sql db/patches/BATCH/inserts.sql
        ");
    }

    private function deleteBatch()
    {
        if (is_dir('db/patches/BATCH')) {
            $this->shell->run("
                rm -rf db/patches/BATCH
            ");
        }
    }

    private function bootTestDatabase()
    {
        $this->shell->run("bash script/init-databases.sh");
    }

    public function testApplyAndCancel()
    {
        $this->nonAppliedPatchesContains("BATCH");

        $this->shell->run("
            php bin/datapatch apply BATCH &
            echo $! > tmp/batch.pid
            sleep 1
            kill -9 \$(cat tmp/batch.pid)
        ");

        $this->nonAppliedPatchesContains("BATCH");

        $out = $this->shell->run("
            php bin/datapatch status -p BATCH
        ");
        $this->assertStringContainsString('BATCH is unfinished', $out);
        $this->assertRegExp("/inserts\.sql (.+) Unfinished \✖/", $out);

        try {
            $out = $this->shell->run("
                php bin/datapatch apply BATCH
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertStringContainsString('The script BATCH/inserts.sql seems to was previously executed', $e->getMessage());
        }
    }

    public function testApply()
    {
        $this->nonAppliedPatchesContains("BATCH");

        $out = $this->shell->run("
            php bin/datapatch apply BATCH
        ");

        $this->assertStringContainsString('... Done', $out);
        $this->assertEquals(
            7000,
            $this->databases->queryFirst('mysql56', 'zun', "SELECT count(*) as nrows FROM users")->nrows
        );


        $out = $this->shell->run("
            php bin/datapatch status -p BATCH
        ");
        $this->assertStringContainsString('BATCH is fully applied', $out);
        $this->assertRegExp("/inserts\.sql (.+) Done \✓/", $out);
    }

    private function nonAppliedPatchesContains($patch)
    {
        $out = $this->shell->run("
            php bin/datapatch status
        ");

        $this->assertStringContainsString($patch, $out);
    }
}
