Durian
======

[![Build Status](https://travis-ci.org/gigablah/durian.png?branch=master)](https://travis-ci.org/gigablah/durian) [![Coverage Status](https://coveralls.io/repos/gigablah/durian/badge.png)](https://coveralls.io/r/gigablah/durian)

Durian is a PHP microframework that utilizes the newest features of PHP 5.5 together with lightweight library components to create an accessible, compact framework with performant routing and flexible generator-style middleware.

Why?
----

Because I can.

Installation
------------

Use [Composer][1] to install the gigablah/durian library by adding it to your `composer.json`.

```json
{
    "require": {
        "gigablah/durian": "~0.1"
    }
}
```

Usage
-----

```php
$app = new Durian\Application();
$app->route('/hello/{name}', function () {
    return 'Hello '.$this->params('name');
});
$app->run()->send();
```

Nothing special there. The `Application` container is based on [Pimple][2] and inherits its functions for defining lazy loading services. We also make use of the Symfony2 `Request` object so you have access to request headers, parameters and cookies. But since this is a PHP 5.5 microframework, it has been tailored to take advantage of shiny new [generator functions][3]. We'll explore that starting with the core of our application: the handler stack.

Handlers
--------

When the application is run, the `Request` object is inserted into a `Context` object which is passed through a series of handler functions. They may read information from the request attributes, insert information into the context, create a `Response`, throw an exception and so on. Some of these functions may themselves comprise a series of functions, which are executed in the same manner as the main stack. If there are any generator functions encountered along the way, they are revisited in reverse order after the end of the stack is reached. This entire mechanism is encapsulated in the `Handler` class.

All handlers boil down to an array of callables or generator functions with an optional test function. They can be defined using `Application::handler`:

```php
$responseTimeHandler = $app->handler(function () {
    // this executes before the rest of the stack
    $time = microtime(true);
    yield;
    // this executes after the rest of the stack
    $time = microtime(true) - $time;
    $this->response()->headers->set('X-Response-Time', $time);
}, function () use ($app) {
    // only execute for the master request in the debug environment
    return $this->master() && $app['debug'];
});
```

This returns a `Handler` object which can now be added to the front or back of the middleware stack:

```php
$app->before($responseTimeHandler);
$app->after($someOtherHandler);
```

You can modify the entire stack with `Application::handlers`. The second parameter determines whether to replace the whole stack (true by default).

```php
$app->handlers([
    $responseTimeHandler,
    new Durian\Middleware\RouterMiddleware($app)
], true);
```

Each handler can itself be a stack of handlers! You can insert more handlers into a particular handler like you would with the main application. You may think of them as events; each `Handler` that contains a stack (as opposed to a single function) iterates through all its registered functions (listeners) independently, eventually passing the output to the main application stack. The main stack itself is registered in the container as `$app['app.handler']`.

Normally, a stack will stop iterating once a response is found in the context. To change this behaviour, you can set the `terminate_on_response` option to false:

```php
$app['app.handler']->options(['terminate_on_response' => false]);
```

### Context

See the lavish use of `$this` in the examples above? That's made possible by the automatic binding of each closure or generator to the `Context` object. Each time the application handles a request or subrequest, a new context is pushed onto the stack. Handlers and middlewares receive a `ContextProxy` which saves them the trouble of juggling between different context objects.

The context is a simple container for the `Request` and `Response` objects. It also holds the route parameters and the return values from each handler.

```php
$app->route('/hello/{name}', function () {
    return $this->params('name');
})->get(function () {
    $name = $this->last();
    $request = $this->request();
    if (!$this->response()) {
        $this->response("Hello $name");
    }
});
```

`Context::last` holds the return (or yielded) value from the previous handler. This is one way to pass information downstream other than using the application container or request attributes.

### Middleware

Middlewares are simply handler functions defined as concrete classes by extending `Middleware`. The logic goes in `Middleware::run`. Middlewares have access to all context methods, so the syntax is essentially unchanged:

```php
class ResponseTimeMiddleware extends Durian\Middleware
{
    public function run()
    {
        $time = microtime(true);
        yield;
        $time = microtime(true) - $time;
        $this->response()->headers->set('X-Response-Time', $time);
    }
}

// Middlewares accept the application container in the constructor
$app->before(new ResponseTimeMiddleware($app));
```

### Handler Injection

If a handler function returns another `Handler` or generator function, it will be inserted into the current position of the execution stack.

In essence, the handler stack is recursively iterated over as a multidimensional array.

Routing
-------

Instead of the hierarchical routing syntax found in some other microframeworks, we use method chaining. The `yield` keyword allows us to pass execution to the next matching segment. Upon reaching the end of the chain, the execution flow is passed back to all generators in reverse order. Therefore, code before and after a `yield` statement will "wrap" subsequent route handlers:

```php
$app['awesome_library'] = $app->share(function ($app) {
    return new MyAwesomeLibrary();
});

$app->route('/hello', function () use ($app) {
    $app['awesome_library']->performExpensiveOperation();
    yield 'Hello';
    $app['awesome_library']->performCleanUp();
})->route('/{name}', function () {
    return $this->last().' '.$this->params('name');
})->get(function () {
    return ['method' => 'GET', 'message' => $this->last()];
})->post(function () {
    return ['method' => 'POST', 'message' => $this->last()];
});
```

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
    return 'Hello';
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

### ResponseMiddleware

`ResponseMiddleware` takes care of converting return values to Symfony2 `Response` objects. Arrays will result in a `JsonResponse`. You may also manually craft a response:

```php
$app->route('/tea', function () use ($app) {
    $this->response('I\'m a teapot', 418);
});
```

Or throw an exception:

```php
$app->route('/404', function () {
    // Alternatively pass in an exception object as the first parameter
    $this->error('Not Found', 404);
});
```

Returning a HTTP status code also works:

```php
$app->route('/fail', function () {
    return 500;
});
```

### Subrequests

Subrequests are performed by calling `Application::run`:

```php
$app->route('/song/{id:[0-9]+}', function () use ($app) {
    $id = $this->params('id');
    return [
        'id' => $id,
        'artist' => $app->run('GET', "/artists-by-song/$id")->getContent()
    ];
});
```

During a subrequest, `$this->master()` will return false.

Thrown exceptions from subrequests are not converted to `Response` objects; they should be handled in the master request.

Exception Handling
------------------

Whenever an exception is thrown, the application bubbles it up through all the generators in the stack.

This means that you can intercept any exception by wrapping a `yield` statement with a try/catch block:

```php
$exceptionHandlerMiddleware = $app->handler(function () {
    try {
        yield;
    } catch (\Exception $exception) {
        if (!$this->master()) {
            throw $exception;
        }
        $this->response($exception->getMessage(), 500);
    }
});
```

For pretty exception traces, you can make use of the [filp/whoops][5] library by including it in your composer.json:

```json
{
    "require": {
        "gigablah/durian": "~0.1",
        "filp/whoops": "~1.0"
    }
}
```

Then, register `WhoopsMiddleware` as the first handler in your application:

```php
$app->before(new Durian\Middleware\WhoopsMiddleware($app));
```

You may also register it for only a specific sub-stack, like the example below:

```php
$app->handlers([
    new Durian\Middleware\ResponseMiddleware($app),
    new Durian\Handler([
        new Durian\Middleware\WhoopsMiddleware($app),
        new Durian\Middleware\RouterMiddleware($app)
    ])
]);
```

Just to drive home the point, you may also register it only for a specific route:

```php
$app->route('/foo', new Durian\Middleware\WhoopsMiddleware($app), function () {
    throw new \Exception('bar');
});
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
$app->run($request); // handle a request or subrequest
$app->run($method, $path); // handle a HTTP method for a request path
$app->handler($callable, $condition); // wrap a callback as a Handler
$app->handlers(); // get the handler stack
$app->handlers(array $handlers, $replace); // replace or append to the stack
$app->before($callable, $condition); // prepend a handler to the stack
$app->after($callable, $condition); // append a handler to the stack
$app->route($path, ...$handlers); // start a new route segment
$app->handle($request, $type, $catch); // implement HttpKernelInterface::handle
```

### Handler

```php
$handler->run(); // iterate through the handler stack
$handler->handler($callable, $condition); // wrap a callback as a Handler
$handler->handlers(); // get the handler stack
$handler->handlers(array $handlers, $replace); // replace or append to the stack
$handler->context($context); // set the HTTP context
$handler->context(); // get the HTTP context
$handler->before($callable, $condition); // prepend a handler to the stack
$handler->after($callable, $condition); // append a handler to the stack
$handler->options(array $options); // set the handler options
$handler->options(); // get the handler options
```

### Route

```php
$route->route($path, ...$handlers); // append a new route segment
$route->method($methods, ...$handlers); // register method handler(s)
$route->get(...$handlers); // register method handler(s) for GET
$route->post(...$handlers); // register method handler(s) for POST
$route->put(...$handlers); // register method handler(s) for PUT
$route->delete(...$handlers); // register method handler(s) for DELETE
$route->patch(...$handlers); // register method handler(s) for PATCH
$route->options(...$handlers); // register method handler(s) for OPTIONS
$route->head(...$handlers); // register method handler(s) for HEAD
$route->dump(); // recursively dump all routes to an array
```

### Context

```php
$context->request(); // get the Request
$context->request($request, $type); // set the Request
$context->response(); // get the Response
$context->response($response); // set the Response
$context->response($content, $status, array $headers); // create a new Response
$context->error($exception); // throw an exception
$context->error($message, $status, array $headers, $code); // throw an exception
$context->master(); // check whether the current request is the master request
$context->params($key); // get a route parameter
$context->params($key, $default); // get a route parameter with fallback
$context->params(array $params); // insert route parameters
$context->params(); // get all route parameters
$context->append($output); // append a handler return value
$context->last(); // get the last handler return value
$context->clear(); // clear the current context
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
* [Silex][11]

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
[11]: https://github.com/silexphp/Silex
