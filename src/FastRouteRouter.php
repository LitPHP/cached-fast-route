<?php namespace Lit\CachedFastRoute;

use FastRoute\Dispatcher;
use Lit\Core\AbstractRouter;
use Lit\Core\Interfaces\IStubResolver;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;

class FastRouteRouter extends AbstractRouter
{
    /**
     * @var CachedDispatcher
     */
    protected $cachedDispatcher;
    /**
     * @var mixed
     */
    protected $methodNotAllowed;

    public function __construct(
        CachedDispatcher $cachedDispatcher,
        IStubResolver $stubResolver,
        $notFound,
        $methodNotAllowed = null
    ) {
        parent::__construct($stubResolver, $notFound);
        $this->cachedDispatcher = $cachedDispatcher;
        $this->methodNotAllowed = $methodNotAllowed;
    }


    protected function findStub(ServerRequestInterface $request)
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        $routeInfo = $this->cachedDispatcher->dispatch($method, $path);

        return $this->stub($routeInfo);
    }

    protected function stub($routeInfo)
    {
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return $this->notFound;
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                if (!empty($this->methodNotAllowed)) {
                    return [$this->methodNotAllowed, [$routeInfo[1]]];
                } else {
                    return $this->notFound;
                }
                break;
            case Dispatcher::FOUND:
                list(, $stub, $vars) = $routeInfo;

                if (empty($vars)) {
                    return $stub;
                } else {
                    return $this->proxy($stub, $vars);
                }
                break;

            default:
                throw new \Exception(__METHOD__ . '/' . __LINE__);
        }
    }

    protected function proxy($stub, $vars)
    {
        return function (ServerRequestInterface $request, Response $response, $next) use ($stub, $vars) {
            foreach ($vars as $key => $val) {
                $request = $request->withAttribute($key, $val);
            }
            $middleware = $this->resolve($stub);

            return call_user_func($middleware, $request, $response, $next);
        };
    }
}
