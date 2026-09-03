<?php

namespace App\Controllers;

use App\Services\SyncWorker;
use Throwable;

class WorkerTest extends BaseController
{
    public function index()
    {
        try {

            $worker = new SyncWorker();

            $result = $worker->process(10);

            return $this->response->setJSON(
                $result
            );

        } catch (Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
        }
    }
}