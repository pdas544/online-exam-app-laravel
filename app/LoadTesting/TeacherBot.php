<?php

namespace App\LoadTesting;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Drives the teacher monitoring flow against a running app instance.
 *
 * Plain PHP + Guzzle only (runs in forked children): no facades, no Laravel
 * helpers. Every request is timed and appended as one JSONL line; 4xx/5xx and
 * transport errors are recorded, never thrown.
 */
class TeacherBot
{
    private Client $client;

    private CookieJar $jar;

    public function __construct(
        private string $email,
        private string $password,
        private int $examId
    ) {
        $this->jar = new CookieJar;
        $this->client = new Client([
            'cookies' => $this->jar,
            'http_errors' => false,
            // Redirects stay disabled so redirect responses (e.g. force-end)
            // are recorded as-is instead of being followed silently.
            'allow_redirects' => false,
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);
    }

    public function run(string $baseUrl, string $resultsPath): void
    {
        $base = rtrim($baseUrl, '/');

        $this->login($base, $resultsPath);

        sleep(20);

        $res = $this->send('POST', $base.'/teacher/monitor/'.$this->examId.'/start');
        $this->record($resultsPath, 'start', $res['status'], $res['ms'], $res['body']);

        sleep(10);

        $res = $this->send('GET', $base.'/teacher/monitor/'.$this->examId.'/sessions');
        $this->record($resultsPath, 'sessions', $res['status'], $res['ms'], $res['body']);
        $sessions = $this->parseSessions($res['body']);

        $warned = 0;
        foreach ($sessions as $session) {
            if ($warned >= 2) {
                break;
            }
            $warn = $this->send('POST', $base.'/teacher/monitor/session/'.$session['id'].'/warn', [
                'form_params' => ['message' => 'loadtest warning'],
            ]);
            $this->record($resultsPath, 'warn', $warn['status'], $warn['ms'], $warn['body']);
            $warned++;
        }

        $endedId = null;
        if (count($sessions) > 0) {
            $endedId = $sessions[0]['id'];
            $end = $this->send('POST', $base.'/teacher/monitor/session/'.$endedId.'/end');
            $this->record($resultsPath, 'end', $end['status'], $end['ms'], $end['body']);
        } else {
            $this->record($resultsPath, 'end', 0, 0.0, 'no-target');
        }

        $resumeId = null;
        foreach ($sessions as $session) {
            if ($session['status'] === 'paused' && $session['id'] !== $endedId) {
                $resumeId = $session['id'];
                break;
            }
        }
        if ($resumeId === null) {
            foreach ($sessions as $session) {
                if ($session['status'] === 'paused') {
                    $resumeId = $session['id'];
                    break;
                }
            }
        }

        if ($resumeId === null) {
            $this->record($resultsPath, 'resume', 0, 0.0, 'no-target');
        } else {
            $resume = $this->send('POST', $base.'/teacher/monitor/session/'.$resumeId.'/resume');
            $this->record($resultsPath, 'resume', $resume['status'], $resume['ms'], $resume['body']);
        }
    }

    private function login(string $base, string $resultsPath): void
    {
        $get = $this->send('GET', $base.'/login');
        $this->record($resultsPath, 'login', $get['status'], $get['ms'], $get['body']);

        $post = $this->send('POST', $base.'/login', [
            'form_params' => [
                'email' => $this->email,
                'password' => $this->password,
            ],
        ]);
        $this->record($resultsPath, 'login', $post['status'], $post['ms'], $post['body']);
    }

    /**
     * @return array<int, array{id: int, status: string}>
     */
    private function parseSessions(string $body): array
    {
        /** @var array<int, array{id: int, status: string}> $sessions */
        $sessions = [];

        $decoded = json_decode($body, true);
        if (! is_array($decoded) || ! isset($decoded['sessions']) || ! is_array($decoded['sessions'])) {
            return $sessions;
        }

        foreach ($decoded['sessions'] as $row) {
            if (! is_array($row) || ! isset($row['id']) || ! is_numeric($row['id'])) {
                continue;
            }
            $sessions[] = [
                'id' => (int) $row['id'],
                'status' => isset($row['status']) && is_string($row['status']) ? $row['status'] : '',
            ];
        }

        return $sessions;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{status: int, ms: float, body: string, location: string}
     */
    private function send(string $method, string $url, array $options = []): array
    {
        $start = microtime(true);

        try {
            if ($method !== 'GET' && $method !== 'HEAD') {
                $token = $this->xsrfToken();
                if ($token !== '') {
                    if (! isset($options['headers']) || ! is_array($options['headers'])) {
                        $options['headers'] = [];
                    }
                    $options['headers']['X-XSRF-TOKEN'] = $token;
                }
            }

            $response = $this->client->request($method, $url, $options);

            return [
                'status' => $response->getStatusCode(),
                'ms' => (microtime(true) - $start) * 1000.0,
                'body' => (string) $response->getBody(),
                'location' => $response->getHeaderLine('Location'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'ms' => (microtime(true) - $start) * 1000.0,
                'body' => 'transport-error: '.substr($e->getMessage(), 0, 160),
                'location' => '',
            ];
        }
    }

    private function xsrfToken(): string
    {
        $cookie = $this->jar->getCookieByName('XSRF-TOKEN');
        if ($cookie === null) {
            return '';
        }

        $value = $cookie->getValue();

        return is_string($value) ? urldecode($value) : '';
    }

    private function record(string $path, string $action, int $status, float $ms, string $body): void
    {
        $line = json_encode([
            'bot' => 'teacher',
            'action' => $action,
            'status' => $status,
            'ms' => $ms,
            'body' => substr($body, 0, 200),
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        if (! is_string($line)) {
            $line = '{"bot":"teacher","action":"'.$action.'","status":0,"ms":0,"body":"encode-error"}';
        }

        @file_put_contents($path, $line."\n", FILE_APPEND | LOCK_EX);
    }
}
