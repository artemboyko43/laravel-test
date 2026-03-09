<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['message' => 'Laravel API'];
});
