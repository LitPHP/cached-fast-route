<?php namespace Lit\CachedFastRoute;

use FastRoute\Dispatcher;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Lit\Core\AbstractRouter;
use Lit\Core\Interfaces\IStubResolver;
use Psr\Http\Message\ServerRequestInterface;

class FastRouteRouter extends AbstractRouter
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;
    /**
     * @var mixed
     */
    protected $methodNotAllowed;

    public function __construct(
        Dispatcher $dispatcher,
        IStubResolver $stubResolver,
        $notFound,
        $methodNotAllowed = null
    ) {
        parent::__construct($stubResolver, $notFound);
        $this->dispatcher = $dispatcher;
        $this->methodNotAllowed = $methodNotAllowed;
    }


    protected function findStub(ServerRequestInterface $request)
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        $routeInfo = $this->dispatcher->dispatch($method, $path);

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
        return function (
            ServerRequestInterface $request,
            DelegateInterface $delegate
        ) use ($stub, $vars) {
            foreach ($vars as $key => $val) {
                $request = $request->withAttribute($key, $val);
            }
            $middleware = $this->resolve($stub);

            return $middleware->process($request, $delegate);
        };
    }
}
