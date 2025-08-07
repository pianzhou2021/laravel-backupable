<?php

namespace Pianzhou\Backupable;

use LogicException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait MonthlyMassBackupable
{
    use BackupableTable;

    abstract public function getTableDateFieldName();

    /**
     * Backup all backupable models in the database.
     *
     * @param int $chunkSize
     * @return int
     */
    public function backupAll(int $chunkSize = 1000)
    {
        $total = $this->backupable()->count();
        $this->backupable()
            ->groupBy(DB::raw('DATE_FORMAT(' . $this->getTableDateFieldName() . ",'%Y-%m')"))
            ->select(DB::raw('DATE_FORMAT(' . $this->getTableDateFieldName() . ",'%Y-%m') as date"))
            ->pluck('date')
            ->each(function ($tableDateString) use ($chunkSize) {
                $tableDate = Carbon::parse($tableDateString);
                $tableSuffix = $tableDate->format('ym');
                // 生成备份表
                $backupTableName = sprintf('%s_%s', $this->getTable(), $tableSuffix);
                if (!Schema::hasTable($backupTableName)) {
                    $this->duplicateTable(
                        $this->getTable(),
                        $backupTableName
                    );
                } else {
                    Log::warning('backup table ' . $backupTableName . ' already exists');
                    return;
                }

                $maxId = $this->backupable()
                    ->where($this->getTableDateFieldName(), '<', $tableDate->clone()->addMonth()->format('Y-m-d'))
                    ->where($this->getTableDateFieldName(), '>=', $tableDate->format('Y-m-d'))
                    ->max($this->getKeyName());

                $minId = $this->backupable()
                    ->where($this->getTableDateFieldName(), '<', $tableDate->clone()->addMonth()->format('Y-m-d'))
                    ->where($this->getTableDateFieldName(), '>=', $tableDate->format('Y-m-d'))
                    ->min($this->getKeyName());

                if (!$maxId || !$minId) {
                    return;
                }
                $query = $this->backupable()
                    ->where($this->getTableDateFieldName(), '<', $tableDate->clone()->addMonth()->format('Y-m-d'))
                    ->where($this->getTableDateFieldName(), '>=', $tableDate->format('Y-m-d'))
                    ->orderBy($this->getKeyName());

                $currentId = $minId;
                while ($currentId <= $maxId) {
                    $nextId = $currentId + $chunkSize - 1;
                    $nextId = min($maxId, $nextId);
                    $query = $this->backupable()->whereBetween('id', [$currentId, $nextId]);
                    $sql = sprintf(
                        'INSERT IGNORE INTO `%s` %s',
                        $backupTableName,
                        $query->toSql(),
                    );
                    DB::statement($sql, $query->getBindings());
                    $currentId = $nextId + 1;
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
