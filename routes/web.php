<?php

use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Home;
use App\Livewire\Species\Index as SpeciesIndex;
use App\Livewire\Species\Show as SpeciesShow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', Home::class)->name('home');
    Route::get('/species', SpeciesIndex::class)->name('species.index');
    Route::get('/species/{idsp}', SpeciesShow::class)->whereNumber('idsp')->name('species.show');
    Route::get('/audit', AuditIndex::class)->middleware('can:admin')->name('audit.index');

    Route::middleware('can:update-gbif')->prefix('review')->name('review.')->group(function () {
        Route::get('/matches', \App\Livewire\Review\Matches::class)->name('matches');
        Route::get('/occurrences', \App\Livewire\Review\Occurrences::class)->name('occurrences');
        Route::get('/changes', \App\Livewire\Review\Changes::class)->name('changes');
    });

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
