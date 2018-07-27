<?php

namespace App\Http\Controllers;

use App\Services\SSOServer;
use Illuminate\Http\Request;

class SSOController extends Controller
{
    /**
     * Handle the SSO logic.
     *
     * @param SSOServer $ssoServer
     * @param Request   $request
     **/
    public function __invoke(SSOServer $ssoServer, Request $request)
    {
        $command = $request->input('command');
        if (!$command || !method_exists($ssoServer, $command)) {
            return response([
                'status' => 'error',
                'error' => 'This rquest cannot be handled by this server',
            ], 400);
        }
        $user = $ssoServer->{$command}();
        if ($user) {
            return response()->json($user);
        }
    }
}
