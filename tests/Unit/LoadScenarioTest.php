<?php

namespace Tests\Unit;

use App\LoadTesting\Scenario;
use PHPUnit\Framework\TestCase;

class LoadScenarioTest extends TestCase
{
    public function test_same_bot_and_seed_replays_identically(): void
    {
        $this->assertSame(Scenario::forBot(3, 's1'), Scenario::forBot(3, 's1'));
    }

    public function test_action_pool_covers_all_endpoints_across_100_bots(): void
    {
        $seen = [];
        foreach (range(1, 100) as $id) {
            foreach (Scenario::forBot($id, 'cover') as $step) {
                $seen[$step['action']] = true;
            }
        }
        foreach (['start', 'status', 'begin', 'answer', 'violation', 'pause', 'resume', 'submit', 'submit_dup'] as $action) {
            $this->assertArrayHasKey($action, $seen);
        }
    }
}
