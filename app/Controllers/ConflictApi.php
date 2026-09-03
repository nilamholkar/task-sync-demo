<?php

namespace App\Controllers;

use App\Models\TaskModel;
use App\Models\SyncConflictModel;
use App\Models\SyncQueueModel;
use CodeIgniter\HTTP\ResponseInterface;

class ConflictApi extends BaseController
{
    protected TaskModel $taskModel;
    protected SyncConflictModel $conflictModel;
    protected SyncQueueModel $queueModel;

    public function __construct()
    {
        $this->taskModel = new TaskModel();
        $this->conflictModel = new SyncConflictModel();
        $this->queueModel = new SyncQueueModel();
    }

    /**
     * GET /api/conflicts
     */
    public function index(): ResponseInterface
    {
        $conflicts = $this->conflictModel
            ->where('status', 'open')
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'data' => $conflicts,
        ]);
    }

    /**
     * GET /api/conflicts/{id}
     */
    public function show(int $id): ResponseInterface
    {
        $conflict = $this->conflictModel->find($id);

        if (!$conflict) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'error' => 'Conflict not found.',
                ]);
        }

        $conflict['local_snapshot'] =
            $this->decodeJson($conflict['local_snapshot']);

        $conflict['provider_snapshot'] =
            $this->decodeJson($conflict['provider_snapshot']);

        if (!empty($conflict['resolved_snapshot'])) {
            $conflict['resolved_snapshot'] =
                $this->decodeJson($conflict['resolved_snapshot']);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $conflict,
        ]);
    }

    /**
     * POST /api/conflicts/{id}/resolve
     *
     * Body:
     *
     * {
     *     "resolution": "keep_local"
     * }
     *
     * OR
     *
     * {
     *     "resolution": "keep_provider"
     * }
     *
     * OR
     *
     * {
     *     "resolution": "manual_merge",
     *     "title": "...",
     *     "description": "...",
     *     "status": "pending",
     *     "priority": "medium",
     *     "due_date": "2026-09-10"
     * }
     */
    public function resolve(int $id): ResponseInterface
    {
        $conflict = $this->conflictModel->find($id);

        if (!$conflict) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'error' => 'Conflict not found.',
                ]);
        }

        if ($conflict['status'] !== 'open') {
            return $this->response
                ->setStatusCode(409)
                ->setJSON([
                    'success' => false,
                    'error' => 'Conflict has already been resolved.',
                ]);
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'error' => 'Invalid JSON body.',
                ]);
        }

        $resolution = $data['resolution'] ?? null;

        $allowedResolutions = [
            'keep_local',
            'keep_provider',
            'manual_merge',
        ];

        if (!in_array($resolution, $allowedResolutions, true)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'error' => 'Invalid resolution.',
                    'allowed' => $allowedResolutions,
                ]);
        }

        $task = $this->taskModel->find($conflict['task_id']);

        if (!$task) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'error' => 'Associated task not found.',
                ]);
        }

        if ($resolution === 'keep_local') {
            return $this->keepLocal($conflict, $task);
        }

        if ($resolution === 'keep_provider') {
            return $this->keepProvider($conflict, $task);
        }

        return $this->manualMerge($conflict, $task, $data);
    }

    /**
     * Keep local version.
     */
    private function keepLocal(array $conflict, array $task): ResponseInterface
    {
        $db = db_connect();

        $db->transBegin();

        try {
            $newVersion = ((int) $task['version']) + 1;

            $this->taskModel->update($task['id'], [
                'version' => $newVersion,
                'sync_status' => 'pending',
                'local_updated_at' => date('Y-m-d H:i:s'),
            ]);

            $idempotencyKey =
                'task-' . $task['id'] . '-v' . $newVersion . '-conflict-local';

            $existingQueue = $this->queueModel
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (!$existingQueue) {
                $this->queueModel->insert([
                    'task_id' => $task['id'],
                    'provider' => 'github',
                    'operation' => 'update',
                    'status' => 'pending',
                    'idempotency_key' => $idempotencyKey,
                    'payload' => json_encode([
                        'title' => $task['title'],
                        'description' => $task['description'],
                        'status' => $task['status'],
                        'priority' => $task['priority'],
                        'due_date' => $task['due_date'],
                    ]),
                    'attempts' => 0,
                    'max_attempts' => 5,
                    'next_attempt_at' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->conflictModel->update($conflict['id'], [
                'status' => 'resolved',
                'resolution' => 'keep_local',
                'resolved_snapshot' => json_encode([
                    'title' => $task['title'],
                    'description' => $task['description'],
                    'status' => $task['status'],
                    'priority' => $task['priority'],
                    'due_date' => $task['due_date'],
                ]),
                'resolved_at' => date('Y-m-d H:i:s'),
            ]);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction failed.');
            }

            $db->transCommit();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Conflict resolved using local version.',
                'resolution' => 'keep_local',
                'task_id' => (int) $task['id'],
                'sync_status' => 'pending',
                'queue_status' => 'pending',
            ]);

        } catch (\Throwable $e) {

            $db->transRollback();

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
        }
    }

    /**
     * Keep GitHub/provider version.
     */
    private function keepProvider(
        array $conflict,
        array $task
    ): ResponseInterface {

        $providerSnapshot =
            $this->decodeJson($conflict['provider_snapshot']);

        $localSnapshot =
            $this->decodeJson($conflict['local_snapshot']);

        $providerStatus =
            (($providerSnapshot['state'] ?? 'open') === 'closed')
                ? 'completed'
                : 'pending';

        $db = db_connect();

        $db->transBegin();

        try {

            $newVersion = ((int) $task['version']) + 1;

            $this->taskModel->update($task['id'], [
                'title' => $providerSnapshot['title'] ?? $task['title'],
                'description' => $providerSnapshot['description'] ?? null,
                'status' => $providerStatus,
                'version' => $newVersion,
                'provider_updated_at' =>
                    $this->convertProviderDate(
                        $providerSnapshot['updated_at'] ?? null
                    ),
                'provider_url' =>
                    $providerSnapshot['html_url'] ??
                    $task['provider_url'],
                'sync_status' => 'synced',
                'last_synced_at' => date('Y-m-d H:i:s'),
                'local_updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->conflictModel->update($conflict['id'], [
                'status' => 'resolved',
                'resolution' => 'keep_provider',
                'resolved_snapshot' => json_encode([
                    'title' => $providerSnapshot['title'] ?? '',
                    'description' =>
                        $providerSnapshot['description'] ?? '',
                    'status' => $providerStatus,
                    'provider_snapshot' => $providerSnapshot,
                    'previous_local_snapshot' => $localSnapshot,
                ]),
                'resolved_at' => date('Y-m-d H:i:s'),
            ]);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction failed.');
            }

            $db->transCommit();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Conflict resolved using GitHub version.',
                'resolution' => 'keep_provider',
                'task_id' => (int) $task['id'],
                'sync_status' => 'synced',
            ]);

        } catch (\Throwable $e) {

            $db->transRollback();

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
        }
    }

    /**
     * Manual merge.
     */
    private function manualMerge(
        array $conflict,
        array $task,
        array $data
    ): ResponseInterface {

        if (empty($data['title'])) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'error' => 'Title is required for manual merge.',
                ]);
        }

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

        $status = $data['status'] ?? $task['status'];
        $priority = $data['priority'] ?? $task['priority'];

        if (!in_array($status, $allowedStatuses, true)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'error' => 'Invalid status.',
                ]);
        }

        if (!in_array($priority, $allowedPriorities, true)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'error' => 'Invalid priority.',
                ]);
        }

        $merged = [
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'status' => $status,
            'priority' => $priority,
            'due_date' => $data['due_date'] ?? null,
        ];

        $db = db_connect();

        $db->transBegin();

        try {

            $newVersion = ((int) $task['version']) + 1;

            $this->taskModel->update($task['id'], [
                'title' => $merged['title'],
                'description' => $merged['description'],
                'status' => $merged['status'],
                'priority' => $merged['priority'],
                'due_date' => $merged['due_date'],
                'version' => $newVersion,
                'local_updated_at' => date('Y-m-d H:i:s'),
                'sync_status' => 'pending',
            ]);

            $idempotencyKey =
                'task-' .
                $task['id'] .
                '-v' .
                $newVersion .
                '-manual-merge';

            $this->queueModel->insert([
                'task_id' => $task['id'],
                'provider' => 'github',
                'operation' => 'update',
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'payload' => json_encode($merged),
                'attempts' => 0,
                'max_attempts' => 5,
                'next_attempt_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->conflictModel->update($conflict['id'], [
                'status' => 'resolved',
                'resolution' => 'manual_merge',
                'resolved_snapshot' => json_encode($merged),
                'resolved_at' => date('Y-m-d H:i:s'),
            ]);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction failed.');
            }

            $db->transCommit();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Conflict resolved using manual merge.',
                'resolution' => 'manual_merge',
                'task_id' => (int) $task['id'],
                'sync_status' => 'pending',
                'queue_status' => 'pending',
                'merged' => $merged,
            ]);

        } catch (\Throwable $e) {

            $db->transRollback();

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
        }
    }

    /**
     * Decode JSON safely.
     */
    private function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Convert GitHub ISO timestamp to MySQL datetime.
     */
    private function convertProviderDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}