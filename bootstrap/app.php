<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions()
    ->withMiddleware(static function(Middleware $middleware){
        $middleware->removeFromGroup('api',$middleware->getMiddlewareGroups()['api']);
        $middleware->removeFromGroup('web',$middleware->getMiddlewareGroups()['web']);
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->create();
