<?php

namespace Datapatch\Console\Command;

use Datapatch\Core\Shell;
use Datapatch\Tests\Databases;
use Datapatch\Tests\DatabasesHelper;
use Datapatch\Tests\TestHelper;
use PHPUnit\Framework\TestCase;
use Exception;

class DatapatchAppTest extends TestCase
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
            $this->terminal("
                rm -rf db/patches/ERR01
            ");
        }

        $this->bootTestDatabase();
    }

    public function tearDown()
    {
        if (is_dir('db/patches/ERR01')) {
            $this->terminal("
                rm -rf db/patches/ERR01
            ");
        }
    }

    private function bootTestDatabase()
    {
        $this->terminal("bash script/init-databases.sh");
    }

    public function testApplyDev122()
    {
        $this->nonAppliedPatchesContains("DEV-122");


        // Check patch status
        $out = $this->terminal("
            php bin/datapatch status -p DEV-122
        ");
        $this->assertStringContainsString("DEV-122 is not applied at development", $out);



        $out = $this->terminal("
            php bin/datapatch apply DEV-122
        ");

        $this->nonAppliedPatchesDoNotContains("DEV-122");



        // Check patch status
        $out = $this->terminal("
            php bin/datapatch status -p DEV-122
        ");
        $this->assertStringContainsString("DEV-122 is fully applied at development", $out);



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

        $out = $this->terminal("
            php bin/datapatch status -e production -y
        ");

        $this->assertStringContainsString('DEV-122', $out);

        $out = $this->terminal("
            php bin/datapatch apply DEV-122 -e production -y
        ");

        $out = $this->terminal("
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
        $out = $this->terminal("
            php bin/datapatch status
        ");

        $this->assertStringContainsString($patch, $out);
    }

    private function nonAppliedPatchesDoNotContains($patch)
    {
        $out = $this->terminal("
            php bin/datapatch status
        ");

        $this->assertStringNotContainsString($patch, $out);
    }

    public function testApplyBundle()
    {
        $this->nonAppliedPatchesContains("CARD-3236");
        $this->nonAppliedPatchesContains("CARD-3235");


        // Check bundle status
        $out = $this->terminal("
            php bin/datapatch status -b 2019.10.12
        ");
        $this->assertStringContainsString("Bundle 2019.10.12 at development", $out);
        $this->assertStringContainsString("CARD-3236 (not applied)", $out);
        $this->assertStringContainsString("CARD-3235 (not applied)", $out);


        $out = $this->terminal("
            php bin/datapatch deploy 2019.10.12
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
        $out = $this->terminal("
            php bin/datapatch deploy 2019.10.12
        ");

        $this->nonAppliedPatchesContains("DEV-231");
        $this->nonAppliedPatchesContains("CARD-3237");

        $out = $this->terminal("
            php bin/datapatch apply-all
        ");

        $status = $this->terminal("
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


    public function testPartiallyApply()
    {
        $this->terminal("php bin/datapatch apply CARD-3235/zun");
        $this->assertEquals(
            3,
            $this->databases->queryFirst('mysql56', 'zun', "SELECT count(*) as nrows FROM card3235zun")->nrows
        );


        $out = $this->terminal("
            php bin/datapatch status
        ");
        $this->assertStringContainsString("CARD-3235 (Partially Applied)", $out);
        $this->assertRegExp("/zun\.sql (.+) Done \✓/", $out);
        $this->assertRegExp("/reports\.sql (.+) Not Applied \✖/", $out);



        $out = $this->terminal("
            php bin/datapatch status -p CARD-3235
        ");
        $this->assertStringContainsString("CARD-3235 is partially applied", $out);
        $this->assertRegExp("/zun\.sql (.+) Done \✓/", $out);
        $this->assertRegExp("/reports\.sql (.+) Not Applied \✖/", $out);



        $out = $this->terminal("
            php bin/datapatch status -b 2019.10.12
        ");
        $this->assertStringContainsString("Bundle 2019.10.12 at development", $out);
        $this->assertStringContainsString("CARD-3236 (not applied)", $out);
        $this->assertStringContainsString("CARD-3235 (Partially Applied)", $out);
        $this->assertRegExp("/zun\.sql (.+) Done \✓/", $out);
        $this->assertRegExp("/reports\.sql (.+) Not Applied \✖/", $out);
    }



    public function testApplyAllEnvProduction()
    {
        $this->databases = $this->helper->getDatabasesHelper('production');

        $out = $this->terminal("
            php bin/datapatch deploy 2019.10.12 -e production -y
        ");
        $this->assertStringContainsString("at production env", $out);

        $out = $this->terminal("
            php bin/datapatch apply-all -e production -y
        ");
        $this->assertStringContainsString("at production env", $out);


        $status = $this->terminal("
            php bin/datapatch status
        ");
        $this->assertStringContainsString("CARD-3236", $status);


        $status = $this->terminal("
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
        $out = $this->terminal("
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

        $out = $this->terminal("
            php bin/datapatch apply ERR01/log
        ");
        $this->assertStringContainsString("... Done", $out);




        try {
            $out = $this->terminal("
                php bin/datapatch apply ERR01
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertStringContainsString("ERROR 1054", $e->getMessage());
        }





        // verify script status
        $out = $this->terminal("
            php bin/datapatch status
        ");
        $this->assertStringContainsString("CARD-3235 (not applied)", $out);
        $this->assertStringContainsString("ERR01 (Error)", $out);
        $this->assertRegExp("/log\.sql (.+) Done \✓/", $out);
        $this->assertRegExp("/pap\.sql (.+) Error \✖/", $out);
        $this->assertRegExp("/pap\.sql (.+) Not Applied \✖/", $out);

        // verufy patch status
        $out = $this->terminal("
            php bin/datapatch status --patch ERR01
        ");
        $this->assertStringContainsString("ERR01 is errored at development", $out);
        $this->assertRegExp("/log\.sql (.+) Done \✓/", $out);
        $this->assertRegExp("/pap\.sql (.+) Error \✖/", $out);
        $this->assertRegExp("/pap\.sql (.+) Not Applied \✖/", $out);

        // verify script status
        $out = $this->terminal("
            php bin/datapatch status --script ERR01/pap
        ");
        $this->assertStringContainsString("ERR01/pap.sql at development", $out);
        $this->assertRegExp("/pap\.sql (.+) Error \✖/", $out);


        // fix syntax
        file_put_contents('db/patches/ERR01/pap.sql', "INSERT INTO hash VALUES (1500, 'key1500', 'val1500');\n");
        try {
            $out = $this->terminal("
                php bin/datapatch apply ERR01
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertStringContainsString("ERR01/pap.sql was previously executed and raised an error!", $e->getMessage());
        }




        // force run
        $out = $this->terminal("
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
        $out = $this->terminal("
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

        $out = $this->terminal("
            php bin/datapatch apply ERR01/log
        ");
        $this->assertStringContainsString("... Done", $out);




        try {
            $out = $this->terminal("
                php bin/datapatch apply ERR01
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $ex) {
            $this->assertTrue(TRUE);
        }



        // fix syntax
        file_put_contents('db/patches/ERR01/pap.sql', "INSERT INTO hash VALUES (1500, 'key1500', 'val1500');\n");


        // try apply and must be denied
        try {
            $out = $this->terminal("
                php bin/datapatch apply ERR01
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertStringContainsString("ERR01/pap.sql was previously executed and raised an error!", $e->getMessage());
        }

        // pap.sql must have not be applied
        $this->assertEquals(
            NULL,
            $this->databases->queryFirst('mysql56', 'zun_pr', "SELECT * FROM hash WHERE id = 1500")
        );



        // manual run
        $out = $this->terminal("
            php bin/datapatch exec db/patches/ERR01/pap.sql -d zun_pr --server mysql56
        ");
        $this->assertEquals(
            'key1500',
            $this->databases->queryFirst('mysql56', 'zun_pr', "SELECT * FROM hash WHERE id = 1500")->k
        );



        // mark as executed in the first pap database
        $out = $this->terminal("
            php bin/datapatch mark-executed ERR01/pap.sql -d zun_pr
        ");
        $this->assertEquals(
            NULL,
            $this->databases->queryFirst('mysql57', 'zun_mg', "SELECT * FROM hash WHERE id = 1500")
        );



        // force after fix
        $out = $this->terminal("
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
        $out = $this->terminal("
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

        $out = $this->terminal("
            php bin/datapatch apply ERR01/log
        ");
        $this->assertStringContainsString("... Done", $out);




        try {
            $out = $this->terminal("
                php bin/datapatch apply ERR01
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $ex) {
            $this->assertTrue(TRUE);
        }



        // fix script
        file_put_contents('db/patches/ERR01/zun.sql', "select 1;");


        // try apply and must be denied
        try {
            $out = $this->terminal("
                php bin/datapatch apply ERR01
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertStringContainsString("ERR01/zun.sql was previously executed and raised an error!", $e->getMessage());
        }


        // Check if first transaction was commited
        $this->assertEquals(
            2,
            $this->databases->queryFirst('mysql56', 'zun', "SELECT count(*) as nrows FROM transaction")->nrows
        );

    }


    public function testInsertCommand()
    {
        $this->terminal("
            php bin/datapatch exec tests/scripts/insert.sql -r mysql56 -d zun_*
        ");

        $res = $this->databases->queryFirst('mysql56', 'zun_rs', "SELECT * FROM tenants WHERE id = 1200");
        $this->assertEquals('Test Tenant 1200 Çñ', $res->name);

        $res = $this->databases->queryFirst('mysql56', 'zun_pr', "SELECT * FROM tenants WHERE id = 1200");
        $this->assertEquals('Test Tenant 1200 Çñ', $res->name);
    }

    public function testInsertOnServer()
    {
        $this->terminal("
            php bin/datapatch exec tests/scripts/inserts_explicit_schema.sql -r mysql57
        ");

        $res = $this->databases->queryFirst('mysql57', 'zun_ms', "SELECT * FROM tenants WHERE id = 1200");
        $this->assertEquals('Test Tenant 1200 Çñ', $res->name);

        $res = $this->databases->queryFirst('mysql57', 'zun_mt', "SELECT * FROM tenants WHERE id = 1200");
        $this->assertEquals('Test Tenant 1200 Çñ', $res->name);
    }

    public function testProtectedEnv()
    {
        $out = $this->terminal("
            php bin/datapatch status -e development
        ");

        $this->assertStringContainsString("DEV-122", $out);
        $this->assertStringContainsString("CARD-3237", $out);
        $this->assertStringContainsString("TASK-6780", $out);

        try {
            $out = $this->terminal("
                php bin/datapatch status -e staging
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertStringContainsString("Denied execution", $e->getMessage());
        }
    }

    public function testUndefinedEnv()
    {
        $out = $this->terminal("
            php bin/datapatch status -e staging -y
        ");
        $this->assertStringContainsString("DEV-122", $out);
        $this->assertStringContainsString("CARD-3237", $out);
        $this->assertStringContainsString("TASK-6780", $out);

        try {
            $out = $this->terminal("
                php bin/datapatch status -e beta -y
            ");
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertStringContainsString("Invalid env \"beta\"", $e->getMessage());
        }
    }

    private function terminal($cmd)
    {
        $out = $this->shell->run($cmd);
        $out = preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $out);
        return $out;
    }
}
