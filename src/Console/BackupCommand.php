<?php

namespace Pianzhou\Backupable\Console;

use Pianzhou\Backupable\Backupable;
use Pianzhou\Backupable\Massbackupable;
use Pianzhou\Backupable\ModelsBackuped;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;

class BackupCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'model:backup
                                {--model=* : Class names of the models to be backuped}
                                {--except=* : Class names of the models to be excluded from backuping}
                                {--chunk=1000 : The number of models to retrieve per chunk of models to be backuped}
                                {--pretend : Display the number of backupable records found instead of backuping them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup models that are no longer needed';

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function handle(Dispatcher $events)
    {
        $models = $this->models();

        if ($models->isEmpty()) {
            $this->components->info('No backupable models found.');

            return;
        }

        if ($this->option('pretend')) {
            $models->each(function ($model) {
                $this->pretendToBackup($model);
            });

            return;
        }

        $backuping = [];

        $events->listen(ModelsBackuped::class, function ($event) use (&$backuping) {
            if (! in_array($event->model, $backuping)) {
                $backuping[] = $event->model;

                $this->newLine();

                $this->components->info(sprintf('backuping [%s] records.', $event->model));
            }

            $this->components->twoColumnDetail($event->model, "{$event->count} records");
        });

        $models->each(function ($model) {
            $this->backupModel($model);
        });

        $events->forget(ModelsBackuped::class);
    }

    /**
     * Backup the given model.
     *
     * @param  string  $model
     * @return void
     */
    protected function backupModel(string $model)
    {
        $instance = new $model;

        $chunkSize = property_exists($instance, 'backupableChunkSize')
            ? $instance->backupableChunkSize
            : $this->option('chunk');

        $total = $this->isBackupable($model)
            ? $instance->backupAll($chunkSize)
            : 0;

        if ($total == 0) {
            $this->components->info("No backupable [$model] records found.");
        }
    }

    /**
     * Determine the models that should be backuped.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function models()
    {
        if (! empty($models = $this->option('model'))) {
            return collect($models)->filter(function ($model) {
                return class_exists($model);
            })->values();
        }

        $except = $this->option('except');

        if (! empty($models) && ! empty($except)) {
            throw new InvalidArgumentException('The --models and --except options cannot be combined.');
        }

        return collect((new Finder)->in($this->getDefaultPath())->files()->name('*.php'))
            ->map(function ($model) {
                $namespace = $this->laravel->getNamespace();

                return $namespace.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    Str::after($model->getRealPath(), realpath(app_path()).DIRECTORY_SEPARATOR)
                );
            })->when(! empty($except), function ($models) use ($except) {
                return $models->reject(function ($model) use ($except) {
                    return in_array($model, $except);
                });
            })->filter(function ($model) {
                return $this->isBackupable($model);
            })->filter(function ($model) {
                return class_exists($model);
            })->values();
    }

    /**
     * Get the default path where models are located.
     *
     * @return string|string[]
     */
    protected function getDefaultPath()
    {
        return app_path('Models');
    }

    /**
     * Determine if the given model class is backupable.
     *
     * @param  string  $model
     * @return bool
     */
    protected function isBackupable($model)
    {
        $uses = class_uses_recursive($model);

        return in_array(Backupable::class, $uses) || in_array(Massbackupable::class, $uses);
    }

    /**
     * Display how many models will be backuped.
     *
     * @param  string  $model
     * @return void
     */
    protected function pretendToBackup($model)
    {
        $instance = new $model;

        $count = $instance->backupable()->count();

        if ($count === 0) {
            $this->components->info("No backupable [$model] records found.");
        } else {
            $this->components->info("{$count} [{$model}] records will be backuped.");
        }
    }
}
