<?php

test('security headers are present on every response', function () {
    actingAsAdmin();

    $response = $this->get(route('dashboard'));

    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'");
});

test('security headers are present on the login page for guests too', function () {
    $response = $this->get(route('login'));

    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Content-Security-Policy');
});
