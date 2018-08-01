<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('home');
    }

    /**
     * Redirect user to Saml page.
     *
     * @return \Illuminate\Http\Response
     */
    public function samlRedirect()
    {
        return view('auth.saml')->with([
            'data' => session()->get('data', []),
            'destination' => session()->get('destination', ''),
        ]);
    }
}
