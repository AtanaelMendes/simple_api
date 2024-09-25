<?php

use App\Controllers\HelloWorldController;
use App\Controllers\UserController;

$app = new App();

/** @see App\Controllers\HelloWorldController::getHelloWorld() */
$app->get("hello-world", [HelloWorldController::class, "getHelloWorld"]);

/** @see App\Controllers\UserController::index() */
$app->get("users", [UserController::class, "index"]);

/** @see App\Controllers\UserController::show() */
$app->get("users/{id}", [UserController::class, "show"]);

/** @see App\Controllers\UserController::store() */
$app->post("users", [UserController::class, "store"]);

/** @see App\Controllers\UserController::update() */
$app->put("users/{id}", [UserController::class, "update"]);

/** @see App\Controllers\UserController::destroy() */
$app->delete("users/{id}", [UserController::class, "destroy"]);

