<?php

use App\Http\Controllers\ReportController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('institution', 'pages::institution.edit')->name('institution.edit')->middleware('can:institution.view');
    Route::livewire('academic', 'pages::academic.index')->name('academic.index')->middleware('can:academic.view');
    Route::livewire('students', 'pages::students.index')->name('students.index')->middleware('can:student.view');
    Route::livewire('students/{student}', 'pages::students.show')->name('students.show')->middleware('can:student.view');
    Route::livewire('teachers', 'pages::teachers.index')->name('teachers.index')->middleware('can:teacher.view');
    Route::livewire('guardians', 'pages::guardians.index')->name('guardians.index')->middleware('can:guardian.view');
    Route::livewire('scores', 'pages::scores.index')->name('scores.index')->middleware('can:scores.view');
    Route::livewire('portal', 'pages::portal.index')->name('portal.index')->middleware('can:portal.view');
    Route::livewire('enrollments', 'pages::enrollments.index')->name('enrollments.index')->middleware('can:enrollment.view');
    Route::livewire('enrollments/create', 'pages::enrollments.create')->name('enrollments.create')->middleware('can:enrollment.create');
    Route::livewire('reports', 'pages::reports.index')->name('reports.index')->middleware('can:reports.view');
});

Route::middleware(['auth', 'verified', 'can:reports.print'])->group(function () {
    Route::get('reports/boletin/{student}', [ReportController::class, 'boletin'])->name('reports.boletin');
    Route::get('reports/constancia/{enrollment}', [ReportController::class, 'constancia'])->name('reports.constancia');
    Route::get('reports/listado/{classroom}', [ReportController::class, 'listado'])->name('reports.listado');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
