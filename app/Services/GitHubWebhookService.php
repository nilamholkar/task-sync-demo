<?php

namespace App\Services;

use App\Models\TaskModel;
use App\Models\SyncConflictModel;
use App\Models\WebhookEventModel;
use RuntimeException;

class GitHubWebhookService
{
    private WebhookEventModel $events;
    private TaskModel $tasks;

    private string $provider = 'github';

    public function __construct()
    {
        $this->events = new WebhookEventModel();
        $this->tasks = new TaskModel();
    }

    /**
     * Verify GitHub webhook signature.
     */
    public function verifySignature(
        string $payload,
        string $signature
    ): bool {

        $secret =
            (string) env(
                'github.webhook_secret'
            );

        if ($secret === '') {
            throw new RuntimeException(
                'GitHub webhook secret is not configured.'
            );
        }

        if ($signature === '') {
            return false;
        }

        if (
            !str_starts_with(
                $signature,
                'sha256='
            )
        ) {
            return false;
        }

        $expected =
            'sha256=' .
            hash_hmac(
                'sha256',
                $payload,
                $secret
            );

        return hash_equals(
            $expected,
            $signature
        );
    }

    /**
     * Process incoming GitHub webhook.
     */
    public function process(
        string $eventId,
        string $eventName,
        string $payload
    ): array {

        if ($eventId === '') {
            throw new RuntimeException(
                'GitHub webhook event ID is missing.'
            );
        }

        $data =
            json_decode(
                $payload,
                true
            );

        if (!is_array($data)) {
            throw new RuntimeException(
                'Invalid GitHub webhook JSON.'
            );
        }

        /*
         * Idempotency:
         *
         * X-GitHub-Delivery is stored as
         * provider + event_id.
         */
        $existing =
            $this->events
                ->where(
                    'provider',
                    $this->provider
                )
                ->where(
                    'event_id',
                    $eventId
                )
                ->first();

        if ($existing) {

            return [
                'success' => true,

                'status' => 'ignored',

                'message' =>
                    'Duplicate webhook event ignored.',

                'event_id' =>
                    $eventId,
            ];
        }

        /*
         * Determine GitHub action.
         */
        $action =
            $data['action'] ?? null;

        /*
         * Store the webhook BEFORE processing it.
         *
         * Unique(provider,event_id) prevents
         * duplicate deliveries.
         */
        $eventIdDb =
            $this->events->insert([
                'provider' =>
                    $this->provider,

                'event_id' =>
                    $eventId,

                'event_name' =>
                    $eventName,

                'action' =>
                    $action,

                'delivery_status' =>
                    'received',

                'payload' =>
                    $payload,

                'error_message' =>
                    null,

                'received_at' =>
                    date('Y-m-d H:i:s'),

                'processed_at' =>
                    null,
            ]);

        if (!$eventIdDb) {

            /*
             * Another concurrent request may have
             * inserted the same event.
             */
            $existing =
                $this->events
                    ->where(
                        'provider',
                        $this->provider
                    )
                    ->where(
                        'event_id',
                        $eventId
                    )
                    ->first();

            if ($existing) {

                return [
                    'success' => true,

                    'status' => 'ignored',

                    'message' =>
                        'Duplicate webhook event ignored.',

                    'event_id' =>
                        $eventId,
                ];
            }

            throw new RuntimeException(
                'Unable to store webhook event.'
            );
        }

        try {

            /*
             * Only process Issue events.
             */
            if (
                $eventName !== 'issues'
            ) {

                $this->events->update(
                    $eventIdDb,
                    [
                        'delivery_status' =>
                            'ignored',

                        'processed_at' =>
                            date(
                                'Y-m-d H:i:s'
                            ),
                    ]
                );

                return [
                    'success' => true,

                    'status' => 'ignored',

                    'message' =>
                        'Webhook event type is not supported.',

                    'event_id' =>
                        $eventId,
                ];
            }

            $issue =
                $data['issue']
                ?? null;

            if (!is_array($issue)) {
                throw new RuntimeException(
                    'GitHub issue data is missing.'
                );
            }

            $this->processIssue(
                $issue,
                $action
            );

            $this->events->update(
                $eventIdDb,
                [
                    'delivery_status' =>
                        'processed',

                    'processed_at' =>
                        date(
                            'Y-m-d H:i:s'
                        ),
                ]
            );

            return [
                'success' => true,

                'status' => 'processed',

                'event_id' =>
                    $eventId,

                'action' =>
                    $action,
            ];

        } catch (\Throwable $e) {

            $this->events->update(
                $eventIdDb,
                [
                    'delivery_status' =>
                        'failed',

                    'error_message' =>
                        $e->getMessage(),

                    'processed_at' =>
                        date(
                            'Y-m-d H:i:s'
                        ),
                ]
            );

            throw $e;
        }
    }

    /**
     * Process a GitHub Issue event.
     */
    private function processIssue(array $issue, string $action): void
    {
        if (empty($issue['id']) || empty($issue['number'])) {
            throw new \RuntimeException('Invalid GitHub issue payload.');
        }

        $providerTaskId = (string) $issue['id'];
        $issueNumber = (int) $issue['number'];

        $taskModel = new TaskModel();
        $conflictModel = new SyncConflictModel();

        // Find local task using GitHub issue ID first.
        $task = $taskModel
            ->where('provider', 'github')
            ->where('provider_task_id', $providerTaskId)
            ->first();

        // Fallback to issue number.
        if (!$task) {
            $task = $taskModel
                ->where('provider', 'github')
                ->where('provider_issue_number', $issueNumber)
                ->first();
        }

        /*
        * Unknown GitHub issue.
        *
        * This means the issue was created directly in GitHub.
        * Create a corresponding local task.
        */
        if (!$task) {
            $this->createLocalTask($issue);
            return;
        }

        /*
        * Tombstone protection.
        *
        * If the task was deleted locally, an old GitHub webhook
        * must NOT recreate or restore it.
        */
        if (!empty($task['deleted_at'])) {
            return;
        }

        /*
        * If there is a pending local change, GitHub also changed.
        *
        * This is a real conflict.
        */
        if ($task['sync_status'] === 'pending') {

            $localSnapshot = [
                'id' => (int) $task['id'],
                'title' => $task['title'],
                'description' => $task['description'],
                'status' => $task['status'],
                'priority' => $task['priority'],
                'due_date' => $task['due_date'],
                'version' => (int) $task['version'],
                'local_updated_at' => $task['local_updated_at'],
            ];

            $providerSnapshot = [
                'id' => $issue['id'],
                'number' => $issue['number'],
                'title' => $issue['title'] ?? '',
                'description' => $issue['body'] ?? '',
                'state' => $issue['state'] ?? 'open',
                'html_url' => $issue['html_url'] ?? null,
                'updated_at' => $issue['updated_at'] ?? null,
            ];

            /*
            * Avoid creating duplicate open conflicts for the
            * same task/version.
            */
            $existingConflict = $conflictModel
                ->where('task_id', $task['id'])
                ->where('local_version', $task['version'])
                ->where('status', 'open')
                ->first();

            if (!$existingConflict) {

                $conflictModel->insert([
                    'task_id' => $task['id'],
                    'provider' => 'github',
                    'local_version' => $task['version'],
                    'local_snapshot' => json_encode($localSnapshot),
                    'provider_snapshot' => json_encode($providerSnapshot),
                    'conflict_type' => 'concurrent_update',
                    'status' => 'open',
                    'resolution' => null,
                    'resolved_snapshot' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            /*
            * Mark task as conflicted.
            */
            $taskModel->update($task['id'], [
                'sync_status' => 'conflict',
            ]);

            return;
        }

        /*
        * Normal GitHub -> Local synchronization.
        */

        $localStatus = ($issue['state'] ?? 'open') === 'closed'
            ? 'completed'
            : 'pending';

        $providerUpdatedAt = null;

        if (!empty($issue['updated_at'])) {
            $timestamp = strtotime($issue['updated_at']);

            if ($timestamp !== false) {
                $providerUpdatedAt = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $taskModel->update($task['id'], [
            'title' => $issue['title'] ?? $task['title'],
            'description' => $issue['body'] ?? null,
            'status' => $localStatus,
            'provider' => 'github',
            'provider_task_id' => $providerTaskId,
            'provider_issue_number' => $issueNumber,
            'provider_url' => $issue['html_url'] ?? $task['provider_url'],
            'provider_updated_at' => $providerUpdatedAt,
            'last_synced_at' => date('Y-m-d H:i:s'),
            'sync_status' => 'synced',
        ]);
    }

    /**
     * Create a local task from a new GitHub issue.
     */
    private function createLocalTask(
        array $issue
    ): void {

        $status =
            ($issue['state'] ?? 'open')
                === 'closed'
                    ? 'completed'
                    : 'pending';

        $providerUpdatedAt = null;

        if (
            !empty(
                $issue['updated_at']
            )
        ) {

            $providerUpdatedAt =
                date(
                    'Y-m-d H:i:s',
                    strtotime(
                        $issue['updated_at']
                    )
                );
        }

        $now =
            date('Y-m-d H:i:s');

        $this->tasks->insert([
            'title' =>
                $issue['title']
                ?? 'Untitled GitHub Issue',

            'description' =>
                $issue['body']
                ?? null,

            'status' =>
                $status,

            'priority' =>
                'medium',

            'due_date' =>
                null,

            'provider' =>
                $this->provider,

            'provider_task_id' =>
                (string) $issue['id'],

            'provider_issue_number' =>
                (int) $issue['number'],

            'provider_url' =>
                $issue['html_url']
                ?? null,

            'sync_status' =>
                'synced',

            'version' =>
                1,

            'local_updated_at' =>
                $now,

            'provider_updated_at' =>
                $providerUpdatedAt,

            'last_synced_at' =>
                $now,

            'deleted_at' =>
                null,
        ]);
    }
}