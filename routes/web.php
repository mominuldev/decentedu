<?php

use Illuminate\Support\Facades\Route;

// The React SPA owns client-side routing; every non-API path renders the shell.
Route::view('/{any?}', 'app')->where('any', '^(?!api).*$');
