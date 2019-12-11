<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\Shell;
use Datapatch\Tests\Databases;
use Datapatch\Tests\DatabasesHelper;
use Datapatch\Tests\TestHelper;
use PHPUnit\Framework\TestCase;
use Exception;

class ExecCommandTest extends TestCase
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

        if (is_dir('db/patches/ERR01')) {
            $this->shell->run("
                rm -rf db/patches/ERR01
            ");
        }

        $this->bootTestDatabase();
    }

    private function bootTestDatabase()
    {
        $this->shell->run("bash script/init-databases.sh");
    }

    public function testApplyDev122()
    {
        $this->nonAppliedPatchesContains("DEV-122");

        $out = $this->shell->run("
            php bin/datapatch apply DEV-122
        ");


        $this->nonAppliedPatchesDoNotContains("DEV-122");

        $this->assertEquals(
            5,
            $this->databases->queryFirst('mysql56', 'zun_rs', "SELECT count(*) as nrows FROM dev122pap")->nrows
        );

        $this->assertEquals(
            5,
            $this->databases->queryFirst('mysql57', 'zun_mt', "SELECT count(*) as nrows FROM dev122pap")->nrows
        );

        $this->assertEquals(
            5,
            $this->databases->queryFirst('local', 'zun_ro', "SELECT count(*) as nrows FROM dev122pap")->nrows
        );

        $this->assertEquals(
            3,
            $this->databases->queryFirst('mysql57', 'logs', "SELECT count(*) as nrows FROM dev122log")->nrows
        );

        $this->assertTrue($this->databases->tableExists('mysql56', 'zun', 'telemetry'));
        $this->assertFalse($this->databases->tableExists('mysql57', 'logs', 'telemetry'));

        $this->assertEquals(
            2,
            $this->databases->queryFirst('mysql56', 'zun', "SELECT count(*) as nrows FROM telemetry")->nrows
        );
    }

    public function testApplyDev122Scoped()
    {
        $this->databases = $this->helper->getDatabasesHelper('production');

        $out = $this->shell->run("
            php bin/datapatch status -e production -y
        ");

        $this->assertStringContainsString('DEV-122', $out);

        $out = $this->shell->run("
            php bin/datapatch apply DEV-122 -e production -y
        ");

        $out = $this->shell->run("
            php bin/datapatch status -e production -y
        ");

        $this->assertStringNotContainsString('DEV-122', $out);

        $this->assertEquals(
            5,
            $this->databases->queryFirst('mysql56', 'zun_rs', "SELECT count(*) as nrows FROM dev122pap")->nrows
        );

        $this->assertEquals(
            5,
            $this->databases->queryFirst('mysql57', 'zun_mt', "SELECT count(*) as nrows FROM dev122pap")->nrows
        );

        $this->assertEquals(
            5,
            $this->databases->queryFirst('local', 'zun_ro', "SELECT count(*) as nrows FROM dev122pap")->nrows
        );

        $this->assertEquals(
            3,
            $this->databases->queryFirst('mysql57', 'logs', "SELECT count(*) as nrows FROM dev122log")->nrows
        );

        $this->assertFalse($this->databases->tableExists('mysql56', 'zun', 'telemetry'));
        $this->assertTrue($this->databases->tableExists('mysql57', 'logs', 'telemetry'));

        $this->assertEquals(
            2,
            $this->databases->queryFirst('mysql57', 'logs', "SELECT count(*) as nrows FROM telemetry")->nrows
        );
    }

    private function nonAppliedPatchesContains($patch)
    {
        $out = $this->shell->run("
            php bin/datapatch status
        ");

        $this->assertStringContainsString($patch, $out);
    }

    private function nonAppliedPatchesDoNotContains($patch)
    {
        $out = $this->shell->run("
            php bin/datapatch status
        ");

        $this->assertStringNotContainsString($patch, $out);
    }

    public function testApplyBundle()
    {
        $this->nonAppliedPatchesContains("CARD-3236");
        $this->nonAppliedPatchesContains("CARD-3235");

        $out = $this->shell->run("
            php bin/datapatch apply 2019.10.12
        ");

        $this->nonAppliedPatchesDoNotContains("CARD-3236");
        $this->nonAppliedPatchesDoNotContains("CARD-3235");

        $this->assertEquals(
            1,
            $this->databases->queryFirst('mysql56', 'reports', "SELECT count(*) as nrows FROM card3236reports")->nrows
        );

        $this->assertEquals(
            3,
            $this->databases->queryFirst('mysql56', 'zun', "SELECT count(*) as nrows FROM card3235zun")->nrows
        );
    }

    public function testListPatches()
    {
        $this->nonAppliedPatchesContains('DEV-122');
        $this->nonAppliedPatchesContains('TASK-6780');
        $this->nonAppliedPatchesContains('CARD-3235');
        $this->nonAppliedPatchesContains('CARD-3236');
        $this->nonAppliedPatchesContains('CARD-3237');
        $this->nonAppliedPatchesContains('DEV-231');
    }

    public function testApplyAll()
    {
        $out = $this->shell->run("
            php bin/datapatch apply 2019.10.12
        ");

        $this->nonAppliedPatchesContains("DEV-231");
        $this->nonAppliedPatchesContains("CARD-3237");

        $out = $this->shell->run("
            php bin/datapatch apply-all
        ");

        $status = $this->shell->run("
            php bin/datapatch status
        ");

        $this->assertStringContainsString("No patches to be applied!", $status);

        $this->assertEquals(
            5,
            $this->databases->queryFirst('mysql56', 'zun_rs', "SELECT count(*) as nrows FROM task6280pap")->nrows
        );

        $this->assertEquals(
            5,
            $this->databases->queryFirst('mysql57', 'zun_mg', "SELECT count(*) as nrows FROM task6280pap")->nrows
        );



        $this->assertEquals(
            10,
            $this->databases->queryFirst('local', 'zun_rr', "SELECT count(*) as nrows FROM task6280pap")->nrows
        );

        $this->assertEquals(
            12,
            $this->databases->queryFirst('local', 'zun_ma', "SELECT count(*) as nrows FROM task6280pap")->nrows
        );

        $this->assertEquals(
            5,
            $this->databases->queryFirst('local', 'zun_pa', "SELECT count(*) as nrows FROM task6280pap")->nrows
        );



        $this->assertEquals(
            0,
            $this->databases->queryFirst('mysql57', 'logs', "SELECT count(*) as nrows FROM dev231log")->nrows
        );
    }



    public function testApplyAllEnvProduction()
    {
        $this->databases = $this->helper->getDatabasesHelper('production');

        $out = $this->shell->run("
            php bin/datapatch apply 2019.10.12 -e production -y
        ");

        $out = $this->shell->run("
            php bin/datapatch apply-all -e production -y
        ");


        $status = $this->shell->run("
            php bin/datapatch status
        ");
        $this->assertStringContainsString("CARD-3236", $status);


        $status = $this->shell->run("
            php bin/datapatch status -e production -y
        ");

        $this->assertStringContainsString("No patches to be applied!", $status);

        $this->assertEquals(
            5,
            $this->databases->queryFirst('mysql56', 'zun_rs', "SELECT count(*) as nrows FROM task6280pap")->nrows
        );

        $this->assertEquals(
            7,
            $this->databases->queryFirst('mysql57', 'zun_mg', "SELECT count(*) as nrows FROM task6280pap")->nrows
        );



        $this->assertEquals(
            10,
            $this->databases->queryFirst('local', 'zun_rr', "SELECT count(*) as nrows FROM task6280pap")->nrows
        );

        $this->assertEquals(
            12,
            $this->databases->queryFirst('local', 'zun_ma', "SELECT count(*) as nrows FROM task6280pap")->nrows
        );

        $this->assertEquals(
            5,
            $this->databases->queryFirst('local', 'zun_pa', "SELECT count(*) as nrows FROM task6280pap")->nrows
        );



        $this->assertEquals(
            0,
            $this->databases->queryFirst('mysql57', 'logs', "SELECT count(*) as nrows FROM dev231log")->nrows
        );
    }

    public function testGenFailedPatch()
    {
        $out = $this->shell->run("
            php bin/datapatch gen:patch ERR01
        ");

        $this->assertFileExists('db/patches/ERR01/pap.sql');
        $this->assertFileExists('db/patches/ERR01/zun.sql');
        $this->assertFileExists('db/patches/ERR01/reports.sql');
        $this->assertFileExists('db/patches/ERR01/log.sql');




        unlink('db/patches/ERR01/zun.sql');
        unlink('db/patches/ERR01/reports.sql');

        file_put_contents('db/patches/ERR01/log.sql', "SELECT 1;\n");
        file_put_contents('db/patches/ERR01/pap.sql', "SELECT INS\n"); // syntax err

        $out = $this->shell->run("
            php bin/datapatch apply ERR01/log
        ");
        $this->assertStringContainsString("... Done", $out);




        $out = $this->shell->run("
            php bin/datapatch apply ERR01 2>&1
        ", NULL, FALSE);
        $this->assertStringContainsString("... Already executed", $out);



        // fix syntax
        file_put_contents('db/patches/ERR01/pap.sql', "INSERT INTO hash VALUES (1500, 'key1500', 'val1500');\n");
        $out = $this->shell->run("
            php bin/datapatch apply ERR01
        ", NULL, FALSE);
        $this->assertStringContainsString("ERR01/pap.sql was previously executed and raised an error!", $out);




        // force run
        $out = $this->shell->run("
            php bin/datapatch apply ERR01 -f
        ");

        $this->assertEquals(
            'key1500',
            $this->databases->queryFirst('mysql57', 'zun_mg', "SELECT * FROM hash WHERE id = 1500")->k
        );

        $this->assertEquals(
            'key1500',
            $this->databases->queryFirst('mysql56', 'zun_rs', "SELECT * FROM hash WHERE id = 1500")->k
        );
    }


    public function testGenFailedPatch2()
    {
        $out = $this->shell->run("
            php bin/datapatch gen:patch ERR01
        ");

        $this->assertFileExists('db/patches/ERR01/pap.sql');
        $this->assertFileExists('db/patches/ERR01/zun.sql');
        $this->assertFileExists('db/patches/ERR01/reports.sql');
        $this->assertFileExists('db/patches/ERR01/log.sql');




        unlink('db/patches/ERR01/zun.sql');
        unlink('db/patches/ERR01/reports.sql');

        file_put_contents('db/patches/ERR01/log.sql', "SELECT 1;\n");
        file_put_contents('db/patches/ERR01/pap.sql', "SELECT INS\n"); // syntax err

        $out = $this->shell->run("
            php bin/datapatch apply ERR01/log
        ");
        $this->assertStringContainsString("... Done", $out);




        try {
            $out = $this->shell->run("
                php bin/datapatch apply ERR01
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $ex) {
            $this->assertTrue(TRUE);
        }



        // fix syntax
        file_put_contents('db/patches/ERR01/pap.sql', "INSERT INTO hash VALUES (1500, 'key1500', 'val1500');\n");


        // try apply and must be denied
        $out = $this->shell->run("
            php bin/datapatch apply ERR01
        ", NULL, FALSE);
        $this->assertStringContainsString("ERR01/pap.sql was previously executed and raised an error!", $out);



        // pap.sql must have not be applied
        $this->assertEquals(
            NULL,
            $this->databases->queryFirst('mysql56', 'zun_pr', "SELECT * FROM hash WHERE id = 1500")
        );



        // manual run
        $out = $this->shell->run("
            php bin/datapatch exec db/patches/ERR01/pap.sql -d zun_pr --server mysql56
        ");
        $this->assertEquals(
            'key1500',
            $this->databases->queryFirst('mysql56', 'zun_pr', "SELECT * FROM hash WHERE id = 1500")->k
        );



        // mark as executed in the first pap database
        $out = $this->shell->run("
            php bin/datapatch mark-executed ERR01/pap.sql -d zun_pr
        ");
        $this->assertEquals(
            NULL,
            $this->databases->queryFirst('mysql57', 'zun_mg', "SELECT * FROM hash WHERE id = 1500")
        );



        // force after fix
        $out = $this->shell->run("
            php bin/datapatch apply ERR01
        ");

        $this->assertStringContainsString("Running pap.sql in zun_pr at mysql56... Already executed", $out);
        $this->assertStringContainsString("Running pap.sql in zun_rs at mysql56... Done", $out);


        $this->assertEquals(
            'key1500',
            $this->databases->queryFirst('mysql56', 'zun_pr', "SELECT * FROM hash WHERE id = 1500")->k
        );

        $this->assertEquals(
            'key1500',
            $this->databases->queryFirst('mysql57', 'zun_mg', "SELECT * FROM hash WHERE id = 1500")->k
        );

        $this->assertEquals(
            'key1500',
            $this->databases->queryFirst('mysql56', 'zun_rs', "SELECT * FROM hash WHERE id = 1500")->k
        );
    }


    public function testGenFailedPatchWithTransaction()
    {
        $out = $this->shell->run("
            php bin/datapatch gen:patch ERR01
        ");

        $this->assertFileExists('db/patches/ERR01/pap.sql');
        $this->assertFileExists('db/patches/ERR01/zun.sql');
        $this->assertFileExists('db/patches/ERR01/reports.sql');
        $this->assertFileExists('db/patches/ERR01/log.sql');




        unlink('db/patches/ERR01/pap.sql');
        unlink('db/patches/ERR01/reports.sql');

        file_put_contents('db/patches/ERR01/log.sql', "SELECT 1;\n");
        file_put_contents('db/patches/ERR01/zun.sql',
            file_get_contents('tests/scripts/transaction_test.sql')
        );

        $out = $this->shell->run("
            php bin/datapatch apply ERR01/log
        ");
        $this->assertStringContainsString("... Done", $out);




        try {
            $out = $this->shell->run("
                php bin/datapatch apply ERR01
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $ex) {
            $this->assertTrue(TRUE);
        }



        // fix script
        file_put_contents('db/patches/ERR01/zun.sql', "select 1;");


        // try apply and must be denied
        $out = $this->shell->run("
            php bin/datapatch apply ERR01
        ", NULL, FALSE);
        $this->assertStringContainsString("ERR01/zun.sql was previously executed and raised an error!", $out);

        $this->assertEquals(
            2,
            $this->databases->queryFirst('mysql56', 'zun', "SELECT count(*) as nrows FROM transaction")->nrows
        );

    }


    public function testInsertCommand()
    {
        $this->shell->run("
            php bin/datapatch exec tests/scripts/insert.sql -r mysql56 -d zun_*
        ");

        $res = $this->databases->queryFirst('mysql56', 'zun_rs', "SELECT * FROM tenants WHERE id = 1200");
        $this->assertEquals('Test Tenant 1200 Çñ', $res->name);

        $res = $this->databases->queryFirst('mysql56', 'zun_pr', "SELECT * FROM tenants WHERE id = 1200");
        $this->assertEquals('Test Tenant 1200 Çñ', $res->name);
    }

    public function testInsertOnServer()
    {
        $this->shell->run("
            php bin/datapatch exec tests/scripts/inserts_explicit_schema.sql -r mysql57
        ");

        $res = $this->databases->queryFirst('mysql57', 'zun_ms', "SELECT * FROM tenants WHERE id = 1200");
        $this->assertEquals('Test Tenant 1200 Çñ', $res->name);

        $res = $this->databases->queryFirst('mysql57', 'zun_mt', "SELECT * FROM tenants WHERE id = 1200");
        $this->assertEquals('Test Tenant 1200 Çñ', $res->name);
    }
}
