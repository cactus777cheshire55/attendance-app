<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_requires_name(): void
    {
        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertStringContainsString(
            'お名前を入力してください',
            session('errors')->first('name')
        );
    }

    public function test_register_requires_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'メールアドレスを入力してください',
            session('errors')->first('email')
        );
    }

    public function test_register_requires_password_minimum_8_characters(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $response->assertSessionHasErrors('password');

        $message = session('errors')->first('password');

        $this->assertStringContainsString(
            'パスワードは8文字以上で入力してください',
            $message
        );
    }

    public function test_register_requires_password_confirmation_to_match(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $response->assertSessionHasErrors('password');

        $message = session('errors')->first('password');

        $this->assertStringContainsString(
            'パスワードと一致しません',
            $message
        );
    }

    public function test_register_requires_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors('password');

        $message = session('errors')->first('password');

        $this->assertStringContainsString(
            'パスワードを入力してください',
            $message
        );
    }

    public function test_register_saves_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(
            Hash::check('password123', $user->password)
        );
    }

    public function test_login_requires_email(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->post('/login', [
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'メールアドレスを入力してください',
            session('errors')->first('email')
        );
    }

    public function test_login_requires_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors('password');

        $this->assertStringContainsString(
            'パスワードを入力してください',
            session('errors')->first('password')
        );
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors();

        $errors = session('errors')->getBag('default')->all();

        $this->assertContains(
            'ログイン情報が登録されていません',
            $errors
        );
    }

    public function test_admin_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password123',
            'admin_status' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors();
    }
}
