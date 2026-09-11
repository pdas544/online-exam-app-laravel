<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Single-node Realtime guards: scaling stays off (one VPS needs no Redis
 * pub-sub mesh) and the browser-origin allow-list is env-driven so prod can
 * lock it down without a code change.
 */
class ReverbConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('REVERB_ALLOWED_ORIGINS');
        unset($_ENV['REVERB_ALLOWED_ORIGINS'], $_SERVER['REVERB_ALLOWED_ORIGINS']);

        parent::tearDown();
    }

    private function loadReverbConfig(): array
    {
        return require config_path('reverb.php');
    }

    private function setOriginsEnv(string $value): void
    {
        putenv("REVERB_ALLOWED_ORIGINS={$value}");
        $_ENV['REVERB_ALLOWED_ORIGINS'] = $value;
        $_SERVER['REVERB_ALLOWED_ORIGINS'] = $value;
    }

    public function test_scaling_is_disabled_by_default(): void
    {
        $config = $this->loadReverbConfig();

        $this->assertFalse($config['servers']['reverb']['scaling']['enabled']);
    }

    public function test_allowed_origins_default_open_only_when_unset(): void
    {
        $config = $this->loadReverbConfig();

        $this->assertEquals(['*'], $config['apps']['apps'][0]['allowed_origins']);
    }

    public function test_allowed_origins_parses_comma_list(): void
    {
        $this->setOriginsEnv('https://exam.example.com, https://www.exam.example.com');

        $config = $this->loadReverbConfig();

        $this->assertEquals(
            ['https://exam.example.com', 'https://www.exam.example.com'],
            $config['apps']['apps'][0]['allowed_origins']
        );
    }
}
