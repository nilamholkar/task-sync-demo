<?php

namespace App\Models;

use CodeIgniter\Model;

class TaskModel extends Model
{
    protected $table = 'tasks';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',

        'provider',
        'provider_task_id',
        'provider_issue_number',
        'provider_url',

        'sync_status',
        'version',

        'local_updated_at',
        'provider_updated_at',
        'last_synced_at',

        'deleted_at',
    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}