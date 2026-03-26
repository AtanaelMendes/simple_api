<?php

use App\Controllers\HelloWorldController;
use App\Controllers\UserController;

$app = new App();

/** @see App\Controllers\HelloWorldController::getHelloWorld() */
$app->get("hello-world", [HelloWorldController::class, "getHelloWorld"]);

/** @see App\Controllers\MenuPrincipalController::index() */
$app->get("menus", [MenuPrincipalController::class, "index"]);

