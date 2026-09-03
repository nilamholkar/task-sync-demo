<?php

namespace App\Controllers;

use App\Models\TaskModel;
use App\Models\SyncQueueModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class TaskApi extends BaseController
{
    private TaskModel $tasks;
    private SyncQueueModel $queue;

    public function __construct()
    {
        $this->tasks = new TaskModel();
        $this->queue = new SyncQueueModel();
    }

    /**
     * GET /api/tasks
     */
    public function index(): ResponseInterface
    {
        try {
            $search = trim(
                (string) $this->request->getGet('search')
            );

            $status = trim(
                (string) $this->request->getGet('status')
            );

            $syncStatus = trim(
                (string) $this->request->getGet('sync_status')
            );

            $builder = $this->tasks
                ->where('deleted_at', null);

            if ($search !== '') {

                $builder->groupStart()
                    ->like('title', $search)
                    ->orLike('description', $search)
                    ->groupEnd();
            }

            if ($status !== '') {
                $builder->where('status', $status);
            }

            if ($syncStatus !== '') {
                $builder->where(
                    'sync_status',
                    $syncStatus
                );
            }

            $tasks = $builder
                ->orderBy('id', 'DESC')
                ->findAll();

            return $this->response->setJSON([
                'success' => true,
                'count'   => count($tasks),
                'data'    => $tasks,
            ]);

        } catch (Throwable $e) {

            return $this->errorResponse(
                $e->getMessage()
            );
        }
    }

    /**
     * GET /api/tasks/{id}
     */
    public function show(int $id): ResponseInterface
    {
        try {

            $task = $this->tasks
                ->where('deleted_at', null)
                ->find($id);

            if (!$task) {

                return $this->response
                    ->setStatusCode(404)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Task not found.',
                    ]);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $task,
            ]);

        } catch (Throwable $e) {

            return $this->errorResponse(
                $e->getMessage()
            );
        }
    }

    /**
     * POST /api/tasks
     */
    public function create(): ResponseInterface
    {
        try {

            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                $data = [];
            }

            $title = trim(
                (string) ($data['title'] ?? '')
            );

            if ($title === '') {

                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Title is required.',
                    ]);
            }

            $status =
                $data['status'] ?? 'pending';

            $priority =
                $data['priority'] ?? 'medium';

            $allowedStatuses = [
                'pending',
                'in_progress',
                'completed',
            ];

            $allowedPriorities = [
                'low',
                'medium',
                'high',
            ];

            if (!in_array(
                $status,
                $allowedStatuses,
                true
            )) {

                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Invalid status.',
                    ]);
            }

            if (!in_array(
                $priority,
                $allowedPriorities,
                true
            )) {

                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Invalid priority.',
                    ]);
            }

            $now = date('Y-m-d H:i:s');

            $taskData = [
                'title' =>
                    $title,

                'description' =>
                    $data['description'] ?? null,

                'status' =>
                    $status,

                'priority' =>
                    $priority,

                'due_date' =>
                    $data['due_date'] ?? null,

                'provider' =>
                    null,

                'provider_task_id' =>
                    null,

                'provider_issue_number' =>
                    null,

                'provider_url' =>
                    null,

                'sync_status' =>
                    'pending',

                'version' =>
                    1,

                'local_updated_at' =>
                    $now,

                'provider_updated_at' =>
                    null,

                'last_synced_at' =>
                    null,

                'deleted_at' =>
                    null,
            ];

            $db = db_connect();

            $db->transStart();

            $taskId =
                $this->tasks->insert(
                    $taskData,
                    true
                );

            if (!$taskId) {
                throw new \RuntimeException(
                    'Unable to create task.'
                );
            }

            /*
             * New local task must be sent to GitHub.
             */
            $idempotencyKey =
                'task-' .
                $taskId .
                '-v1-create';

            $this->queue->insert([
                'task_id' =>
                    $taskId,

                'provider' =>
                    'github',

                'operation' =>
                    'create',

                'status' =>
                    'pending',

                'idempotency_key' =>
                    $idempotencyKey,

                'payload' =>
                    json_encode([
                        'title' =>
                            $title,

                        'description' =>
                            $data['description'] ?? null,

                        'status' =>
                            $status,

                        'priority' =>
                            $priority,

                        'due_date' =>
                            $data['due_date'] ?? null,
                    ]),

                'attempts' =>
                    0,

                'max_attempts' =>
                    5,

                'next_attempt_at' =>
                    $now,

                'locked_at' =>
                    null,

                'locked_by' =>
                    null,

                'last_error' =>
                    null,
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \RuntimeException(
                    'Transaction failed while creating task.'
                );
            }

            $task =
                $this->tasks->find($taskId);

            return $this->response
                ->setStatusCode(201)
                ->setJSON([
                    'success' => true,
                    'message' =>
                        'Task created and queued for synchronization.',
                    'data' => $task,
                ]);

        } catch (Throwable $e) {

            return $this->errorResponse(
                $e->getMessage()
            );
        }
    }

    /**
     * PATCH /api/tasks/{id}
     *
     * Optimistic locking:
     *
     * Client must provide:
     *
     * {
     *     "version": 1,
     *     ...
     * }
     */
    public function update(int $id): ResponseInterface
    {
        try {

            $task =
                $this->tasks->find($id);

            if (
                !$task ||
                !empty($task['deleted_at'])
            ) {

                return $this->response
                    ->setStatusCode(404)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Task not found.',
                    ]);
            }

            $data =
                $this->request->getJSON(true);

            if (!is_array($data)) {
                $data = [];
            }

            if (
                !isset($data['version']) ||
                !is_numeric($data['version'])
            ) {

                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Version is required for optimistic locking.',
                    ]);
            }

            $expectedVersion =
                (int) $data['version'];

            if (
                $expectedVersion !==
                (int) $task['version']
            ) {

                return $this->response
                    ->setStatusCode(409)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Task was modified by another request.',
                        'conflict' => true,
                        'current_task' =>
                            $task,
                    ]);
            }

            $update = [];

            $allowedFields = [
                'title',
                'description',
                'status',
                'priority',
                'due_date',
            ];

            foreach (
                $allowedFields as $field
            ) {

                if (
                    array_key_exists(
                        $field,
                        $data
                    )
                ) {

                    $update[$field] =
                        $data[$field];
                }
            }

            if (isset($update['title'])) {

                $update['title'] =
                    trim(
                        (string)
                        $update['title']
                    );

                if (
                    $update['title'] === ''
                ) {

                    return $this->response
                        ->setStatusCode(422)
                        ->setJSON([
                            'success' => false,
                            'error' =>
                                'Title cannot be empty.',
                        ]);
                }
            }

            if (
                isset($update['status']) &&
                !in_array(
                    $update['status'],
                    [
                        'pending',
                        'in_progress',
                        'completed',
                    ],
                    true
                )
            ) {

                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Invalid status.',
                    ]);
            }

            if (
                isset($update['priority']) &&
                !in_array(
                    $update['priority'],
                    [
                        'low',
                        'medium',
                        'high',
                    ],
                    true
                )
            ) {

                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Invalid priority.',
                    ]);
            }

            if (empty($update)) {

                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'No fields provided for update.',
                    ]);
            }

            $update['version'] =
                $expectedVersion + 1;

            $update['local_updated_at'] =
                date('Y-m-d H:i:s');

            $update['sync_status'] =
                'pending';

            /*
             * Critical optimistic-locking UPDATE.
             *
             * The version condition prevents
             * two concurrent updates from silently
             * overwriting each other.
             */
            $db = db_connect();

            $db->transStart();

            $builder =
                $db->table('tasks');

            $builder
                ->where('id', $id)
                ->where(
                    'version',
                    $expectedVersion
                )
                ->where(
                    'deleted_at',
                    null
                )
                ->update($update);

            if (
                $db->affectedRows() !== 1
            ) {

                $db->transRollback();

                $current =
                    $this->tasks->find($id);

                return $this->response
                    ->setStatusCode(409)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Concurrent update detected.',
                        'conflict' => true,
                        'current_task' =>
                            $current,
                    ]);
            }

            /*
             * Queue the new version.
             */
            $newVersion =
                $expectedVersion + 1;

            $idempotencyKey =
                'task-' .
                $id .
                '-v' .
                $newVersion .
                '-update';

            $this->queue->insert([
                'task_id' =>
                    $id,

                'provider' =>
                    'github',

                'operation' =>
                    'update',

                'status' =>
                    'pending',

                'idempotency_key' =>
                    $idempotencyKey,

                'payload' =>
                    json_encode([
                        'version' =>
                            $newVersion,

                        'changes' =>
                            $update,
                    ]),

                'attempts' =>
                    0,

                'max_attempts' =>
                    5,

                'next_attempt_at' =>
                    date('Y-m-d H:i:s'),

                'locked_at' =>
                    null,

                'locked_by' =>
                    null,

                'last_error' =>
                    null,
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {

                throw new \RuntimeException(
                    'Transaction failed while updating task.'
                );
            }

            $updatedTask =
                $this->tasks->find($id);

            return $this->response->setJSON([
                'success' => true,

                'message' =>
                    'Task updated and queued for synchronization.',

                'data' =>
                    $updatedTask,
            ]);

        } catch (Throwable $e) {

            return $this->errorResponse(
                $e->getMessage()
            );
        }
    }

    /**
     * DELETE /api/tasks/{id}
     *
     * Soft delete.
     */
    public function delete(int $id): ResponseInterface
    {
        try {

            $task =
                $this->tasks->find($id);

            if (
                !$task ||
                !empty($task['deleted_at'])
            ) {

                return $this->response
                    ->setStatusCode(404)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Task not found.',
                    ]);
            }

            $data =
                $this->request->getJSON(true);

            if (!is_array($data)) {
                $data = [];
            }

            if (
                !isset($data['version']) ||
                !is_numeric($data['version'])
            ) {

                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Version is required for delete.',
                    ]);
            }

            $expectedVersion =
                (int) $data['version'];

            if (
                $expectedVersion !==
                (int) $task['version']
            ) {

                return $this->response
                    ->setStatusCode(409)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Task was modified by another request.',
                        'conflict' => true,
                        'current_task' =>
                            $task,
                    ]);
            }

            $now =
                date('Y-m-d H:i:s');

            $newVersion =
                $expectedVersion + 1;

            $db = db_connect();

            $db->transStart();

            $builder =
                $db->table('tasks');

            $builder
                ->where('id', $id)
                ->where(
                    'version',
                    $expectedVersion
                )
                ->where(
                    'deleted_at',
                    null
                )
                ->update([
                    'deleted_at' =>
                        $now,

                    'version' =>
                        $newVersion,

                    'local_updated_at' =>
                        $now,

                    'sync_status' =>
                        'pending',
                ]);

            if (
                $db->affectedRows() !== 1
            ) {

                $db->transRollback();

                return $this->response
                    ->setStatusCode(409)
                    ->setJSON([
                        'success' => false,
                        'error' =>
                            'Concurrent delete detected.',
                        'conflict' => true,
                    ]);
            }

            /*
             * We don't actually delete the GitHub issue.
             *
             * The worker will later apply our documented
             * delete policy: close/archive the provider issue.
             */
            $idempotencyKey =
                'task-' .
                $id .
                '-v' .
                $newVersion .
                '-delete';

            $this->queue->insert([
                'task_id' =>
                    $id,

                'provider' =>
                    'github',

                'operation' =>
                    'delete',

                'status' =>
                    'pending',

                'idempotency_key' =>
                    $idempotencyKey,

                'payload' =>
                    json_encode([
                        'version' =>
                            $newVersion,
                    ]),

                'attempts' =>
                    0,

                'max_attempts' =>
                    5,

                'next_attempt_at' =>
                    $now,

                'locked_at' =>
                    null,

                'locked_by' =>
                    null,

                'last_error' =>
                    null,
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {

                throw new \RuntimeException(
                    'Transaction failed while deleting task.'
                );
            }

            return $this->response->setJSON([
                'success' => true,

                'message' =>
                    'Task deleted locally and queued for provider synchronization.',
            ]);

        } catch (Throwable $e) {

            return $this->errorResponse(
                $e->getMessage()
            );
        }
    }

    /**
     * Standard error response.
     */
    private function errorResponse(
        string $message
    ): ResponseInterface {

        return $this->response
            ->setStatusCode(500)
            ->setJSON([
                'success' => false,
                'error' => $message,
            ]);
    }
}