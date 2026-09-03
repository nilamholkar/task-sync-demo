<?php

namespace App\Models;

use CodeIgniter\Model;

class SyncCheckpointModel extends Model
{
    protected $table = 'sync_checkpoints';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'provider',
        'repository',
        'direction',

        'cursor',
        'page',

        'last_provider_updated_at',

        'status',
        'last_error',

        'updated_at',
    ];

    protected $useTimestamps = false;
}