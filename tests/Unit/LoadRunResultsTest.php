<?php

namespace Tests\Unit;

use App\LoadTesting\RunResults;
use PHPUnit\Framework\TestCase;

class LoadRunResultsTest extends TestCase
{
    public function test_aggregate_computes_nearest_rank_percentiles_and_counts(): void
    {
        $dir = sys_get_temp_dir().'/loadrunresults-'.uniqid();
        mkdir($dir);

        $lines = [
            ['bot' => 1, 'action' => 'login', 'status' => 200, 'ms' => 100, 'body' => 'ok'],
            ['bot' => 2, 'action' => 'login', 'status' => 200, 'ms' => 200, 'body' => 'ok'],
            ['bot' => 3, 'action' => 'login', 'status' => 500, 'ms' => 300, 'body' => 'err'],
            ['bot' => 4, 'action' => 'login', 'status' => 429, 'ms' => 400, 'body' => 'busy'],
            ['bot' => 1, 'action' => 'answer', 'status' => 200, 'ms' => 10, 'body' => 'ok'],
            ['bot' => 2, 'action' => 'answer', 'status' => 200, 'ms' => 20, 'body' => 'ok'],
            ['bot' => 3, 'action' => 'answer', 'status' => 502, 'ms' => 30, 'body' => 'err'],
            'this is not json',
        ];

        $content = '';
        foreach ($lines as $line) {
            $content .= (is_string($line) ? $line : json_encode($line))."\n";
        }
        file_put_contents($dir.'/bot-1.jsonl', $content);

        $result = RunResults::aggregate($dir);

        $this->assertSame(4, $result['by_action']['login']['n']);
        $this->assertSame(200.0, $result['by_action']['login']['p50']);
        $this->assertSame(400.0, $result['by_action']['login']['p95']);
        $this->assertSame([200 => 2, 500 => 1, 429 => 1], $result['by_action']['login']['codes']);

        $this->assertSame(3, $result['by_action']['answer']['n']);
        $this->assertSame(20.0, $result['by_action']['answer']['p50']);
        $this->assertSame(30.0, $result['by_action']['answer']['p95']);
        $this->assertSame([200 => 2, 502 => 1], $result['by_action']['answer']['codes']);

        $this->assertSame(2, $result['http_5xx']);
        $this->assertSame(1, $result['http_429']);
        $this->assertSame(1, $result['skipped']);

        array_map('unlink', glob($dir.'/*.jsonl'));
        rmdir($dir);
    }
}
