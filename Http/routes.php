<?php

Route::group(['middleware' => ['web', 'auth', 'roles'], 'roles' => ['admin'], 'prefix' => 'qonversion', 'namespace' => 'Modules\QonversionIntegration\Http\Controllers'], function () {
    Route::post('/save-settings', 'SettingsController@save')->name('qonversion.save_settings');
});
