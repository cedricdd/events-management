<?php

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            //We are getting a NotFoundHttpException on an api route as a result of a ModelNotFoundException
            //This is a workaround to return a 404 response with a custom message
            if ($e instanceof NotFoundHttpException && request()->is('api/*') && ($e->getPrevious() instanceof ModelNotFoundException)) {
                //Get the model name from the exception message
                $model = Str::afterLast($e->getPrevious()->getModel(), '\\'); //extract Model name
                return response()->json(
                    ['message' => $model . ' not found'], 
                    404);
            }
        });
    })->create();
