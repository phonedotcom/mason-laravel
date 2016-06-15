<?php namespace Phonedotcom\Mason\Routing;

use Illuminate\Routing\Router;
use Phonedotcom\Mason\Schema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Phonedotcom\Mason\Http\SchemaResponse;

trait SchemaServiceTrait
{
    public static function registerRoute($router)
    {
        if (empty(static::$routeName) || empty(static::$routePath)) {
            throw new \Exception(sprintf('Service has no routeName, routePath, and/or verb defined: %s', get_called_class()));
        }

        $path = static::$routePath;
        $class = get_called_class();

        $route = $router->get($path, ['as' => static::$routeName, 'uses' => "$class@schema"]);
        if (!empty(static::$routePatterns)) {
            $route->where(static::$routePatterns);
        }

        if (!self::pathIsRegistered($router, $path)) {
            $router->options($path, ['uses' => "$class@options"]);
        }
    }

    private static function pathIsRegistered($router, $path)
    {
        if ($router instanceof Router) {
            foreach ($router->getRoutes() as $route) {
                if (in_array('OPTIONS', $route->getMethods()) && $route->getPath() == $path) {
                    return true;
                }
            }
            return false;
        }

        return isset($router->getRoutes()['OPTIONS'.$path]);
    }

    public function options(Request $request)
    {
        return Response::create('', 200, ['Allow' => 'GET,HEAD']);
    }

    protected function makeSchemaResponse(JsonSchema $schema, Request $request, $status = 200, array $headers = [])
    {
        $schema->id = $request->fullUrl() . '#';

        return SchemaResponse::create($schema, $request, $status, $headers);
    }

    public static function getUrl($name)
    {
        return route(static::$routeName) . "#/definitions/$name";
    }
}
