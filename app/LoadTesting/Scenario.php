<?php

namespace App\LoadTesting;

class Scenario
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forBot(int $botId, string $masterSeed): array
    {
        mt_srand(crc32($masterSeed.'.'.$botId));

        $steps = [
            ['action' => 'double_start'],
            ['action' => 'eager_begin'],
            ['action' => 'start'],
        ];

        foreach (self::middleSteps($botId) as $step) {
            $steps[] = $step;
        }

        $steps[] = ['action' => 'submit'];
        $steps[] = ['action' => 'submit_dup'];

        return $steps;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function middleSteps(int $botId): array
    {
        if ($botId === 0) {
            $middle = [
                ['action' => 'violation', 'type' => 'tab_switch', 'spam' => true],
                ['action' => 'violation', 'type' => 'tab_switch', 'spam' => true],
            ];

            $extra = mt_rand(2, 4);
            for ($i = 0; $i < $extra; $i++) {
                $middle[] = ['action' => 'answer', 'question' => mt_rand(1, 10)];
            }

            return $middle;
        }

        if ($botId === 1) {
            $middle = [];
            for ($i = 0; $i < 3; $i++) {
                $middle[] = ['action' => 'pause'];
                $middle[] = ['action' => 'resume'];
            }

            $extra = mt_rand(2, 4);
            for ($i = 0; $i < $extra; $i++) {
                $middle[] = ['action' => 'answer', 'question' => mt_rand(1, 10)];
            }

            return $middle;
        }

        if ($botId >= 2 && $botId <= 4) {
            return [
                ['action' => 'answer', 'question' => mt_rand(1, 10)],
                ['action' => 'violation', 'type' => 'tab_switch'],
                ['action' => 'answer', 'question' => mt_rand(1, 10)],
            ];
        }

        return self::weightedMiddle();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function weightedMiddle(): array
    {
        $target = mt_rand(8, 14);
        $middle = [];

        while (count($middle) < $target) {
            $roll = mt_rand(1, 100);

            if ($roll <= 60) {
                $middle[] = ['action' => 'answer', 'question' => mt_rand(1, 10)];
            } elseif ($roll <= 75) {
                $middle[] = ['action' => 'status'];
            } elseif ($roll <= 83) {
                $middle[] = ['action' => 'violation', 'type' => 'tab_switch'];
            } elseif ($roll <= 88) {
                if (count($middle) + 2 <= $target) {
                    $middle[] = ['action' => 'pause'];
                    $middle[] = ['action' => 'resume'];
                } else {
                    $middle[] = ['action' => 'answer', 'question' => mt_rand(1, 10)];
                }
            } else {
                $middle[] = ['action' => 'begin'];
            }
        }

        return $middle;
    }
}
