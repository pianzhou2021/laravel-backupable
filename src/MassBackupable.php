<?php

namespace Pianzhou\Backupable;

use Illuminate\Support\Facades\DB;
use LogicException;

trait Massbackupable
{
    use BackupableTable;
    /**
     * Backup all backupable models in the database.
     *
     * @param  int  $chunkSize
     * @return int
     */
    public function backupAll(int $chunkSize = 1000)
    {
        $backupTableName    = $this->getBackupTable();
        $query  = $this->backupable();
        $total  = $query->count();
        
        $page= intval(ceil($total / $chunkSize));
        for ($i = 1; $i <= $page; $i++) {
            $sql = sprintf('INSERT IGNORE INTO `%s` %s', $backupTableName, $query->limit($chunkSize)->offset(($i - 1) * $chunkSize)->toSql());
            DB::connection()->statement($sql, $query->getBindings());
            event(new ModelsBackuped(static::class, min($total, $i * $chunkSize) ));
        }

        return $total;

    }

    /**
     * Get the backupable model query.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function backupable()
    {
        throw new LogicException('Please implement the backupable method on your model.');
    }
}
