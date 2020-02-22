<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

ini_set('display_errors', 'stderr');
require 'vendor/autoload.php';

$psr17Factory = new Nyholm\Psr7\Factory\Psr17Factory();
$container = new \DI\Container();
$app = \Slim\Factory\AppFactory::create($psr17Factory, $container);

// define routes
$app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
    $response->getBody()->write('Hello world from RoadRunner and Slim!');
    return $response;
});

// set routing error handling
$app->addMiddleware(new class($psr17Factory) implements MiddlewareInterface {

    private $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface {
        try {
            return $next->handle($request);
        } catch (\Slim\Exception\HttpNotFoundException $e) {
            $response = $this->responseFactory->createResponse(404);
            $response->getBody()->write('route not found');
            return $response;
        } catch (\Slim\Exception\HttpMethodNotAllowedException $e) {
            $response = $this->responseFactory->createResponse(405);
            $response->getBody()->write('method not allowed');
            return $response;
        }
    }
});

$relay = new Spiral\Goridge\StreamRelay(STDIN, STDOUT);
$worker = new Spiral\RoadRunner\Worker($relay);
$psr7 = new Spiral\RoadRunner\PSR7Client($worker, $psr17Factory, $psr17Factory, $psr17Factory);

while ($request = $psr7->acceptRequest()) {
    try {
        $response = $app->handle($request);
        $psr7->respond($response);
    } catch (\Throwable $e) {
        $psr7->getWorker()->error((string)$e);
    }
}
