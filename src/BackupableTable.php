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
        $originalBackupTableName    = $backupTableName = $this->backupTableName();
        $index              = $this->getLastBackupTableNameIndex($backupTableName);
        if ($index) {
            $backupTableName    = $originalBackupTableName . '_' . $index;
        }

        $needCreateTable    = true;
        if (Schema::hasTable($backupTableName)) {
            if ($this->isTableChanged($originalTableName, $backupTableName)) {
                $backupTableName    = $originalBackupTableName . '_' . ($index + 1);
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
        $sql   = 'show columns from `%s`';
        $table1Json = collect(DB::connection()->select(sprintf($sql, $table1)))->toJson();
        $table2Json = collect(DB::connection()->select(sprintf($sql, $table2)))->toJson();
        
        return $table1Json !== $table2Json;
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
