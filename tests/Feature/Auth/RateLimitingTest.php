<?php

test('register is throttled after 6 rapid attempts', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->post('/register', []);
    }

    $response = $this->post('/register', []);

    $response->assertStatus(429);
});

test('forgot-password is throttled after 6 rapid attempts', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->post('/forgot-password', []);
    }

    $response = $this->post('/forgot-password', []);

    $response->assertStatus(429);
});

test('reset-password is throttled after 6 rapid attempts', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->post('/reset-password', []);
    }

    $response = $this->post('/reset-password', []);

    $response->assertStatus(429);
});
