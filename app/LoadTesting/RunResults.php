<?php

namespace App\LoadTesting;

class RunResults
{
    /**
     * @return array{by_action: array<string, array{n: int, p50: float, p95: float, codes: array<int, int>}>, http_5xx: int, http_429: int, skipped: int}
     */
    public static function aggregate(string $dir): array
    {
        /** @var array<string, array{ms: array<int, float>, codes: array<int, int>}> $grouped */
        $grouped = [];
        $http5xx = 0;
        $http429 = 0;
        $skipped = 0;

        foreach (glob(rtrim($dir, '/').'/*.jsonl') ?: [] as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (! is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    $skipped++;

                    continue;
                }
                $decoded = json_decode($line, true);
                if (! is_array($decoded)
                    || ! isset($decoded['action']) || ! is_string($decoded['action'])
                    || ! isset($decoded['ms']) || ! is_numeric($decoded['ms'])
                    || ! isset($decoded['status']) || ! is_numeric($decoded['status'])
                ) {
                    $skipped++;

                    continue;
                }
                $action = $decoded['action'];
                $ms = (float) $decoded['ms'];
                $status = (int) $decoded['status'];

                if (! isset($grouped[$action])) {
                    $grouped[$action] = ['ms' => [], 'codes' => []];
                }
                $grouped[$action]['ms'][] = $ms;
                $grouped[$action]['codes'][$status] = ($grouped[$action]['codes'][$status] ?? 0) + 1;

                if ($status >= 500 && $status <= 599) {
                    $http5xx++;
                }
                if ($status === 429) {
                    $http429++;
                }
            }
        }

        $byAction = [];
        foreach ($grouped as $action => $data) {
            sort($data['ms']);
            $byAction[$action] = [
                'n' => count($data['ms']),
                'p50' => self::percentile($data['ms'], 50),
                'p95' => self::percentile($data['ms'], 95),
                'codes' => $data['codes'],
            ];
        }

        return [
            'by_action' => $byAction,
            'http_5xx' => $http5xx,
            'http_429' => $http429,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<int, float>  $sorted
     */
    private static function percentile(array $sorted, float $p): float
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }
        $rank = (int) ceil($p / 100 * $n);

        return (float) $sorted[max(0, $rank - 1)];
    }
}
