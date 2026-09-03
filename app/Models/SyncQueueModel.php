<?php

namespace App\Models;

use CodeIgniter\Model;

class SyncQueueModel extends Model
{
    protected $table = 'sync_queue';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'task_id',
        'provider',
        'operation',
        'status',
        'idempotency_key',
        'payload',

        'attempts',
        'max_attempts',

        'next_attempt_at',

        'locked_at',
        'locked_by',

        'last_error',
    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}