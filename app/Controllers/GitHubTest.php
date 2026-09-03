<?php

namespace App\Controllers;

use App\Services\GitHubService;
use Throwable;

class GitHubTest extends BaseController
{
    public function index()
    {
        try {

            $github = new GitHubService();

            $issues = $github->getIssues(
                page: 1,
                perPage: 100
            );

            return $this->response->setJSON([
                'success' => true,
                'count'   => count($issues),
                'issues'  => $issues,
            ]);

        } catch (Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'error'   => $e->getMessage(),
                ]);
        }
    }
}