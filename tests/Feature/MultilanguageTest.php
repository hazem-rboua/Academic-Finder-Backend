<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultilanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_english_messages_by_default(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'user_type' => UserType::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
        
        $this->assertStringContainsString('incorrect', $response->json('errors.email.0'));
    }

    public function test_api_returns_arabic_messages_when_requested(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'user_type' => UserType::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Accept-Language' => 'ar',
        ])->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
        
        $this->assertStringContainsString('غير صحيحة', $response->json('errors.email.0'));
    }

    public function test_successful_login_returns_translated_message(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'user_type' => UserType::ADMIN,
            'is_active' => true,
        ]);

        // Test English
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Test Arabic
        $response = $this->withHeaders([
            'Accept-Language' => 'ar',
        ])->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
    }

    public function test_logout_returns_translated_message(): void
    {
        $user = User::factory()->create([
            'user_type' => UserType::ADMIN,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token', ['admin'])->plainTextToken;

        // Test English
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept-Language' => 'en',
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out',
            ]);

        // Create new token for Arabic test
        $token = $user->createToken('test-token', ['admin'])->plainTextToken;

        // Test Arabic
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept-Language' => 'ar',
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'تم تسجيل الخروج بنجاح',
            ]);
    }
}

