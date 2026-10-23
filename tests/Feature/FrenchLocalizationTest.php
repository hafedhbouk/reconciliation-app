<?php

use App\Models\Import;
use App\Models\User;
use App\Support\DateDisplay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

test('instants are shown in French in the display timezone', function () {
    // Stocké en UTC ; la Tunisie est en UTC+1 toute l'année.
    expect(DateDisplay::dateTime(Carbon::parse('2026-10-22T19:50:08Z')))->toBe('22/10/2026 à 20:50:08')
        ->and(DateDisplay::dateTime('2026-10-22 19:50:08'))->toBe('22/10/2026 à 20:50:08') // texte de la base = UTC
        ->and(DateDisplay::dateTime(null))->toBeNull()
        ->and(DateDisplay::dateOf('2026-06-30 23:30:00'))->toBe('01/07/2026'); // le jour local a déjà changé

    config(['app.display_timezone' => 'UTC']);
    expect(DateDisplay::dateTime('2026-10-22 19:50:08'))->toBe('22/10/2026 à 19:50:08');
});

test('calendar dates are never shifted by the timezone', function () {
    expect(DateDisplay::date('2026-06-30'))->toBe('30/06/2026')
        ->and(DateDisplay::date(Carbon::parse('2026-07-01')))->toBe('01/07/2026')
        ->and(DateDisplay::date(''))->toBeNull();
});

test('dates stay stored in UTC and comparable in the database', function () {
    Carbon::setTestNow('2026-10-22 19:50:08'); // horloge de l'application = UTC
    $import = Import::factory()->create();

    expect(DB::table('imports')->where('id', $import->id)->value('created_at'))->toBe('2026-10-22 19:50:08')
        ->and(Import::query()->where('created_at', '<', '2026-10-22 20:00:00')->count())->toBe(1)
        ->and(DateDisplay::dateTime($import->fresh()->created_at))->toBe('22/10/2026 à 20:50:08');
    Carbon::setTestNow();
});

test('the @datetime directive and relative dates are in French', function () {
    expect(Blade::render('@datetime($value)', ['value' => Carbon::parse('2026-10-22T19:50:08Z')]))->toBe('22/10/2026 à 20:50:08')
        ->and(Blade::render('@datetime($value)', ['value' => null]))->toBe('—')
        ->and(DateDisplay::relative(now()->subMinutes(5)))->toBe('il y a 5 minutes');
});

test('the application speaks French on the login screen and in validation messages', function () {
    expect(app()->getLocale())->toBe('fr');

    $this->get(route('login'))->assertOk()
        ->assertSee('Se connecter')->assertSee('Mot de passe')->assertSee('Mot de passe oublié ?')
        ->assertDontSee('Log in')->assertDontSee('Forgot your password?');

    $this->from(route('login'))->post(route('login'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors(['email' => 'Le champ adresse e-mail est obligatoire.']);

    $user = User::factory()->create();
    $this->from(route('login'))->post(route('login'), ['email' => $user->email, 'password' => 'mauvais'])
        ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte.']);
});

test('table data sends French dates instead of raw ISO timestamps', function () {
    actingAsAdmin();
    $import = Import::factory()->create(['created_at' => '2026-10-22 19:50:08']);

    $this->getJson(route('admin.imports.data').'?draw=1&start=0&length=10')->assertOk()
        ->assertJsonPath('data.0.created_at', '22/10/2026 à 20:50:08')
        ->assertDontSee('2026-10-22T19:50:08');
});
