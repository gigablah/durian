Durian
======

A pungent PHP 5.5 microframework based on generator-style middleware.

Why?
----

Because I can.

Installation
------------

Use [Composer][1] to install the gigablah/durian library by adding it to your `composer.json`.

```json
{
    "require": {
        "gigablah/durian": "~0.0.1"
    }
}
```

Usage
-----

```php
$app = new Durian\Application();
$app->route('/hello/{name}', function () {
    return 'Hello '.$this->param('name');
});
$app->run();
```

Nothing special there. But since this is a PHP 5.5 microframework, it has been tailored to take advantage of shiny new [generator functions][2]. We'll explore that starting with `Application::route`.

Routing
-------

```php
$app->route('/hello', function () use ($app) {
    $app['giant_library']->performExpensiveOperation();
    yield 'Hello ';
    $app['giant_library']->performCleanUp();
})->route('/{name}', function () {
    return $this->last().$this->param('name');
})->get(function () {
    return ['method' => 'GET', 'message' => $this->last()];
})->post(function () {
    return ['method' => 'POST', 'message' => $this->last()];
});
```

Instead of the hierarchical routing syntax found in some other microframeworks, we use method chaining. The `yield` keyword allows us to pass execution to the next matching segment. Upon reaching the end of the chain, the execution flow is passed back to all generators in reverse order. Therefore, code before and after a `yield` statement will essentially "wrap" subsequent route handlers.

Why method chaining? The simple reason is that embedding the next route or method segment inside the route handler function forces us to execute the handler first before proceeding, thus potentially incurring expensive initialization code even if the request results in an error. Here, we stack the handler functions as each segment matches, and execute all of them in one go only if the route and method match is successful.

(At least, that was the original intention. Currently the framework utilizes [nikic/fast-route][3], which compiles all the routes into a single regex mapping to all handler stack combinations.)

Note that `Application::route` starts a new segment and returns a new `Route` object. The method functions map to all the common HTTP request methods (get, post, put, delete, patch, options) and return the same `Route`. All the routing methods accept an arbitrary number of handler functions, so you can encapsulate surrounding operations (such as the ones in the example above) into a separate generator:

```php
$expensiveOperation = function () use ($app) {
    $app['giant_library']->performExpensiveOperation();
    yield;
    $app['giant_library']->performCleanUp();
};

$app->route('/hello', $expensiveOperation, function () use ($app) {
    return 'Hello ';
})->route(...);
```

Return values are automatically converted to Symfony2 `Response` objects. Arrays will result in a `JsonResponse`. You may also manually craft a response:

```php
$app->route('/tea', function () use ($app) {
    $this->setResponse(new Symfony\Component\HttpFoundation\Response("I'm a teapot", 418));
});
```

Or throw an exception:

```php
$app->route('/404', function () use ($app) {
    throw new Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
});
```

Subrequests are performed by calling `Application::run`:

```php
$app->route('/song/{id:[0-9]+}', function () use ($app) {
    $id = $this->param('id');
    return [
        'id' => $id,
        'artist' => $app->run('GET', "/artists-by-song/$id")->getContent()
    ];
});
```

Context
-------

See the lavish use of `$this` in the examples above? That's made possible by the automatic binding of each closure or generator to the `Context` object. Each time the application handles a request or subrequest, a new context is pushed onto the stack.

The context is a simple container for the `Request` and `Response` objects. It also holds the route parameters and the return values from each handler.

```php
$app->route('/hello/{name}', function () {
    return $this->param('name');
})->get(function () {
    $name = $this->last();
    $request = $this->getRequest();
    if (!$this->hasResponse()) {
        $this->setResponse("Hello $name");
    }
});
```

`Context::last` holds the return (or yielded) value from the previous handler. This is a way to pass information downstream other than using the application container or request attributes.

Middleware
----------

The pattern of generator stacking applies to the entire application flow, not just routing. All middlewares boil down to a callable or generator function with an optional test function. They can be defined using `Application::handler`:

```php
$responseTimeMiddleware = $app->handler(function () {
    $time = microtime(true);
    yield;
    $this->getResponse()->headers->set('X-Response-Time', sprintf('%fms', microtime(true) - $time));
}, function () use ($app) {
    return isset($app['debug']) && $app['debug'];
});
```

This returns a `Handler` object which can now be added to the front or back of the middleware stack:

```php
$app->before($responseTimeMiddleware);
$app->after($someOtherMiddleware);
```

You can modify the entire stack with `Application::handlers`. The second parameter determines whether to replace the whole stack (true by default).

```php
$app->handlers([
    $responseTimeMiddleware,
    new Durian\Middleware\RouterMiddleware()
]);
```

Middleware can also be defined as concrete classes by extending `AbstractMiddleware`.

Exception Handling
------------------

Whenever an exception is thrown, the application bubbles it up through all the generators in the stack.

This means that you can intercept any exception by wrapping a `yield` statement with a try/catch block:

```php
$exceptionHandlerMiddleware = $app->handler(function () {
    try {
        yield;
    } catch (\Exception $exception) {
        if ($exception instanceof HttpException) {
            $this->setResponse(Response::create(
                $exception->getMessage() ?: Response::$statusTexts[$exception->getStatusCode()],
                $exception->getStatusCode(),
                $exception->getHeaders()
            ));
        } else {
            $this->setResponse(Response::create($exception->getMessage(), 500));
        }
    }
});
```

Dependency Injection
--------------------

Dependency injection? What's that? :)

Other than the fact that the application container is based on `Pimple`, a lightweight DIC (or service locator, if you're so inclined), no parameter matching is currently performed on route handlers. Eventually I'd like to have it implemented as an optional trait. Watch this space!

License
-------

Released under the MIT license. See the LICENSE file for details.

Credits
-------

This project was inspired by the following:

* [koa][4]
* [Martini][5]
* [Bullet][6]
* [Slim][7]

[1]: http://getcomposer.org
[2]: http://www.php.net/manual/en/language.generators.overview.php
[3]: https://github.com/nikic/FastRoute
[4]: https://github.com/koajs/koa
[5]: https://github.com/codegangsta/martini
[6]: https://github.com/vlucas/bulletphp
[7]: https://github.com/codeguy/Slim
