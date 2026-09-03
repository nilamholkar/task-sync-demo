<?php

namespace App\Controllers;

use App\Services\GitHubSyncService;
use Throwable;

class SyncTest extends BaseController
{
    public function githubToApp()
    {
        try {

            $sync = new GitHubSyncService();

            $result = $sync->initialSync();

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