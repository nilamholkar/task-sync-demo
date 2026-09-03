<?php

namespace App\Services;

use Config\Services;
use RuntimeException;
use Throwable;

class GitHubService
{
    private string $token;
    private string $owner;
    private string $repo;

    private int $maxRetries = 3;

    public function __construct()
    {
        $this->token = (string) env('github.token');
        $this->owner = (string) env('github.owner');
        $this->repo = (string) env('github.repo');

        if ($this->token === '') {
            throw new RuntimeException('GitHub token is not configured.');
        }

        if ($this->owner === '') {
            throw new RuntimeException('GitHub owner is not configured.');
        }

        if ($this->repo === '') {
            throw new RuntimeException('GitHub repository is not configured.');
        }
    }

    /**
     * Get repository details
     */
    public function getRepository(): array
    {
        return $this->request(
            'GET',
            $this->getRepositoryUrl()
        );
    }

    /**
     * Get GitHub issues with pagination
     */
    public function getIssues(
        int $page = 1,
        int $perPage = 100,
        ?string $since = null
    ): array {
        $query = [
            'state'    => 'all',
            'page'     => $page,
            'per_page' => $perPage,
        ];

        if ($since !== null) {
            $query['since'] = $since;
        }

        $url = $this->getRepositoryUrl() . '/issues?' .
            http_build_query($query);

        return $this->request('GET', $url);
    }

    /**
     * Get one GitHub issue
     */
    public function getIssue(int $issueNumber): array
    {
        $url = $this->getRepositoryUrl() .
            '/issues/' .
            $issueNumber;

        return $this->request('GET', $url);
    }

    /**
     * Create a new GitHub issue
     */
    public function createIssue(
        string $title,
        ?string $body = null
    ): array {
        $data = [
            'title' => $title,
        ];

        if ($body !== null) {
            $data['body'] = $body;
        }

        return $this->request(
            'POST',
            $this->getRepositoryUrl() . '/issues',
            $data
        );
    }

    /**
     * Update an existing GitHub issue
     */
    public function updateIssue(
        int $issueNumber,
        array $data
    ): array {
        $allowedFields = [
            'title',
            'body',
            'state',
        ];

        $updateData = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            throw new RuntimeException(
                'No valid fields provided for GitHub issue update.'
            );
        }

        return $this->request(
            'PATCH',
            $this->getRepositoryUrl() .
                '/issues/' .
                $issueNumber,
            $updateData
        );
    }

    /**
     * Close a GitHub issue
     */
    public function closeIssue(int $issueNumber): array
    {
        return $this->updateIssue(
            $issueNumber,
            [
                'state' => 'closed',
            ]
        );
    }

    /**
     * Get the GitHub repository API URL
     */
    private function getRepositoryUrl(): string
    {
        return sprintf(
            'https://api.github.com/repos/%s/%s',
            rawurlencode($this->owner),
            rawurlencode($this->repo)
        );
    }

    /**
     * Send request to GitHub with retry handling
     */
    private function request(
        string $method,
        string $url,
        ?array $body = null
    ): array {
        $client = Services::curlrequest([
            'timeout'         => 30,
            'connect_timeout' => 10,
            'http_errors'     => false,
        ]);

        $attempt = 0;

        while ($attempt < $this->maxRetries) {

            $attempt++;

            try {

                $options = [
                    'headers' => [
                        'Accept' =>
                            'application/vnd.github+json',

                        'Authorization' =>
                            'Bearer ' . $this->token,

                        'X-GitHub-Api-Version' =>
                            '2022-11-28',

                        'User-Agent' =>
                            'Task-Management-Sync-App',
                    ],
                ];

                if ($body !== null) {
                    $options['json'] = $body;
                }

                $response = $client->request(
                    $method,
                    $url,
                    $options
                );

                $statusCode = $response->getStatusCode();

                $responseBody =
                    (string) $response->getBody();

                $data = json_decode(
                    $responseBody,
                    true
                );

                if (!is_array($data)) {
                    $data = [
                        'raw' => $responseBody,
                    ];
                }

                /*
                 * Success
                 */
                if (
                    $statusCode >= 200 &&
                    $statusCode < 300
                ) {
                    return $data;
                }

                /*
                 * Rate limit
                 */
                if ($statusCode === 429) {

                    $retryAfter =
                        $response->getHeaderLine(
                            'Retry-After'
                        );

                    $sleep =
                        $retryAfter !== ''
                            ? (int) $retryAfter
                            : 5;

                    sleep($sleep);

                    continue;
                }

                /*
                 * GitHub rate limit
                 */
                if (
                    $statusCode === 403 &&
                    $response->getHeaderLine(
                        'X-RateLimit-Remaining'
                    ) === '0'
                ) {

                    $reset =
                        $response->getHeaderLine(
                            'X-RateLimit-Reset'
                        );

                    if ($reset !== '') {

                        $wait =
                            (int) $reset - time();

                        if ($wait > 0) {
                            sleep(min($wait, 60));
                        }
                    }

                    continue;
                }

                /*
                 * Retry temporary server errors
                 */
                if ($statusCode >= 500) {

                    sleep($attempt * 2);

                    continue;
                }

                /*
                 * Non-retryable error
                 */
                throw new RuntimeException(
                    'GitHub API error. HTTP ' .
                    $statusCode .
                    ': ' .
                    ($data['message'] ??
                        $responseBody)
                );

            } catch (Throwable $e) {

                /*
                 * Last retry failed
                 */
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }

                /*
                 * Network error backoff
                 */
                sleep($attempt * 2);
            }
        }

        throw new RuntimeException(
            'GitHub request failed after retries.'
        );
    }
}