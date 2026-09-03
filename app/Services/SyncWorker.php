<?php

namespace App\Services;

use App\Models\SyncLogModel;
use App\Models\SyncQueueModel;
use App\Models\TaskModel;
use Throwable;

class SyncWorker
{
    private SyncQueueModel $queue;
    private TaskModel $tasks;
    private SyncLogModel $logs;
    private GitHubService $github;

    public function __construct()
    {
        $this->queue = new SyncQueueModel();
        $this->tasks = new TaskModel();
        $this->logs = new SyncLogModel();
        $this->github = new GitHubService();
    }

    /**
     * Process pending queue items.
     */
    public function process(int $limit = 10): array
    {
        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $quarantined = 0;

        for ($i = 0; $i < $limit; $i++) {

            $item = $this->getNextQueueItem();

            if (!$item) {
                break;
            }

            $processed++;

            try {

                $result = $this->processItem($item);

                if ($result === 'succeeded') {
                    $succeeded++;
                } elseif ($result === 'quarantined') {
                    $quarantined++;
                }

            } catch (Throwable $e) {

                $failed++;
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'quarantined' => $quarantined,
        ];
    }

    /**
     * Get one pending queue item.
     *
     * Locking is handled using the processing state.
     */
    private function getNextQueueItem(): ?array
    {
        $db = db_connect();

        $db->transStart();

        $item = $this->queue
            ->where('status', 'pending')
            ->groupStart()
                ->where('next_attempt_at <=', date('Y-m-d H:i:s'))
                ->orWhere('next_attempt_at', null)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->first();

        if (!$item) {
            $db->transComplete();

            return null;
        }

        $workerId =
            gethostname() .
            '-' .
            getmypid() .
            '-' .
            bin2hex(random_bytes(4));

        $updated = $this->queue
            ->where('id', $item['id'])
            ->where('status', 'pending')
            ->set([
                'status' => 'processing',
                'locked_at' => date('Y-m-d H:i:s'),
                'locked_by' => $workerId,
                'updated_at' => date('Y-m-d H:i:s'),
            ])
            ->update();

        if (!$updated) {

            $db->transRollback();

            return null;
        }

        $db->transComplete();

        $item['status'] = 'processing';
        $item['locked_by'] = $workerId;

        return $item;
    }

    /**
     * Process a single queue item.
     */
    private function processItem(array $item): string
    {
        $queueId = (int) $item['id'];
        $taskId = (int) $item['task_id'];

        $task = $this->tasks->find($taskId);

        if (!$task) {

            $this->markFailed(
                $item,
                'Task no longer exists.'
            );

            return 'failed';
        }

        $operation = $item['operation'];

        try {

            switch ($operation) {

                case 'create':

                    $result =
                        $this->processCreate(
                            $item,
                            $task
                        );

                    break;

                case 'update':

                    $result =
                        $this->processUpdate(
                            $item,
                            $task
                        );

                    break;

                case 'delete':

                    $result =
                        $this->processDelete(
                            $item,
                            $task
                        );

                    break;

                default:

                    throw new \RuntimeException(
                        'Unsupported queue operation: ' .
                        $operation
                    );
            }

            $this->queue->update(
                $queueId,
                [
                    'status' => 'succeeded',
                    'locked_at' => null,
                    'locked_by' => null,
                    'last_error' => null,
                    'updated_at' =>
                        date('Y-m-d H:i:s'),
                ]
            );

            return 'succeeded';

        } catch (Throwable $e) {

            return $this->handleFailure(
                $item,
                $task,
                $e
            );
        }
    }

    /**
     * CREATE operation.
     */
    private function processCreate(
        array $item,
        array $task
    ): array {

        /*
         * Idempotency protection:
         *
         * If GitHub issue already exists locally,
         * do not create another issue.
         */
        if (
            !empty($task['provider_task_id']) ||
            !empty($task['provider_issue_number'])
        ) {

            return $task;
        }

        $payload =
            json_decode(
                $item['payload'] ?? '{}',
                true
            );

        if (!is_array($payload)) {
            $payload = [];
        }

        $title =
            $payload['title']
            ?? $task['title'];

        $description =
            $payload['description']
            ?? $task['description'];

        $startedAt = microtime(true);

        $githubIssue =
            $this->github->createIssue(
                $title,
                $description
            );

        $durationMs = (int) round(
            (microtime(true) - $startedAt) * 1000
        );

        if (
            empty($githubIssue['id']) ||
            empty($githubIssue['number'])
        ) {

            throw new \RuntimeException(
                'GitHub returned an invalid issue response.'
            );
        }

        $this->tasks->update(
            $task['id'],
            [
                'provider' =>
                    'github',

                'provider_task_id' =>
                    (string) $githubIssue['id'],

                'provider_issue_number' =>
                    (int) $githubIssue['number'],

                'provider_url' =>
                    $githubIssue['html_url']
                    ?? null,

                'provider_updated_at' =>
                    isset(
                        $githubIssue['updated_at']
                    )
                        ? date(
                            'Y-m-d H:i:s',
                            strtotime(
                                $githubIssue['updated_at']
                            )
                        )
                        : date('Y-m-d H:i:s'),

                'last_synced_at' =>
                    date('Y-m-d H:i:s'),

                'sync_status' =>
                    'synced',
            ]
        );

        $this->logs->insert([
            'task_id' =>
                $task['id'],

            'direction' =>
                'app_to_github',

            'operation' =>
                'create',

            'status' =>
                'success',

            'message' =>
                'GitHub issue created successfully.',

            'request_data' =>
                json_encode($payload),

            'response_data' =>
                json_encode([
                    'id' =>
                        $githubIssue['id'],

                    'number' =>
                        $githubIssue['number'],

                    'url' =>
                        $githubIssue['html_url']
                        ?? null,
                ]),

            'duration_ms' =>
                $durationMs,

            'created_at' =>
                date('Y-m-d H:i:s'),
        ]);

        return $githubIssue;
    }

    /**
     * UPDATE operation.
     */
    private function processUpdate(
        array $item,
        array $task
    ): array {

        if (
            empty(
                $task['provider_issue_number']
            )
        ) {

            throw new \RuntimeException(
                'Cannot update GitHub issue because provider issue number is missing.'
            );
        }

        $payload =
            json_decode(
                $item['payload'] ?? '{}',
                true
            );

        if (!is_array($payload)) {
            $payload = [];
        }

        $changes =
            $payload['changes']
            ?? [];

        $githubData = [];

        if (
            array_key_exists(
                'title',
                $changes
            )
        ) {

            $githubData['title'] =
                $changes['title'];
        }

        if (
            array_key_exists(
                'description',
                $changes
            )
        ) {

            $githubData['body'] =
                $changes['description'];
        }

        if (
            array_key_exists(
                'status',
                $changes
            )
        ) {

            $githubData['state'] =
                $changes['status'] ===
                'completed'
                    ? 'closed'
                    : 'open';
        }

        /*
         * If only priority/due date changed,
         * there may be nothing to send to GitHub
         * yet. Local state remains authoritative
         * for fields GitHub Issues doesn't directly
         * represent.
         */
        if (empty($githubData)) {

            $this->tasks->update(
                $task['id'],
                [
                    'sync_status' =>
                        'synced',

                    'last_synced_at' =>
                        date('Y-m-d H:i:s'),
                ]
            );

            return $task;
        }

        $startedAt = microtime(true);

        $githubIssue =
            $this->github->updateIssue(
                (int) $task[
                    'provider_issue_number'
                ],
                $githubData
            );

        $durationMs = (int) round(
            (microtime(true) - $startedAt)
            * 1000
        );

        $this->tasks->update(
            $task['id'],
            [
                'provider_updated_at' =>
                    isset(
                        $githubIssue['updated_at']
                    )
                        ? date(
                            'Y-m-d H:i:s',
                            strtotime(
                                $githubIssue['updated_at']
                            )
                        )
                        : date('Y-m-d H:i:s'),

                'last_synced_at' =>
                    date('Y-m-d H:i:s'),

                'sync_status' =>
                    'synced',
            ]
        );

        $this->logs->insert([
            'task_id' =>
                $task['id'],

            'direction' =>
                'app_to_github',

            'operation' =>
                'update',

            'status' =>
                'success',

            'message' =>
                'GitHub issue updated successfully.',

            'request_data' =>
                json_encode($githubData),

            'response_data' =>
                json_encode([
                    'number' =>
                        $githubIssue['number']
                        ?? null,

                    'updated_at' =>
                        $githubIssue['updated_at']
                        ?? null,
                ]),

            'duration_ms' =>
                $durationMs,

            'created_at' =>
                date('Y-m-d H:i:s'),
        ]);

        return $githubIssue;
    }

    /**
     * DELETE operation.
     *
     * Local delete is represented as a closed
     * GitHub issue because GitHub Issues does not
     * provide a normal delete operation for this flow.
     */
    private function processDelete(
        array $item,
        array $task
    ): array {

        if (
            empty(
                $task['provider_issue_number']
            )
        ) {

            /*
             * Task was never synced to GitHub.
             * Nothing needs to be deleted remotely.
             */
            return $task;
        }

        $startedAt = microtime(true);

        $githubIssue =
            $this->github->closeIssue(
                (int) $task[
                    'provider_issue_number'
                ]
            );

        $durationMs = (int) round(
            (microtime(true) - $startedAt)
            * 1000
        );

        $this->tasks->update(
            $task['id'],
            [
                'provider_updated_at' =>
                    isset(
                        $githubIssue['updated_at']
                    )
                        ? date(
                            'Y-m-d H:i:s',
                            strtotime(
                                $githubIssue['updated_at']
                            )
                        )
                        : date('Y-m-d H:i:s'),

                'last_synced_at' =>
                    date('Y-m-d H:i:s'),

                'sync_status' =>
                    'synced',
            ]
        );

        $this->logs->insert([
            'task_id' =>
                $task['id'],

            'direction' =>
                'app_to_github',

            'operation' =>
                'delete',

            'status' =>
                'success',

            'message' =>
                'Local task deleted and GitHub issue closed.',

            'request_data' =>
                json_encode([
                    'issue_number' =>
                        $task[
                            'provider_issue_number'
                        ],
                ]),

            'response_data' =>
                json_encode([
                    'state' =>
                        $githubIssue['state']
                        ?? null,
                ]),

            'duration_ms' =>
                $durationMs,

            'created_at' =>
                date('Y-m-d H:i:s'),
        ]);

        return $githubIssue;
    }

    /**
     * Handle failed queue item.
     */
    private function handleFailure(
        array $item,
        array $task,
        Throwable $e
    ): string {

        $attempts =
            ((int) $item['attempts']) + 1;

        $maxAttempts =
            (int) $item['max_attempts'];

        if ($attempts >= $maxAttempts) {

            $this->queue->update(
                $item['id'],
                [
                    'status' =>
                        'quarantined',

                    'attempts' =>
                        $attempts,

                    'locked_at' =>
                        null,

                    'locked_by' =>
                        null,

                    'last_error' =>
                        $e->getMessage(),

                    'updated_at' =>
                        date('Y-m-d H:i:s'),
                ]
            );

            $this->tasks->update(
                $task['id'],
                [
                    'sync_status' =>
                        'error',
                ]
            );

            $this->logs->insert([
                'task_id' =>
                    $task['id'],

                'direction' =>
                    'app_to_github',

                'operation' =>
                    $item['operation'],

                'status' =>
                    'failed',

                'message' =>
                    'Queue item quarantined after maximum attempts: ' .
                    $e->getMessage(),

                'request_data' =>
                    $item['payload'],

                'response_data' =>
                    null,

                'duration_ms' =>
                    null,

                'created_at' =>
                    date('Y-m-d H:i:s'),
            ]);

            return 'quarantined';
        }

        /*
         * Exponential backoff:
         *
         * attempt 1 → 2 minutes
         * attempt 2 → 4 minutes
         * attempt 3 → 8 minutes
         * attempt 4 → 16 minutes
         */
        $delayMinutes =
            2 ** $attempts;

        $nextAttempt =
            date(
                'Y-m-d H:i:s',
                time() +
                ($delayMinutes * 60)
            );

        $this->queue->update(
            $item['id'],
            [
                'status' =>
                    'pending',

                'attempts' =>
                    $attempts,

                'next_attempt_at' =>
                    $nextAttempt,

                'locked_at' =>
                    null,

                'locked_by' =>
                    null,

                'last_error' =>
                    $e->getMessage(),

                'updated_at' =>
                    date('Y-m-d H:i:s'),
            ]
        );

        $this->tasks->update(
            $task['id'],
            [
                'sync_status' =>
                    'error',
            ]
        );

        $this->logs->insert([
            'task_id' =>
                $task['id'],

            'direction' =>
                'app_to_github',

            'operation' =>
                $item['operation'],

            'status' =>
                'retry',

            'message' =>
                'Synchronization failed. Retry scheduled for ' .
                $nextAttempt .
                '. Error: ' .
                $e->getMessage(),

            'request_data' =>
                $item['payload'],

            'response_data' =>
                null,

            'duration_ms' =>
                null,

            'created_at' =>
                date('Y-m-d H:i:s'),
        ]);

        return 'failed';
    }

    /**
     * Mark an invalid queue item as failed.
     */
    private function markFailed(
        array $item,
        string $message
    ): void {

        $this->queue->update(
            $item['id'],
            [
                'status' =>
                    'failed',

                'last_error' =>
                    $message,

                'locked_at' =>
                    null,

                'locked_by' =>
                    null,

                'updated_at' =>
                    date('Y-m-d H:i:s'),
            ]
        );
    }
}