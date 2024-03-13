<?php
namespace vakata\database\test;

use vakata\collection\Collection;
use vakata\database\DB as DBI;
use vakata\database\DBException as DBE;

class HiddenTest extends \PHPUnit\Framework\TestCase
{
    protected function getConnectionString()
    {
        return "postgre://postgres:postgres@".gethostname().".local/test?schema=public";
    }

    protected function reset(): DBI
    {
        $dbc = new DBI($this->getConnectionString());
        $this->importFile(
            $dbc,
            __DIR__ . '/data/hidden.sql'
        );
        return $dbc;
    }
    protected function importFile(DBI $dbc, string $path)
    {
        $sql = file_get_contents($path);
        $sql = str_replace("\r", '', $sql);
        $sql = preg_replace('(--.*\n)', '', $sql);
        $sql = preg_replace('(\n+)', "\n", $sql);
        $sql = explode(';', $sql);
        foreach (array_filter(array_map("trim", $sql)) as $q) {
            $dbc->query($q);
        }
    }

    public function testGenerated()
    {
        $dbc = $this->reset();
        $tbl = $dbc->definition('tbl_hid');
        $this->assertEquals(count($tbl->getColumns()), 2);
        $this->assertEquals(count($tbl->getColumns(true)), 3);
    }
    public function testIdentity()
    {
        $dbc = $this->reset();
        $tbl = $dbc->definition('tbl_hid2');
        $this->assertEquals(count($tbl->getColumns()), 2);
        $this->assertEquals(count($tbl->getColumns(true)), 2);
    }
    public function testAccess()
    {
        $dbc = $this->reset();
        $tbl = $dbc->definition('tbl_hid');
        $this->assertEquals($tbl->getColumn('col_hid'), null);
        $this->assertEquals($tbl->getColumn('col_hid', true) !== null, true);
    }
}
