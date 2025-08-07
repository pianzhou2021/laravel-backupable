<?php

namespace Pianzhou\Backupable\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Pianzhou\Backupable\MonthlyMassBackupable;
use Pianzhou\Backupable\ModelsBackuped;
use Illuminate\Foundation\Testing\RefreshDatabase;

// 创建测试模型
class TestModel extends Model
{
    use MonthlyMassBackupable;

    protected $table = 'test_models';
    protected $guarded = [];

    public function getTableDateFieldName()
    {
        return 'created_at';
    }

    public function backupable()
    {
        return $this->where('created_at', '<=', Carbon::today()->subMonths(6)->format('Y-m-d H:i:s'));
    }
}

class MonthlyMassBackupableTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_empty_backup_data_dont_creates_backup_table_with_date_suffix()
    {
        // 准备测试数据
        $data = [
            ['name' => '测试数据1', 'created_at' => now()->subMonths(1)],
            ['name' => '测试数据1', 'created_at' => now()->subMonths(1)],
            ['name' => '测试数据1', 'created_at' => now()->subMonths(1)],
            ['name' => '测试数据1', 'created_at' => now()->subMonths(1)],
            ['name' => '测试数据2', 'created_at' => now()->subMonths(2)],
        ];
        TestModel::insert($data);

        // 执行备份
        $backModel = new TestModel();
        $needBackupCount = $backModel->backupable()->count();
        $this->assertEquals($needBackupCount, 0);
        $backModel->backupAll();

        // 验证备份表是否创建
        $backupDate = Carbon::now()->subMonths(7)->format('ym');
        $backupTableName = $backModel->getTable() . '_' . $backupDate;
        $this->assertFalse(Schema::hasTable($backupTableName));
        // 验证备份数据
        $this->assertDatabaseCount($backModel->getTable(), count: count($data));
    }

    /** @test */
    public function it_creates_backup_table_with_date_suffix()
    {
        $eventBackedUp = 0;
        Event::listen(ModelsBackuped::class, function (ModelsBackuped $event) use (&$eventBackedUp) {
            $eventBackedUp += $event->count;
        });

        $backModel = new TestModel();
        // 准备测试数据
        $backupDataCount = 0;
        $tables = [];
        $data = [
            [
                'name' => '测试数据',
                'created_at' => now(),
            ]
        ];
        for ($m = 12; $m > 6; $m--) {
            for ($i = 0; $i < 100000; $i++) {
                $c = now()->subMonths($m)->subSeconds($i);
                $data[] = [
                    'name' => '测试数据' . $i,
                    'created_at' => $c,
                ];
                $tables[$backModel->getTable() . '_' . $c->format('ym')] = $backModel->getTable() . '_' . $c->format('ym');
                $backupDataCount++;
            };
            foreach (array_chunk($data, 2000) as   $chunkData) {
                TestModel::insert($chunkData);
            }
            $data = [];
        }

        // 执行备份
        $backupDate = Carbon::now()->subMonths(7)->format('ym');
        $needBackupCount = $backModel->backupable()->count();
        $this->assertEquals($backupDataCount, $needBackupCount);
        $backModel->backupAll();
        // 验证备份表是否创建
        $backupTableName = $backModel->getTable() . '_' . $backupDate;
        $this->assertTrue(Schema::hasTable($backupTableName));
        // 验证备份数据
        $this->assertDatabaseCount($backModel->getTable(), count: $backupDataCount + 1);
        $totalBackedUp = 0;
        foreach ($tables as $table) {
            $totalBackedUp += DB::table($table)->count();
        }
        // 验证备份数据
        $this->assertEquals($backupDataCount, $totalBackedUp);
        $this->assertEquals($backupDataCount, $eventBackedUp);
    }
}
