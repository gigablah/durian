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
        "gigablah/durian": "~0.0.2"
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
$app->run(Symfony\Component\HttpFoundation\Request::createFromGlobals());
```

Nothing special there. The `Application` container is based on [Pimple][2] and inherits its functions for defining lazy loading services. We also make use of the Symfony2 `Request` object so you have access to request headers, parameters and cookies. But since this is a PHP 5.5 microframework, it has been tailored to take advantage of shiny new [generator functions][3]. We'll explore that starting with `Application::route`.

Routing
-------

```php
$app['awesome_library'] = $app->share(function ($app) {
    return new MyAwesomeLibrary();
});

$app->route('/hello', function () use ($app) {
    $app['awesome_library']->performExpensiveOperation();
    yield 'Hello ';
    $app['awesome_library']->performCleanUp();
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

(At least, that was the original intention. Currently the framework utilizes [nikic/fast-route][4], which compiles all the routes into a single regex that maps to handler stack combinations.)

Note that `Application::route` starts a new segment and returns a new `Route` object. The method functions map to all the common HTTP request methods (get, post, put, delete, patch, options) and return the same `Route`. All the routing methods accept an arbitrary number of handler functions, so you can encapsulate surrounding operations (such as the ones in the example above) into a separate generator:

```php
$expensiveOperation = function () use ($app) {
    $app['awesome_library']->performExpensiveOperation();
    yield;
    $app['awesome_library']->performCleanUp();
};

$app->route('/hello', $expensiveOperation, function () {
    return 'Hello ';
})->route(...);
```

You don't necessarily have to chain route segments, the old-fashioned way of defining entire paths will still work fine:

```php
// Routes will support GET by default
$app->route('/users');

// Methods can be declared without handlers
$app->route('/users/{name}')->post();

// Declare multiple methods separated by pipe characters
$app->route('/users/{name}/friends')->method('GET|POST');
```

Return values are automatically converted to Symfony2 `Response` objects. Arrays will result in a `JsonResponse`. You may also manually craft a response:

```php
$app->route('/tea', function () use ($app) {
    $this->response("I'm a teapot", 418);
});
```

Or throw an exception:

```php
$app->route('/404', function () {
    // Alternatively pass in an exception object as the first parameter
    $this->error('Not Found', 404);
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
    $request = $this->request();
    if (!$this->response()) {
        $this->response("Hello $name");
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
    $this->response()->headers->set('X-Response-Time', sprintf('%fms', microtime(true) - $time));
}, function () use ($app) {
    return $this->master() && $app['debug'];
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

Handler Injection
-----------------

If a handler function returns another `Handler`, it will be inserted into the current position of the execution stack.

Similarly, if a handler function produces a generator that yields handlers, the whole collection will be inserted into the stack. This is exactly how the router middleware works.

In essence, the handler stack is recursively iterated over as a multidimensional array.

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
            $this->response(
                $exception->getMessage(),
                $exception->getStatusCode(),
                $exception->getHeaders()
            );
        } else {
            $this->response($exception->getMessage(), 500);
        }
    }
});
```

For pretty exception traces, you can make use of the [filp/whoops][5] library by including it in your composer.json:

```json
{
    "require": {
        "gigablah/durian": "~0.0.2",
        "filp/whoops": "~1.0"
    }
}
```

Then, register `WhoopsMiddleware` as the first handler in your application:

```php
$app->handlers([
    new Durian\Middleware\WhoopsMiddleware()
    new Durian\Middleware\RouterMiddleware()
]);
```

Dependency Injection
--------------------

Dependency injection? What's that? :)

Other than the fact that the application container is based on `Pimple`, a lightweight DIC (or service locator, if you're so inclined), no parameter matching is currently performed on route handlers. Eventually I'd like to have it implemented as an optional trait. Watch this space!

HttpKernelInterface
-------------------

The `Application` container implements Symfony2's `HttpKernelInterface`, so you can compose it with other compatible applications via [Stack][6].

Method List
-----------

### Application

```php
$app->run($request_or_method, $path);
$app->route($path, ...$handlers);
$app->handler($callable, $optional_callable);
$app->before($callable, $optional_callable);
$app->after($callable, $optional_callable);
$app->handlers($handlers, $replace);
$app->handle($request, $type, $catch);
```

### Route

```php
$route->route($path, ...$handlers);
$route->path();
$route->method($methods, ...$handlers);
$route->get(...$handlers);
$route->post(...$handlers);
$route->put(...$handlers);
$route->delete(...$handlers);
$route->patch(...$handlers);
$route->options(...$handlers);
$route->head(...$handlers);
$route->dump();
```

### Context

```php
$context->request();
$context->request($request);
$context->response();
$context->response($response);
$context->response($content, $status, $headers);
$context->error($exception);
$context->error($message, $status, $headers, $code);
$context->master();
$context->map($params);
$context->param($key);
$context->param($key, $default);
$context->append($output);
$context->last();
```

License
-------

Released under the MIT license. See the LICENSE file for details.

Credits
-------

This project was inspired by the following:

* [koa][7]
* [Martini][8]
* [Bullet][9]
* [Slim][10]

[1]: http://getcomposer.org
[2]: http://pimple.sensiolabs.org
[3]: http://www.php.net/manual/en/language.generators.overview.php
[4]: https://github.com/nikic/FastRoute
[5]: https://github.com/filp/whoops
[6]: http://stackphp.com
[7]: https://github.com/koajs/koa
[8]: https://github.com/codegangsta/martini
[9]: https://github.com/vlucas/bulletphp
[10]: https://github.com/codeguy/Slim
