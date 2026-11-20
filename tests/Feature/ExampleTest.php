<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $user = User::create([
            'name' => 'Marketing Prolabios',
            'email' => 'marketing@prolabios.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'initial' => 'MKT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
    }
}
