<?php

namespace App\Models;

use CodeIgniter\Model;

class SyncConflictModel extends Model
{
    protected $table = 'sync_conflicts';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'task_id',
        'provider',
        'local_version',

        'local_snapshot',
        'provider_snapshot',

        'conflict_type',

        'status',
        'resolution',

        'resolved_snapshot',

        'created_at',
        'resolved_at',
    ];

    protected $useTimestamps = false;
}