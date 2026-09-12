<?php

namespace Tests\Unit;

use App\Http\Controllers\AuthController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SanctumRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_no_api_token_trait(): void
    {
        $this->assertNotContains(
            'Laravel\Sanctum\HasApiTokens',
            class_uses_recursive(\App\Models\User::class)
        );
    }

    public function test_dead_json_endpoints_are_gone(): void
    {
        $this->assertFalse(method_exists(AuthController::class, 'profile'));
        $this->assertFalse(method_exists(AuthController::class, 'verifyRole'));
        $this->assertFalse(class_exists(\App\Http\Requests\Auth\VerifyRoleRequest::class));
    }

    public function test_no_personal_access_tokens_table(): void
    {
        $this->assertFalse(Schema::hasTable('personal_access_tokens'));
    }
}
