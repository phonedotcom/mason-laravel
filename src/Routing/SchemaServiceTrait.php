<?php namespace PhoneCom\Mason\Routing;

use PhoneCom\Mason\Schema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhoneCom\Mason\Http\SchemaResponse;

trait SchemaServiceTrait
{
    public static function registerRoute($router)
    {
        if (empty(static::$routeName) || empty(static::$routePath)) {
            throw new \Exception(sprintf('Service has no routeName, routePath, and/or verb defined: %s', get_called_class()));
        }

        $path = static::$routePath;
        $class = get_called_class();

        $router->get($path, ['as' => static::$routeName, 'uses' => "$class@schema"]);

        if (!isset($router->getRoutes()['OPTIONS'.$path])) {
            $router->options($path, ['uses' => "$class@options"]);
        }
    }

    public function options(Request $request)
    {
        return Response::create('', 200, ['Allow' => 'GET,HEAD']);
    }

    protected function makeSchemaResponse(JsonSchema $schema, Request $request, $status = 200, array $headers = [])
    {
        $schema->id = $request->url();

        return SchemaResponse::create($schema, $request, $status, $headers);
    }

    public static function getUrl($name)
    {
        return route(static::$routeName) . '#/definitions/' . $name;
    }
}
