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

    public function test_every_bot_begins_before_state_dependent_steps_and_never_ends_paused(): void
    {
        foreach (range(0, 100) as $id) {
            $actions = array_column(Scenario::forBot($id, 'invariant'), 'action');
            $startAt = array_search('start', $actions, true);
            $beginAt = array_search('begin', $actions, true);
            $submitAt = array_search('submit', $actions, true);

            $this->assertNotFalse($beginAt, "bot {$id} has no begin");
            $this->assertGreaterThan($startAt, $beginAt, "bot {$id} begins before start");
            $this->assertLessThan($submitAt, $beginAt, "bot {$id} begins after submit");

            $openPauses = 0;
            foreach ($actions as $action) {
                if ($action === 'pause') {
                    $openPauses++;
                }
                if ($action === 'resume') {
                    $openPauses = max(0, $openPauses - 1);
                }
                if ($action === 'submit') {
                    $this->assertSame(0, $openPauses, "bot {$id} submits while paused");
                }
            }
        }
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
