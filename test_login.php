<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());

echo 'Fortify Username: '.Fortify::username()."\n";

$provider = Auth::guard('web')->getProvider();
echo 'Provider Model: '.$provider->getModel()."\n";

try {
    $credentials = ['USER_EMAIL' => 'admin@example.com'];
    echo 'Attempting to retrieve by credentials: '.json_encode($credentials)."\n";
    $user = $provider->retrieveByCredentials($credentials);
    echo 'User found: '.($user ? $user->USER_EMAIL : 'None')."\n";
} catch (\Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

try {
    echo "Testing Fortify::authenticateUsing callback...\n";
    $callback = Fortify::$authenticateUsingCallback;
    if ($callback) {
        $mockRequest = new \Illuminate\Http\Request(['email' => 'admin@example.com', 'password' => 'password']);
        $user = call_user_func($callback, $mockRequest);
        echo 'User found via callback: '.($user ? $user->USER_EMAIL : 'None')."\n";
    } else {
        echo "No authentication callback defined!\n";
    }
} catch (\Exception $e) {
    echo 'Error during callback test: '.$e->getMessage()."\n";
}

try {
    $credentials = ['email' => 'admin@example.com'];
    echo 'Attempting to retrieve by credentials: '.json_encode($credentials)."\n";
    $user = $provider->retrieveByCredentials($credentials);
    echo 'User found: '.($user ? $user->USER_EMAIL : 'None')."\n";
} catch (\Exception $e) {
    echo "Error (Expected if 'email' doesn't exist and config is 'email'): ".$e->getMessage()."\n";
}
