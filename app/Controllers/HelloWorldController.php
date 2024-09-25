<?php

namespace App\Controllers;

class HelloWorldController extends Controller
{
    /**
     * Simple hello world endpoint
     * GET /hello-world
     */
    public function getHelloWorld()
    {
        return [
            "message" => "Hello, World!",
            "timestamp" => date('Y-m-d H:i:s'),
            "status" => "success"
        ];
    }
}
