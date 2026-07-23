<?php

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\ExceptionRecord;
use App\Models\Transaction;

test('dashboard renders for an authenticated admin with KPI numbers matching fixtures', function () {
    actingAsAdmin();
    Transaction::factory()->count(3)->create();
    ExceptionRecord::factory()->create(['type' => ExceptionType::Unmatched->value, 'status' => ExceptionStatus::Open->value]);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee(__('Tableau de bord'));
    $response->assertSee('3'); // total transactions KPI
    $response->assertSee(__('Exceptions ouvertes'));
});

test('a plain user without permissions sees the dashboard but not permission-gated cards', function () {
    actingAsPlainUser();

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee(__('Tableau de bord'));
    $response->assertDontSee(__('Exceptions ouvertes'));
    $response->assertDontSee(__('Imports ce mois-ci'));
    $response->assertDontSee(__('Taux de rapprochement'));
});

test('guest is redirected to login when accessing the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
