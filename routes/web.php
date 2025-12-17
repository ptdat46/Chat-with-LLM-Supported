<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MessageSuggestionController;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');
Route::get('/messages', [HomeController::class, 'messages'])->name('messages');
Route::post('/message', [HomeController::class, 'message'])->name('message');
Route::get('/message-suggestions', [MessageSuggestionController::class, 'getSuggestions'])->name('message.suggestions');
Route::get('/test-ollama', [MessageSuggestionController::class, 'testOllama'])->name('test.ollama');
