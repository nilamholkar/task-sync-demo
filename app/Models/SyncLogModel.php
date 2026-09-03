<?php

namespace App\Models;

use CodeIgniter\Model;

class SyncLogModel extends Model
{
    protected $table = 'sync_logs';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'task_id',

        'direction',
        'operation',
        'status',

        'message',

        'request_data',
        'response_data',

        'duration_ms',

        'created_at',
    ];

    protected $useTimestamps = false;
}