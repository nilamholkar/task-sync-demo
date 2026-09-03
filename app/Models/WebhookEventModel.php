<?php

namespace App\Models;

use CodeIgniter\Model;

class WebhookEventModel extends Model
{
    protected $table = 'webhook_events';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'provider',
        'event_id',
        'event_name',
        'action',
        'delivery_status',
        'payload',
        'error_message',
        'received_at',
        'processed_at',
    ];

    protected $useTimestamps = false;
}