<?php

namespace App\Services;

use App\Models\SyncCheckpointModel;
use App\Models\SyncLogModel;
use App\Models\TaskModel;
use RuntimeException;
use Throwable;

class GitHubSyncService
{
    private GitHubService $github;
    private TaskModel $tasks;
    private SyncCheckpointModel $checkpoints;
    private SyncLogModel $logs;

    private string $provider = 'github';

    public function __construct()
    {
        $this->github = new GitHubService();

        $this->tasks = new TaskModel();

        $this->checkpoints = new SyncCheckpointModel();

        $this->logs = new SyncLogModel();
    }

    /**
     * Initial GitHub -> Local synchronization.
     *
     * The process is page based so it can resume
     * from the last successfully processed page.
     */
    public function initialSync(): array
    {
        $repository =
            env('github.owner') . '/' .
            env('github.repo');

        $checkpoint = $this->checkpoints
            ->where('provider', $this->provider)
            ->where('repository', $repository)
            ->where(
                'direction',
                'github_to_app'
            )
            ->first();

        /*
         * Create checkpoint if this is the first sync.
         */
        if (!$checkpoint) {

            $checkpointId =
                $this->checkpoints->insert([
                    'provider' => $this->provider,

                    'repository' => $repository,

                    'direction' => 'github_to_app',

                    'cursors' => null,

                    'page' => 1,

                    'last_provider_updated_at' => null,

                    'status' => 'running',

                    'last_error' => null,

                    'updated_at' =>
                        date('Y-m-d H:i:s'),
                ]);

            $checkpoint =
                $this->checkpoints->find(
                    $checkpointId
                );
        }

        /*
         * If previous sync completed,
         * start again from page 1.
         *
         * This is useful when running an initial
         * sync again after new GitHub issues exist.
         */
        if ($checkpoint['status'] === 'completed') {

            $this->checkpoints->update(
                $checkpoint['id'],
                [
                    'page' => 1,
                    'cursors' => null,
                    'status' => 'running',
                    'last_error' => null,
                    'updated_at' =>
                        date('Y-m-d H:i:s'),
                ]
            );

            $checkpoint['page'] = 1;
        }

        $page = max(
            1,
            (int) $checkpoint['page']
        );

        $totalProcessed = 0;

        try {

            while (true) {

                $startedAt = microtime(true);

                /*
                 * GitHub allows up to 100 items per page.
                 */
                $issues =
                    $this->github->getIssues(
                        page: $page,
                        perPage: 100
                    );

                /*
                 * Empty page means synchronization
                 * has reached the end.
                 */
                if (empty($issues)) {

                    $this->checkpoints->update(
                        $checkpoint['id'],
                        [
                            'status' => 'completed',

                            'last_error' => null,

                            'updated_at' =>
                                date('Y-m-d H:i:s'),
                        ]
                    );

                    break;
                }

                foreach ($issues as $issue) {

                    /*
                     * GitHub Issues API can return
                     * pull requests.
                     *
                     * We only synchronize actual issues.
                     */
                    if (isset($issue['pull_request'])) {
                        continue;
                    }

                    $this->syncIssue($issue);

                    $totalProcessed++;
                }

                $durationMs = (int) round(
                    (microtime(true) - $startedAt)
                    * 1000
                );

                /*
                 * Save checkpoint AFTER successfully
                 * processing the entire page.
                 */
                $lastProviderUpdatedAt = null;

                foreach ($issues as $issue) {

                    if (
                        isset(
                            $issue['updated_at']
                        )
                    ) {

                        $lastProviderUpdatedAt =
                            $issue['updated_at'];
                    }
                }

                $this->checkpoints->update(
                    $checkpoint['id'],
                    [
                        'page' => $page + 1,

                        'last_provider_updated_at' =>
                            $lastProviderUpdatedAt
                                ? date(
                                    'Y-m-d H:i:s',
                                    strtotime(
                                        $lastProviderUpdatedAt
                                    )
                                )
                                : null,

                        'status' => 'running',

                        'last_error' => null,

                        'updated_at' =>
                            date('Y-m-d H:i:s'),
                    ]
                );

                /*
                 * Log successful page processing.
                 */
                $this->logs->insert([
                    'task_id' => null,

                    'direction' =>
                        'github_to_app',

                    'operation' =>
                        'initial_sync_page',

                    'status' =>
                        'success',

                    'message' =>
                        'Processed GitHub page ' .
                        $page .
                        ' containing ' .
                        count($issues) .
                        ' records.',

                    'request_data' =>
                        json_encode([
                            'page' => $page,
                            'per_page' => 100,
                        ]),

                    'response_data' =>
                        json_encode([
                            'count' =>
                                count($issues),
                        ]),

                    'duration_ms' =>
                        $durationMs,

                    'created_at' =>
                        date('Y-m-d H:i:s'),
                ]);

                /*
                 * Less than 100 means this was
                 * the last page.
                 */
                if (count($issues) < 100) {

                    $this->checkpoints->update(
                        $checkpoint['id'],
                        [
                            'status' => 'completed',

                            'last_error' => null,

                            'updated_at' =>
                                date('Y-m-d H:i:s'),
                        ]
                    );

                    break;
                }

                $page++;
            }

            return [
                'success' => true,

                'processed' =>
                    $totalProcessed,

                'checkpoint_page' =>
                    $page,
            ];

        } catch (Throwable $e) {

            /*
             * Keep the current page in the
             * checkpoint so the next execution
             * can resume from it.
             */
            $this->checkpoints->update(
                $checkpoint['id'],
                [
                    'page' => $page,

                    'status' => 'failed',

                    'last_error' =>
                        $e->getMessage(),

                    'updated_at' =>
                        date('Y-m-d H:i:s'),
                ]
            );

            throw $e;
        }
    }

    /**
     * Synchronize one GitHub issue into local tasks.
     */
    private function syncIssue(array $issue): void
    {
        if (
            empty($issue['number']) ||
            empty($issue['id'])
        ) {
            throw new RuntimeException(
                'Invalid GitHub issue received.'
            );
        }

        $providerTaskId =
            (string) $issue['id'];

        /*
         * First try to find the task by
         * provider + provider_task_id.
         */
        $existing = $this->tasks
            ->where(
                'provider',
                $this->provider
            )
            ->where(
                'provider_task_id',
                $providerTaskId
            )
            ->first();

        /*
         * If not found, also check issue number.
         * This protects against accidental duplicates
         * if provider_task_id was not stored earlier.
         */
        if (!$existing) {

            $existing = $this->tasks
                ->where(
                    'provider',
                    $this->provider
                )
                ->where(
                    'provider_issue_number',
                    (int) $issue['number']
                )
                ->first();
        }

        $status =
            ($issue['state'] ?? 'open') === 'closed'
                ? 'completed'
                : 'pending';

        $description =
            $issue['body'] ?? null;

        $updatedAt =
            isset($issue['updated_at'])
                ? date(
                    'Y-m-d H:i:s',
                    strtotime(
                        $issue['updated_at']
                    )
                )
                : date('Y-m-d H:i:s');

        /*
         * Existing task:
         * update provider-side information.
         */
        if ($existing) {

            /*
             * Do not update a locally deleted task.
             * The tombstone prevents an old/new GitHub
             * event from recreating it.
             */
            if (
                !empty(
                    $existing['deleted_at']
                )
            ) {
                return;
            }

            /*
             * If the local task has pending changes,
             * don't overwrite them silently.
             *
             * Conflict handling will be expanded
             * in the next step.
             */
            if (
                $existing['sync_status'] ===
                'pending'
            ) {

                $this->logs->insert([
                    'task_id' =>
                        $existing['id'],

                    'direction' =>
                        'github_to_app',

                    'operation' =>
                        'update',

                    'status' =>
                        'conflict',

                    'message' =>
                        'GitHub issue changed while local task has pending changes.',

                    'request_data' =>
                        json_encode($issue),

                    'response_data' =>
                        null,

                    'duration_ms' =>
                        null,

                    'created_at' =>
                        date('Y-m-d H:i:s'),
                ]);

                return;
            }

            $this->tasks->update(
                $existing['id'],
                [
                    'title' =>
                        $issue['title'],

                    'description' =>
                        $description,

                    'status' =>
                        $status,

                    'provider_url' =>
                        $issue['html_url']
                            ?? null,

                    'provider_updated_at' =>
                        $updatedAt,

                    'last_synced_at' =>
                        date('Y-m-d H:i:s'),

                    'sync_status' =>
                        'synced',
                ]
            );

            return;
        }

        /*
         * New GitHub issue.
         */
        $this->tasks->insert([
            'title' =>
                $issue['title'],

            'description' =>
                $description,

            'status' =>
                $status,

            'priority' =>
                'medium',

            'due_date' =>
                null,

            'provider' =>
                $this->provider,

            'provider_task_id' =>
                $providerTaskId,

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
                date('Y-m-d H:i:s'),

            'provider_updated_at' =>
                $updatedAt,

            'last_synced_at' =>
                date('Y-m-d H:i:s'),

            'deleted_at' =>
                null,
        ]);
    }
}