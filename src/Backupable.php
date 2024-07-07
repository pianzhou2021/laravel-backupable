<?php

namespace Pianzhou\Backupable;

use Illuminate\Support\Facades\DB;
use LogicException;

trait Backupable
{
    use BackupableTable;
    /**
     * backup all backupable models in the database.
     *
     * @param  int  $chunkSize
     * @return int
     */
    public function backupAll(int $chunkSize = 1000)
    {
        $total = 0;
        $this->backupable()
            ->chunkById($chunkSize, function ($models) use (&$total) {
                $models->each->backup();
                $total += $models->count();
                event(new ModelsBackuped(static::class, $total));
            });

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

    /**
     * backup the model in the database.
     *
     * @return bool|null
     */
    public function backup()
    {
        $backupTableName    = $this->getBackupTable();

        return DB::connection()->table($backupTableName)->insertOrIgnore($this->attributes);
    }
}
