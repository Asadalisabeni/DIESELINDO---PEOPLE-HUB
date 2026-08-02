<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the phase three review package contains every required document', function () {
    $documents = [
        'docs/03-design-system/design-system.md',
        'docs/03-design-system/accessibility-i18n.md',
        'docs/03-design-system/phase-3-exit-review.md',
    ];

    foreach ($documents as $document) {
        $path = base_path($document);

        expect($path)->toBeFile()
            ->and(is_readable($path))->toBeTrue();

        $contents = file_get_contents($path);

        expect($contents)
            ->toBeString()
            ->not->toContain('[TODO]')
            ->not->toContain('[TBD]');
    }
});

test('the design system renders every required component family and state', function () {
    $this->get('/design-system')
        ->assertOk()
        ->assertViewIs('design-system.index')
        ->assertSee('Design system')
        ->assertSee('Warna semantik')
        ->assertSee('Tombol &amp; badge', false)
        ->assertSee('Form &amp; validasi', false)
        ->assertSee('Tabel responsif')
        ->assertSee('Modal, drawer &amp; toast', false)
        ->assertSee('Memuat data')
        ->assertSee('Belum ada data')
        ->assertSee('Terjadi kesalahan')
        ->assertSee('Akses dibatasi')
        ->assertSee('role="tablist"', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('x-trap.inert.noscroll', false)
        ->assertSee('aria-live="polite"', false);
});

test('the application shell provides the accessibility baseline', function () {
    $this->get('/home')
        ->assertOk()
        ->assertSee('href="#main-content"', false)
        ->assertSee('aria-label="Navigasi utama"', false)
        ->assertSee('aria-controls="application-sidebar"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('tabindex="-1"', false)
        ->assertSee('aria-disabled="true"', false)
        ->assertSee('meta name="color-scheme" content="light dark"', false);
});

test('users can switch between the supported interface languages', function () {
    $this->from('/home')
        ->post('/locale', ['locale' => 'en'])
        ->assertRedirect('/home');

    expect(session('locale'))->toBe('en');

    $this->get('/home')
        ->assertOk()
        ->assertSee('<html lang="en" class="h-full">', false)
        ->assertSee('Your connected employee workspace')
        ->assertSee('Skip to main content');
});

test('unsupported interface languages are rejected and invalid session values fail closed', function () {
    $this->from('/home')
        ->post('/locale', ['locale' => 'fr'])
        ->assertRedirect('/home')
        ->assertSessionHasErrors('locale');

    $this->withSession(['locale' => 'fr'])
        ->get('/home')
        ->assertOk()
        ->assertSee('<html lang="id" class="h-full">', false);

    expect(session('locale'))->toBeNull();
});

test('indonesian and english translation catalogs have identical keys', function () {
    /** @var array<string, mixed> $indonesian */
    $indonesian = require lang_path('id/ui.php');

    /** @var array<string, mixed> $english */
    $english = require lang_path('en/ui.php');

    $indonesianKeys = array_keys(Arr::dot($indonesian));
    $englishKeys = array_keys(Arr::dot($english));

    sort($indonesianKeys);
    sort($englishKeys);

    expect($indonesianKeys)->toBe($englishKeys)
        ->and($indonesianKeys)->not->toBeEmpty();
});

test('the UI runtime includes dark mode persistence and Alpine focus management', function () {
    $javascript = file_get_contents(resource_path('js/app.js'));
    $stylesheet = file_get_contents(resource_path('css/app.css'));

    expect($javascript)
        ->toBeString()
        ->toContain("window.localStorage.getItem('peoplehub-theme')")
        ->toContain('Alpine.plugin(focus)')
        ->toContain("Alpine.store('theme'")
        ->and($stylesheet)
        ->toBeString()
        ->toContain('@custom-variant dark')
        ->toContain('--color-brand-600')
        ->toContain('--surface-canvas');
});
