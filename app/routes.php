<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::get('/', function()
{
	return View::make('hello');
});

Route::get('create', function()
{
	$user = Sentry::createUser([
		'email'		=> 'calum@host.com',
		'password'	=> 'password',
		'activated' => 'true'
	]);

	return 'User Created';
});
