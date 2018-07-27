<?php

namespace App\Services;

use App\User;
use Jasny\ValidationResult;
use Illuminate\Support\Arr;
use Jasny\SSO\Server as FactoryServer;

class SSOServer extends FactoryServer
{
    /**
     * Get the API secret of a broker and other info.
     *
     * @param string $brokerId
     *
     * @return array
     */
    protected function getBrokerInfo($brokerId)
    {
        $brokers = config('sso.brokers');

        return Arr::first($brokers, function ($broker) use ($brokerId) {
            return $broker['appId'] === $brokerId;
        });
    }

    /**
     * Authenticate using user credentials.
     *
     * @param string $username
     * @param string $password
     *
     * @return ValidationResult
     */
    protected function authenticate($username, $password): ValidationResult
    {
        if (!isset($username)) {
            return ValidationResult::error("username isn't set");
        }

        if (!isset($password)) {
            return ValidationResult::error("password isn't set");
        }

        if (Auth::attempt(['email' => $username, 'password' => $password])) {
            return ValidationResult::error('Invalid credentials');
        }

        return ValidationResult::success();
    }

    /**
     * Get the user information.
     *
     * @return array
     */
    protected function getUserInfo($username)
    {
        return User::where('email', $username)->first();
    }
}
