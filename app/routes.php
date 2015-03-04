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

// Route::filter('auth.token', function($route, $request)
// {
// 	// get token from request
// 	$payload = $request->header('X-Auth-Token');

// 	// get user model
// 	$userModel = Sentry::getUserProvider()->createModel();

// 	// find the user with matching token
// 	$user = $userModel->where('api_token', $payload)->first();

// 	// if token or user aren't found, return 401
// 	if (! $payload || ! $user)
// 	{
// 		$response = Response::json([
// 			'error' 	=> true,
// 			'message'	=> 'Not authenticated',
// 			'code'		=> 401
// 			], 401
// 		);

// 		$response->header('Content-Type', 'application/json');
// 		return $response;
// 	}
// });

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

class Token extends Eloquent
{
	protected $table = 'tokens';
	protected $fillable = ['api_token', 'client', 'user_id', 'expires_on'];

	public function scopeValid()
	{
		return ! Carbon\Carbon::createFromTimeStamp(strtotime($this->expires_on))->isPast();
	}

	public function user()
	{
		return $this->belongsTo('User', 'user_id');
	}
}


// // bare
// Route::filter('auth.token', function($route, $request)
// {
// 	$authenticated = false;
// 	if (! $authenticated)
// 	{
// 		$response = Response::json([
// 			'error'		=> true,
// 			'message'	=> 'Not authenticated',
// 			'code'		=> 401
// 			], 401
// 		);

// 		$response->header('Content-Type', 'application/json');
// 		return $response;
// 	}
// });

Route::filter('auth.token', function($route, $request)
{
	$authenticated = false;

	// take username and password from request if they exist
	if ($email = $request->getUser() && $password = $request->getPassword())
	{
		$credentials = [
			'email' => $request->getUser(),
			'password' => $request->getPassword()
		];

		// attempt to autenticate using those details
		if (Auth::once($credentials))
		{
			$authenticated = true;

			// if no token exists for this user, create one
			if (! Auth::user()->tokens()->where('client', BrowserDetect::toString())->first())
			{
				$token = [];

				$token['api_token'] = hash('sha256', Str::random(10), false);
				$token['client'] = BrowserDetect::toString();
				$token['expires_on'] = Carbon\Carbon::now()->addMonth()->toDateTimeString();

				Auth::user()->tokens()->save(new Token($token));
			}
		}
	}

	// if an auth token is present, find the matching token and login the user
	if ($payload = $request->header('X-Auth-Token'))
	{
		$userModel = Sentry::getUserProvider()->createModel();
		$token = Token::valid()
			->where('api_token', $payload)
			->where('client', BrowserDetect::toString())
			->first();

		if ($token)
		{
			Sentry::login($token->user);
			$authenticated = true;
		}
	}

	// if the user is genuine but not logged in, log them in
	if ($authenticated && ! Sentry::check())
	{
		Sentry::login(Auth::user());
	}

	// if user is not genuine, return 401
	if (! $authenticated)
	{
		$response = Response::json([
			'error'		=> true,
			'message'	=> 'Not authenticated',
			'code'		=> 401
			], 401
		);

		$response->header('Content-Type', 'application/json');
		return $response;
	}
});