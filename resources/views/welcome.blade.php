@extends('layouts.app')

@section('title', 'Fondasi aplikasi')

@section('content')
    <section aria-labelledby="page-title" class="mx-auto flex min-h-[calc(100vh-9rem)] max-w-5xl items-center px-6 py-16 lg:px-8">
        <div class="max-w-3xl">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-orange-700">
                Project foundation
            </p>

            <h1 id="page-title" class="mt-3 text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl">
                Dieselindo PeopleHub
            </h1>

            <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                Fondasi aplikasi HRIS untuk PT Dieselindo Utama Nusa dan anak perusahaan
                telah aktif. Modul bisnis akan ditambahkan bertahap setelah rancangan
                arsitektur dan keamanan disetujui.
            </p>

            <div class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-label="Status aplikasi">
                <p class="text-sm font-medium text-slate-500">Status saat ini</p>
                <p class="mt-2 text-base font-semibold text-slate-900">Phase 1 — Project setup selesai</p>
            </div>
        </div>
    </section>
@endsection
