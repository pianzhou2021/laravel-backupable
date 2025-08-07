<?php

namespace Pianzhou\Backupable;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait MonthlyMassBackupable
{
    use MassBackupable;

    public $backupDateFieldName = 'created_at';
    public $backupMonth = 7;

    /**
     * backup interval
     * @return Carbon[]
     */
    public function getBackupInterval(): array
    {
        return  [
            Carbon::today()->startOfMonth()->subMonthsWithoutOverflow($this->backupMonth),
            Carbon::today()->endOfMonth()->subMonthsWithoutOverflow($this->backupMonth)
        ];
    }

    /**
     * Backup all backupable models in the database.
     *
     * @param int $chunkSize
     * @return int
     */
    public function backupAll(int $chunkSize = 1000)
    {
        $total = $this->backupable()->count();
        if ($total == 0) {
            return 0;
        }
        list($startDate,) = $this->getBackupInterval();
        $tableSuffix = $startDate->format('ym');
        $backupTableName = sprintf('%s_%s', $this->getTable(), $tableSuffix);
        if (Schema::hasTable($backupTableName)) {
            if ($this->isTableChanged($this->getTable(), $backupTableName)) {
                Log::warning('backup table ' . $backupTableName . ' already exists');
                return 0;
            }
        } else {
            $this->duplicateTable($this->getTable(), $backupTableName);
        }

        $maxId = $this->backupable()->max($this->getKeyName());
        $minId = $this->backupable()->min($this->getKeyName());

        if (!$maxId || !$minId) {
            return 0;
        }
        $query = $this->backupable()->orderBy($this->getKeyName());

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

        event(new ModelsBackuped(static::class, $total));
        return $total;
    }

    /**
     * Get the backupable model query.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function backupable()
    {
        $interval = $this->getBackupInterval();
        return static::where($this->backupDateFieldName, '>=', $interval[0]->toDateTimeString())
            ->where($this->backupDateFieldName, '<=', $interval[1]->toDateTimeString());
    }
}
