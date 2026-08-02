<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application uses the approved peoplehub baseline', function () {
    expect(config('app.name'))->toBe('Dieselindo PeopleHub')
        ->and(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(config('app.locale'))->toBe('id')
        ->and(config('app.fallback_locale'))->toBe('en')
        ->and(config('app.faker_locale'))->toBe('id_ID')
        ->and(config('filesystems.disks.public.url'))
        ->toBe(rtrim((string) config('app.url'), '/').'/storage');
});

test('the home page renders the shared application layout', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/home')
        ->assertOk()
        ->assertViewIs('welcome')
        ->assertSee('<html lang="id" class="h-full">', false)
        ->assertSee('id="application-sidebar"', false)
        ->assertSee('<header', false)
        ->assertSee('<main id="main-content"', false)
        ->assertSee('<footer', false)
        ->assertSee('Lewati ke konten utama')
        ->assertSee('Dieselindo PeopleHub')
        ->assertSee('Phase 3 terkunci')
        ->assertSee('Fondasi kerja yang konsisten');
});

test('guests are redirected to the secure login page', function () {
    $this->get('/')
        ->assertRedirect(route('login'));
});
