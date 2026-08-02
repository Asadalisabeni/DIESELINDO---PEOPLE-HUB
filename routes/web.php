<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('/design-system', 'design-system.index')->name('design-system');

Route::post('/locale', function (Request $request): RedirectResponse {
    $validated = $request->validate([
        'locale' => ['required', 'string', 'in:id,en'],
    ]);

    $request->session()->put('locale', $validated['locale']);

    return back();
})->name('locale.update');
