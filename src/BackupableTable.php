<?php

namespace Pianzhou\Backupable;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BackupableTable
{
    /**
     * Define backup table name
     *
     * @return string
     */
    public function backupTableName()
    {
        return $this->getTable() . '_backup';
    }

    /**
     * Get backup table name
     *
     * @return string
     */
    public function getBackupTable()
    {
        $originalTableName  = $this->getTable();
        $backupTableName    = $this->backupTableName();

        $needCreateTable    = true;
        if (Schema::hasTable($backupTableName)) {
            if (!$this->isTableChanged($originalTableName, $backupTableName)) {
                $backupTableName    = $backupTableName . '_' . ($this->getLastBackupTableNameIndex($backupTableName) + 1);
            } else {
                $needCreateTable = false;
            }
        }

        if ($needCreateTable) {
            $this->duplicateTable($originalTableName, $backupTableName);
        }

        return $backupTableName;
    }

    /**
     * Get last backup table name index
     *
     * @param string $backupTableName
     * @return int
     */
    public function getLastBackupTableNameIndex(string $backupTableName)
    {
        return collect(Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableNames())
        ->map(function($table) use ($backupTableName) {
            $index = str_replace($backupTableName.'_', '' , $table);
            if (is_numeric($index)) {
                return $index;
            }
            return 0;
        })
        ->max();
    }

    /**
     * check if origin table changed
     *
     * @param string $table1
     * @param string $table2
     * @return boolean
     */
    function isTableChanged(string $table1, string $table2)
    {
        $columns1 = Schema::getColumnListing($table1);
        $columns2 = Schema::getColumnListing($table2);
    
        // compare columns
        if (array_diff($columns1, $columns2) || array_diff($columns2, $columns1)) {
            return false;
        }
    
        foreach ($columns1 as $column) {
            $type1 = Schema::getColumnType($table1, $column);
            $type2 = Schema::getColumnType($table2, $column);
    
            // compare type
            if ($type1 != $type2) {
                return false;
            }
        }
    
        return true;
    }

    /**
     * duplicate table structure
     *
     * @param string $oldTable
     * @param string $newTable
     * @return boolean
     */
    public function duplicateTable(string $oldTable, string $newTable)
    {
        $sql = 'CREATE TABLE `%s` LIKE `%s`';
        return DB::connection()->statement(sprintf($sql, $newTable, $oldTable));
    }
}
