<?php

namespace Pianzhou\Backupable\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Pianzhou\Backupable\DateBackupable;
use Pianzhou\Backupable\ModelsBackuped;
use Illuminate\Foundation\Testing\RefreshDatabase;

// 创建测试模型
class TestModel extends Model
{
    use DateBackupable;

    protected $table = 'test_models';
    protected $guarded = [];

    public function getBackupTableName()
    {
        return $this->table;
    }

    public function getTableDateFieldName()
    {
        return 'created_at';
    }

    public function backupable()
    {
        return $this->where('created_at', '<=', Carbon::today()->subMonths(6)->format('Y-m-d H:i:s'));
    }
}

class DateBackupableTest extends TestCase
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
        // Event::fake();
        $eventBackedUp = 0;
        Event::listen(ModelsBackuped::class, function (ModelsBackuped $event) use (&$eventBackedUp) {
            $eventBackedUp += $event->count;
        });

        $backModel  = new TestModel();
        // 准备测试数据
        $backupDataCount = 10000;
        $tables = [];
        $data = [
            [
                'name' => '测试数据',
                'created_at' => now(),
            ]
        ];
        for ($i = 0; $i < $backupDataCount; $i++) {
            $c = now()->subMonths(random_int(7, 12))->subSeconds($i);
            $data[] = [
                'name' => '测试数据' . $i,
                'created_at' => $c,
            ];
            $tables[$backModel->getTable() . '_' . $c->format('ym')] = $backModel->getTable() . '_' . $c->format('ym');
        };

        TestModel::insert($data);

        // 执行备份
        $backupDate = Carbon::now()->subMonths(7)->format('ym');
        $needBackupCount = $backModel->backupable()->count();
        $this->assertEquals($backupDataCount, $needBackupCount);
        $backModel->backupAll();
        // 验证备份表是否创建
        $backupTableName = $backModel->getTable() . '_' . $backupDate;
        $this->assertTrue(Schema::hasTable($backupTableName));
        // 验证备份数据
        $this->assertDatabaseCount($backModel->getTable(), count: count($data));
        $totalBackedUp = 0;
        foreach ($tables as  $table) {
            $totalBackedUp += DB::table($table)->count();
        }
        // 验证备份数据
        $this->assertEquals(count($data) - 1, $totalBackedUp);
        $this->assertEquals(count($data) - 1, $eventBackedUp);
    }
}
