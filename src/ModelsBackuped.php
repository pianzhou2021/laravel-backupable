<?php

namespace Pianzhou\Backupable;

class ModelsBackuped
{
    /**
     * The class name of the model that was backuped.
     *
     * @var string
     */
    public $model;

    /**
     * The number of backuped records.
     *
     * @var int
     */
    public $count;

    /**
     * Create a new event instance.
     *
     * @param  string  $model
     * @param  int  $count
     * @return void
     */
    public function __construct($model, $count)
    {
        $this->model = $model;
        $this->count = $count;
    }
}
