<?php

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
    $this->get('/')
        ->assertOk()
        ->assertViewIs('welcome')
        ->assertSee('<html lang="id">', false)
        ->assertSee('<header', false)
        ->assertSee('<main id="main-content"', false)
        ->assertSee('<footer', false)
        ->assertSee('Lewati ke konten utama')
        ->assertSee('Dieselindo PeopleHub')
        ->assertSee('Phase 1 — Project setup selesai');
});
