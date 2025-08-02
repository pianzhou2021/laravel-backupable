<?php

namespace Pianzhou\Backupable;

use LogicException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pianzhou\Backupable\ModelsBackuped;

trait DateBackupable
{
    use BackupableTable;

    abstract public function getTableDateFieldName();

    /**
     * Backup all backupable models in the database.
     *
     * @param  int  $chunkSize
     * @return int
     */
    public function backupAll(int $chunkSize = 1000)
    {
        $dates = $this->backupable()
            ->groupBy(
                DB::raw(
                    'DATE_FORMAT(' . $this->getTableDateFieldName() . ",'%Y-%m')"
                )
            )
            ->select(
                DB::raw(
                    'DATE_FORMAT(' . $this->getTableDateFieldName()
                        . ",'%Y-%m') as date"
                )
            )->pluck('date');
        $total = $this->backupable()->count();

        $backedup = 0;
        $dates->each(function ($tableDateString) use ($chunkSize, $backedup) {
            $tableDate = Carbon::parse($tableDateString);
            $tableSuffix = $tableDate->format('ym');
            // 生成备份表
            $backupTableName = sprintf(
                '%s_%s',
                $this->getTable(),
                $tableSuffix
            );
            if (! Schema::hasTable($backupTableName)) {
                $this->duplicateTable(
                    $this->getTable(),
                    $backupTableName
                );
            }

            $query = $this->backupable()
                ->where($this->getTableDateFieldName(), '<', $tableDate->clone()->addMonth()->format('Y-m-d H:i:s'))
                ->where($this->getTableDateFieldName(), '>=', $tableDate->clone()->format('Y-m-d H:i:s'))
                ->orderBy($this->getKeyName());
            $dateTotal = $query->count();
            $page = intval(ceil($dateTotal / $chunkSize));
            for ($i = 1; $i <= $page; $i++) {
                $sql = sprintf(
                    'INSERT IGNORE INTO `%s` %s',
                    $backupTableName,
                    $query
                        ->limit($chunkSize)
                        ->offset(($i - 1) * $chunkSize)
                        ->toSql(),
                );
                DB::statement($sql, $query->getBindings());
            }
        });

        event(
            new ModelsBackuped(
                static::class,
                $total
            )
        );
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
