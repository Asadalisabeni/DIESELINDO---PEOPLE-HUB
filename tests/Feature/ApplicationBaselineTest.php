<?php

test('the application uses the approved peoplehub baseline', function () {
    expect(config('app.name'))->toBe('Dieselindo PeopleHub')
        ->and(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(config('app.locale'))->toBe('id')
        ->and(config('app.fallback_locale'))->toBe('en')
        ->and(config('app.faker_locale'))->toBe('id_ID');
});
