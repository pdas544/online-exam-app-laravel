<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Guards the phpredis BLPOP contract: the connection read timeout must stay
 * above the queue blocking window, otherwise workers wedge in an exception
 * loop on the first empty queue and never reach the remaining queues.
 */
class RedisQueueConfigTest extends TestCase
{
    public function test_read_timeout_exceeds_block_for(): void
    {
        $readTimeout = (float) config('database.redis.default.read_timeout');
        $blockFor = (int) config('queue.connections.redis.block_for', 0);

        $this->assertGreaterThan(
            $blockFor,
            $readTimeout,
            'REDIS_READ_TIMEOUT must exceed REDIS_QUEUE_BLOCK_FOR or multi-queue workers stall.'
        );
    }
}
