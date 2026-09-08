<?php

namespace App\LoadTesting;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Drives one student's scenario against a running app instance.
 *
 * Plain PHP + Guzzle only (runs in forked children): no facades, no Laravel
 * helpers. Every request is timed and appended as one JSONL line; 4xx/5xx and
 * transport errors are recorded, never thrown.
 */
class StudentBot
{
    private Client $client;

    private CookieJar $jar;

    private ?int $sessionId = null;

    /**
     * @param  array<int, int>  $questionIds
     */
    public function __construct(
        private int $botId,
        private string $email,
        private string $password,
        private int $examId,
        private array $questionIds
    ) {
        $this->jar = new CookieJar;
        $this->client = new Client([
            'cookies' => $this->jar,
            'http_errors' => false,
            // Redirects stay disabled so the session id can be read off the
            // start/resume Location header instead of being followed silently.
            'allow_redirects' => false,
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);
    }

    /**
     * @param  array<int, mixed>  $actions
     */
    public function run(array $actions, string $baseUrl, string $resultsPath): void
    {
        $base = rtrim($baseUrl, '/');

        $this->login($base, $resultsPath);

        foreach ($actions as $step) {
            if (! is_array($step) || ! isset($step['action']) || ! is_string($step['action'])) {
                $this->record($resultsPath, 'unknown', 0, 0.0, 'unknown-action');

                continue;
            }

            $this->dispatch($base, $resultsPath, $step['action'], $step);
        }
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function dispatch(string $base, string $resultsPath, string $action, array $step): void
    {
        switch ($action) {
            case 'double_start':
                $this->startAndRemember($base, $resultsPath, $action);
                $this->startAndRemember($base, $resultsPath, $action);
                break;
            case 'eager_begin':
            case 'start':
                $this->startAndRemember($base, $resultsPath, $action);
                break;
            case 'status':
                if ($this->sessionId === null) {
                    $this->record($resultsPath, $action, 0, 0.0, 'no-session');

                    return;
                }
                $res = $this->send('GET', $base.'/exam/session/'.$this->sessionId.'/status');
                $this->record($resultsPath, $action, $res['status'], $res['ms'], $res['body']);
                break;
            case 'begin':
                if ($this->sessionId === null) {
                    $this->record($resultsPath, $action, 0, 0.0, 'no-session');

                    return;
                }
                $res = $this->send('POST', $base.'/exam/session/'.$this->sessionId.'/begin');
                $this->record($resultsPath, $action, $res['status'], $res['ms'], $res['body']);
                break;
            case 'answer':
                $this->sendAnswer($base, $resultsPath, $step);
                break;
            case 'violation':
                $this->sendViolation($base, $resultsPath, $step);
                break;
            case 'pause':
                $this->postViolation($base, $resultsPath, 'pause', 'window_blur');
                break;
            case 'resume':
                if ($this->sessionId === null) {
                    $this->record($resultsPath, $action, 0, 0.0, 'no-session');

                    return;
                }
                $res = $this->send('GET', $base.'/exam/session/'.$this->sessionId.'/resume');
                $this->record($resultsPath, $action, $res['status'], $res['ms'], $res['body']);
                break;
            case 'submit':
            case 'submit_dup':
                if ($this->sessionId === null) {
                    $this->record($resultsPath, $action, 0, 0.0, 'no-session');

                    return;
                }
                $res = $this->send('POST', $base.'/exam/session/'.$this->sessionId.'/submit');
                $this->record($resultsPath, $action, $res['status'], $res['ms'], $res['body']);
                break;
            default:
                $this->record($resultsPath, $action, 0, 0.0, 'unknown-action');
                break;
        }
    }

    private function startAndRemember(string $base, string $resultsPath, string $action): void
    {
        $res = $this->send('GET', $base.'/exam/'.$this->examId.'/start');
        $this->record($resultsPath, $action, $res['status'], $res['ms'], $res['body']);

        $found = $this->extractSessionId($res['location'], $res['body']);
        if ($found !== null) {
            $this->sessionId = $found;
        }
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function sendAnswer(string $base, string $resultsPath, array $step): void
    {
        if ($this->sessionId === null) {
            $this->record($resultsPath, 'answer', 0, 0.0, 'no-session');

            return;
        }

        $index = isset($step['question']) && is_numeric($step['question']) ? (int) $step['question'] : 1;
        $questionId = $this->questionIds[$index - 1] ?? $this->questionIds[0] ?? null;
        if ($questionId === null) {
            $this->record($resultsPath, 'answer', 0, 0.0, 'no-target');

            return;
        }

        $res = $this->send('POST', $base.'/exam/session/'.$this->sessionId.'/answer', [
            'form_params' => [
                'question_id' => (int) $questionId,
                'answer' => ['B'],
                'is_marked_for_review' => false,
            ],
        ]);
        $this->record($resultsPath, 'answer', $res['status'], $res['ms'], $res['body']);
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function sendViolation(string $base, string $resultsPath, array $step): void
    {
        $times = (isset($step['spam']) && $step['spam'] === true) ? 6 : 1;
        for ($i = 0; $i < $times; $i++) {
            $this->postViolation($base, $resultsPath, 'violation', 'tab_switch');
        }
    }

    private function postViolation(string $base, string $resultsPath, string $action, string $type): void
    {
        if ($this->sessionId === null) {
            $this->record($resultsPath, $action, 0, 0.0, 'no-session');

            return;
        }

        $res = $this->send('POST', $base.'/exam/session/'.$this->sessionId.'/violation', [
            'form_params' => [
                'type' => $type,
                'description' => 'loadtest',
                'metadata' => ['bot' => $this->botId],
            ],
        ]);
        $this->record($resultsPath, $action, $res['status'], $res['ms'], $res['body']);
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

    private function extractSessionId(string $location, string $body): ?int
    {
        foreach ([$location, $body] as $text) {
            if ($text === '') {
                continue;
            }
            if (preg_match('#/exam/session/(\d+)/(take|resume)#', $text, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (isset($decoded['session']) && is_array($decoded['session'])
                && isset($decoded['session']['id']) && is_numeric($decoded['session']['id'])) {
                return (int) $decoded['session']['id'];
            }
            if (isset($decoded['session_id']) && is_numeric($decoded['session_id'])) {
                return (int) $decoded['session_id'];
            }
            if (isset($decoded['id']) && is_numeric($decoded['id'])) {
                return (int) $decoded['id'];
            }
        }

        return null;
    }

    private function record(string $path, string $action, int $status, float $ms, string $body): void
    {
        $line = json_encode([
            'bot' => $this->botId,
            'action' => $action,
            'status' => $status,
            'ms' => $ms,
            'body' => substr($body, 0, 200),
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        if (! is_string($line)) {
            $line = '{"bot":'.$this->botId.',"action":"'.$action.'","status":0,"ms":0,"body":"encode-error"}';
        }

        @file_put_contents($path, $line."\n", FILE_APPEND | LOCK_EX);
    }
}
