<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Route;

// SOW §3.13 — public self-registration is disabled; administrators create users.

test('the registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
    expect(Route::has('register'))->toBeFalse();
});

test('nobody can register through the old endpoint', function () {
    $this->post('/register', [
        'name' => 'Test User', 'email' => 'test@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

test('the welcome page no longer offers a register link', function () {
    $this->get('/')->assertOk()->assertSee('Log in')->assertDontSee('>Register<', false);
});
