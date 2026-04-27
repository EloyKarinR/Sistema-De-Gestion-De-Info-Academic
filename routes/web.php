<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::view('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('institution', 'pages::institution.edit')->name('institution.edit');
    Route::livewire('academic', 'pages::academic.index')->name('academic.index');
    Route::livewire('students', 'pages::students.index')->name('students.index');
    Route::livewire('students/{student}', 'pages::students.show')->name('students.show');
    Route::livewire('enrollments', 'pages::enrollments.index')->name('enrollments.index');
    Route::livewire('enrollments/create', 'pages::enrollments.create')->name('enrollments.create');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
