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

// Route::get('/', function()
// {
// 	return View::make('hello');
// });

Route::get('create', function()
{
	$user = Sentry::createUser([
		'email'		=> 'calum@host.com',
		'password'	=> 'password',
		'activated' => 'true'
	]);

	return 'User Created';
});

Route::post('login', function()
{
	try
	{
		// get user
		$user = Sentry::authenticate(Input::all(), false);

		// create token
		$token = hash('sha256', Str::random(10), false);

		// assign token
		$user->api_token = $token;

		// create user
		$user->save();

		return Response::json([
			'token'		=> $token,
			'user' 		=> $user->toArray()
		]);
	}
	catch(Exception $e)
	{
		App::abort(404, $e->getMessage());
	}
});

Route::group(['prefix' => 'api', 'before' => 'auth.token'], function()
{
	Route::get('/', function()
	{
		return 'Protected Resource';
	});
});

Route::filter('auth.token', function($route, $request)
{
	// get token from request
	$payload = $request->header('X-Auth-Token');

	// get user model
	$userModel = Sentry::getUserProvider()->createModel();

	// find the user with matching token
	$user = $userModel->where('api_token', $payload)->first();

	// if token or user aren't found, return 401
	if (! $payload || ! $user)
	{
		$response = Response::json([
			'error' 	=> true,
			'message'	=> 'Not authenticated',
			'code'		=> 401
			], 401
		);

		$response->header('Content-Type', 'application/json');
		return $response;
	}
});

Route::get('restaurants', [

	'before' => 'auth.token',

	function()
	{
		$restaurants = [];
		for ($i = 0; $i < 10; $i++)
		{
			$restaurant['name'] = 'rest_'.$i;
			$restaurant['location'] = 'loc_'.$i;
			$restaurants[] = $restaurant;
		}

		return Response::json($restaurants, 200);
	}
]);

Route::get('account', [

	'before' => 'auth.token',

	function()
	{
		$user = Sentry::getUser();
		$token = $user->tokens()->where('client', BrowserDetect::toString())->first();

		return Response::json([
			'user' => $user->toArray(),
			'token' => $token->toArray()
		]);
	}
]);