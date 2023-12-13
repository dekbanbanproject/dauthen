<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('authen_main', [App\Http\Controllers\AuthencodeController::class, 'authen_main'])->name('authen_main');
Route::get('authen_index', [App\Http\Controllers\AuthencodeController::class, 'authen_index'])->name('authen_index');
Route::match(['get','post'],'authencode', [App\Http\Controllers\AuthencodeController::class, 'authencode'])->name('a.authencode');
Route::match(['get','post'],'authencode_visit', [App\Http\Controllers\AuthencodeController::class, 'authencode_visit'])->name('a.authencode_visit');
Route::match(['get','post'],'authencode_patient_save', [App\Http\Controllers\AuthencodeController::class, 'authencode_patient_save'])->name('a.authencode_patient_save');
Route::match(['get','post'],'authencode_visit_save', [App\Http\Controllers\AuthencodeController::class, 'authencode_visit_save'])->name('a.authencode_visit_save');
Route::POST('authen_save', [App\Http\Controllers\AuthencodeController::class, 'authen_save'])->name('a.authen_save');
Route::match(['get','post'],'authencode_index',[App\Http\Controllers\AUTHENCHECKController::class,'authencode_index'])->name('aa.authencode_index');
// Route::match(['get','post'],'getsmartcard_authencode',[App\Http\Controllers\AUTHENCHECKController::class,'getsmartcard_authencode'])->name('getsmartcard_authencode');
Route::match(['get','post'],'smartcard_authencode_save',[App\Http\Controllers\AUTHENCHECKController::class,'smartcard_authencode_save'])->name('smartcard_authencode_save');

Route::match(['get','post'],'fetch_province', [App\Http\Controllers\AuthencodeController::class, 'fetch_province'])->name('fecth.fetch_province');
Route::match(['get','post'],'fetch_amphur', [App\Http\Controllers\AuthencodeController::class, 'fetch_amphur'])->name('fecth.fetch_amphur');
Route::match(['get','post'],'fetch_tumbon', [App\Http\Controllers\AuthencodeController::class, 'fetch_tumbon'])->name('fecth.fetch_tumbon');
Route::match(['get','post'],'fetch_pocode', [App\Http\Controllers\AuthencodeController::class, 'fetch_pocode'])->name('fecth.fetch_pocode');