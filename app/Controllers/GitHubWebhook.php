<?php

namespace App\Controllers;

use App\Services\GitHubWebhookService;
use Throwable;

class GitHubWebhook extends BaseController
{
    public function receive()
    {
        $payload =
            $this->request->getBody();

        $signature =
            $this->request->getHeaderLine(
                'X-Hub-Signature-256'
            );

        $eventId =
            $this->request->getHeaderLine(
                'X-GitHub-Delivery'
            );

        $eventName =
            $this->request->getHeaderLine(
                'X-GitHub-Event'
            );

        try {

            $service =
                new GitHubWebhookService();

            if (
                !$service->verifySignature(
                    $payload,
                    $signature
                )
            ) {

                return $this->response
                    ->setStatusCode(401)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Invalid webhook signature.',
                    ]);
            }

            $result =
                $service->process(
                    $eventId,
                    $eventName,
                    $payload
                );

            return $this->response->setJSON(
                $result
            );

        } catch (Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'error' =>
                        $e->getMessage(),
                ]);
        }
    }
}