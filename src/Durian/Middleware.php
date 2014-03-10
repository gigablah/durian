<?php

namespace Durian;

/**
 * Base class for middlewares.
 *
 * @author Chris Heng <bigblah@gmail.com>
 */
abstract class Middleware extends Handler
{
    use ContextTrait {
        request as protected;
        response as protected;
        error as protected;
        master as protected;
        params as protected;
        last as protected;
        clear as protected;
    }

    protected $app;

    /**
     * Constructor.
     *
     * @param Application $app The application container
     */
    public function __construct(Application $app = null)
    {
        $this->app = $app;

        parent::__construct();
    }
}
