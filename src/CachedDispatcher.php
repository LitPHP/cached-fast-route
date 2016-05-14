<?php namespace Lit\CachedFastRoute;

use FastRoute\DataGenerator;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser;
use Lit\Nexus\Interfaces\ISingleValue;

class CachedDispatcher implements Dispatcher
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;
    /**
     * @var ISingleValue
     */
    protected $cache;
    /**
     * @var RouteParser
     */
    protected $routeParser;
    /**
     * @var DataGenerator
     */
    protected $dataGenerator;
    /**
     * @var
     */
    protected $dispatcherClass;
    /**
     * @var callable
     */
    protected $routeDefination;

    /**
     * RouteDispatcher constructor.
     * @param ISingleValue $cache
     * @param RouteParser $routeParser
     * @param DataGenerator $dataGenerator
     * @param callable $routeDefination
     * @param string $dispatcherClass
     * @internal param RouteCollector $routeCollector
     */
    public function __construct(
        ISingleValue $cache,
        RouteParser $routeParser,
        DataGenerator $dataGenerator,
        callable $routeDefination,
        $dispatcherClass
    ) {
        $this->cache = $cache;
        $this->routeParser = $routeParser;
        $this->dataGenerator = $dataGenerator;
        $this->dispatcherClass = $dispatcherClass;
        $this->routeDefination = $routeDefination;
    }

    public function dispatch($httpMethod, $uri)
    {
        return $this->getDispatcher()->dispatch($httpMethod, $uri);
    }

    protected function getDispatcher()
    {
        if (!isset($this->dispatcher)) {
            if ($this->cache->exists()) {
                $data = $this->cache->get();
            } else {
                $collector = new RouteCollector($this->routeParser, $this->dataGenerator);
                call_user_func($this->routeDefination, $collector);
                $data = $collector->getData();
                $this->cache->set($data);
            }
            $this->dispatcher = new $this->dispatcherClass($data);
        }
        return $this->dispatcher;
    }
}
