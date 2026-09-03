# Cross-Tool Task Sync Engine

A full-stack task synchronization system built with CodeIgniter 4,
MySQL/MariaDB, JavaScript and GitHub Issues API.

## Architecture

Browser
   |
   v
CodeIgniter REST API
   |
   +---- MySQL
   |      |
   |      +-- tasks
   |      +-- sync_queue
   |      +-- webhook_events
   |      +-- sync_conflicts
   |      +-- sync_checkpoints
   |      +-- sync_logs
   |
   v
Sync Worker
   |
   v
GitHub Issues API

GitHub Webhook
   |
   v
CodeIgniter Webhook Endpoint
   |
   v
MySQL

## Features

- Create, update and delete tasks
- GitHub Issues integration
- Bidirectional synchronization
- Webhook processing
- Webhook idempotency
- Optimistic concurrency control
- Conflict detection and resolution
- Retry with exponential backoff
- Failed-item quarantine
- Initial synchronization with pagination
- Resumable synchronization using checkpoints
- Sync status tracking
- Search and filtering
- Conflict resolution UI

## Sync Status

Tasks can have:

- synced
- pending
- conflict
- error

## Conflict Policy

A conflict occurs when:

1. A local task has an unsynchronized change.
2. GitHub reports a change for the same task.

The system stores both versions:

- Local snapshot
- GitHub snapshot

The user can resolve the conflict using:

- Keep Local
- Keep GitHub
- Manual Merge

## Idempotency

GitHub webhook deliveries are identified using
X-GitHub-Delivery.

The webhook_events table has a unique constraint on:

(provider, event_id)

Therefore duplicate webhook deliveries are ignored.

Sync queue operations also use unique idempotency keys.

## Optimistic Concurrency

Every task has a version number.

PATCH requests must provide the current version.

Example:

Current version = 6

Request A:
version = 6
=> succeeds
=> version becomes 7

Request B:
version = 6
=> rejected with HTTP 409

This prevents lost updates.

## Retry Strategy

Transient provider failures such as:

- HTTP 429
- HTTP 500
- HTTP 502
- HTTP 503
- network/timeout failures

are retried.

The worker uses exponential backoff.

Each queue item has a maximum of 5 attempts.

After the maximum number of attempts,
the item is quarantined and the task is marked as error.

## Failed Item Quarantine

Failed synchronization items are not discarded.

They remain in sync_queue with:

status = quarantined

and retain:

- attempt count
- last error
- payload
- task ID

This allows investigation and recovery.

## Initial Synchronization

GitHub issues are retrieved using pagination.

The API requests up to 100 issues per page.

After successful processing of each page,
the sync checkpoint is updated.

If synchronization stops unexpectedly,
the next run can continue from the saved checkpoint.

## Deletion Policy

Local deletion uses a soft-delete/tombstone.

deleted_at is retained so old provider events cannot recreate
a task that was intentionally deleted locally.

GitHub Issues do not use normal issue deletion in this workflow,
so a local deletion is synchronized by closing the GitHub issue.

## Provider Mapping

Local status:

pending/in_progress
    -> GitHub open

completed
    -> GitHub closed

## Security

GitHub credentials are stored in .env.

Secrets must never be committed to Git.

.env is excluded through .gitignore.

## Running the Application

Start Apache/MySQL through XAMPP.

Run:

php spark serve --port 8081

Application:

http://localhost:8081

## Worker

The synchronization worker can currently be triggered through:

http://localhost:8081/worker-test

This endpoint is used as a demonstration/manual worker trigger.

## API Endpoints

GET    /api/tasks
GET    /api/tasks/{id}
POST   /api/tasks
PATCH  /api/tasks/{id}
DELETE /api/tasks/{id}

GET    /api/conflicts
GET    /api/conflicts/{id}
POST   /api/conflicts/{id}/resolve

POST   /api/webhooks/github

## How I Ensured Sync Correctness

1. Webhook idempotency prevents duplicate event processing.
2. Queue idempotency prevents duplicate provider operations.
3. Optimistic versioning prevents lost local updates.
4. Transactions protect task and queue state changes.
5. Tombstones prevent deleted tasks from being recreated.
6. Conflicts preserve both local and provider snapshots.
7. Retries handle transient provider failures.
8. Quarantine prevents permanently failing items from blocking
   synchronization.
9. Checkpoints make initial synchronization resumable.
10. Sync logs provide operational visibility.

## Known Limitations

- Current worker is manually triggered through an HTTP endpoint.
- Production deployment should use a CLI worker/process supervisor.
- Conflict detection can be extended to field-level merge strategies.
- The demo repository contains a small number of GitHub issues;
  large-scale pagination is implemented but not demonstrated with
  10,000+ real issues.