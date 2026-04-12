<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('adds hsts on secure responses', function () {
    $response = $this->call('GET', '/', [], [], [], [
        'HTTPS' => 'on',
        'SERVER_PORT' => 443,
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    $response->assertStatus(200);
    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('marks the session cookie as http only', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);

    $sessionCookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($sessionCookie)->not->toBeNull();
    expect($sessionCookie->isHttpOnly())->toBeTrue();
});

it('marks the session cookie as secure on https responses', function () {
    $response = $this->call('GET', '/login', [], [], [], [
        'HTTPS' => 'on',
        'SERVER_PORT' => 443,
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    $response->assertStatus(200);

    $sessionCookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($sessionCookie)->not->toBeNull();
    expect($sessionCookie->isSecure())->toBeTrue();
});
