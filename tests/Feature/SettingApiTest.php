<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders
        $this->artisan('db:seed');
    }

    public function test_can_get_settings(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/settings');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ]);
    }

    public function test_can_get_system_info(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/system/info');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'app' => [
                            'name',
                            'version',
                            'environment'
                        ],
                        'database',
                        'server',
                        'storage'
                    ]
                ]);
    }

    public function test_can_get_backups_list(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/system/backups');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ]);
    }

    public function test_unauthorized_user_cannot_access_settings(): void
    {
        $user = User::role('Cashier')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/settings');

        $response->assertStatus(403);
    }

    public function test_can_create_backup(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/system/backup');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'filename',
                        'created_at'
                    ]
                ]);
    }
}
