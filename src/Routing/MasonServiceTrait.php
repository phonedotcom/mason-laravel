<?php namespace PhoneCom\Mason\Routing;

use Illuminate\Routing\Router;
use PhoneCom\Mason\Schema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhoneCom\Mason\Builder\Components\Control;
use PhoneCom\Mason\Builder\Document;
use PhoneCom\Mason\Http\MasonResponse;
use PhoneCom\Mason\Http\SchemaResponse;

trait MasonServiceTrait
{
    public static function registerRoute($router)
    {
        if (empty(static::$routeName) || empty(static::$routePath) || empty(static::$verb)) {
            throw new \Exception(sprintf(
                'Service has no routeName, routePath, and/or verb defined: %s',
                get_called_class()
            ));
        }

        $path = static::$routePath;
        $class = get_called_class();

        $method = strtolower(static::$verb);
        $router->$method($path, ['as' => static::$routeName, 'uses' => "$class@action"]);

        $methods = get_class_methods(static::class);

        if (in_array('schema', $methods)) {
            $router->get("/schemas/" . self::getSchemaSlug(), [
                'as' => self::getSchemaRouteName(), 'uses' => "$class@schema"
            ]);

        } else {
            if (in_array('inputSchema', $methods)) {
                $router->get("/schemas/" . self::getSchemaSlug() . '-input', [
                    'as' => self::getInputSchemaRouteName(), 'uses' => "$class@inputSchema"
                ]);
            }
            if (in_array('outputSchema', $methods)) {
                $router->get("/schemas/" . self::getSchemaSlug(), [
                    'as' => self::getOutputSchemaRouteName(), 'uses' => "$class@outputSchema"
                ]);
            }
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

    private static function getSchemaSlug()
    {
        $slug = '';
        if (strtoupper(static::$verb) != 'GET') {
            $slug .= strtolower(static::$verb) . '-';
        }
        $slug .= str_replace('.', '-', static::$routeName);

        return $slug;
    }

    public static function getMasonControl(array $params = [])
    {
        $properties = [];

        foreach ([
            'title', 'description', 'isHrefTemplate', 'schemaUrl', 'schema', 'template', 'accept',
            'output', 'encoding', 'jsonFile', 'files', 'alt'
        ] as $property) {
            if (!empty(static::$property)) {
                $properties[$property] = static::$property;
            }
        }

        $isGet = (strtoupper(static::$verb) == 'GET');
        if (!$isGet) {
            $properties['method'] = strtoupper(static::$verb);
        }

        if (in_array('inputSchema', get_class_methods(static::class))) {
            if (!isset($properties['schemaUrl'])) {
                $properties['schemaUrl'] = route(static::getInputSchemaRouteName());
            }

            if (!$isGet && !isset($properties['encoding'])) {
                $properties['encoding'] = (empty(static::$encoding) ? 'json' : static::$encoding);
            }
        }

        return new Control(static::getUrl($params), $properties);
    }

    private static function getOutputSchemaRouteName()
    {
        return 'schemas.' . strtolower(static::$verb) . '.' . static::$routeName . '.output';
    }

    private static function getInputSchemaRouteName()
    {
        return 'schemas.' . strtolower(static::$verb) . '.' . static::$routeName . '.input';
    }

    private static function getSchemaRouteName()
    {
        return 'schemas.' . strtolower(static::$verb) . '.' . static::$routeName;
    }

    public static function getUrl(array $params = [])
    {
        return route(static::$routeName, $params);
    }

    public function options(Request $request)
    {
        $app = app();

        $supportedVerbs = [];
        $currentPathInfo = $request->getPathInfo();
        foreach (array_keys($app->getRoutes()) as $key) {
            $verb = strstr($key, '/', true);
            $pathInfo = strstr($key, '/');

            if ($pathInfo == $currentPathInfo && $verb != 'OPTIONS') {
                $supportedVerbs[] = $verb;
                if ($verb == 'GET') {
                    $supportedVerbs[] = 'HEAD';
                }
            }
        }

        return Response::create('', 200, ['Allow' => join(',', $supportedVerbs)]);
    }

    public static function getRelation()
    {
        return static::$curieNamespace . ':' . static::getSchemaSlug();
    }

    protected function makeMasonItemCreatedResponse(Document $document, Request $request, $url, array $headers = [])
    {
        $headers['Location'] = $url;

        return $this->makeMasonResponse($document, $request, [], 201, $headers);
    }

    public static function getOutputSchemaUrl()
    {
        return route(static::getOutputSchemaRouteName());
    }

    public static function getSchemaUrl()
    {
        return route(static::getSchemaRouteName());
    }

    public static function getInputSchemaUrl()
    {
        return route(static::getInputSchemaRouteName());
    }

    protected function makeMasonResponse(
        Document $document,
        Request $request,
        array $routeParams = [],
        $status = 200,
        array $headers = []
    ) {

        if (method_exists($this, 'outputSchema') || method_exists($this, 'schema')) {
            $url = (method_exists($this, 'schema') ? static::getSchemaUrl() : static::getOutputSchemaUrl());
            $document->setMetaProperty('relation', static::getRelation())
                ->setControl('profile', new Control($url, ['output' => [SchemaResponse::MIME_TYPE]]));

            if (isset($headers['Link']) && !is_array($headers['Link'])) {
                $headers['Link'] = [$headers['Link']];
            }
            $headers['Link'][] = sprintf('<%s>; rel="profile"', $url);
        }

        if (!isset($document->{'@controls'}->self)) {
            $document->setControl('self', static::getMasonControl($routeParams));
        }

        if (method_exists($this, 'addDefaultMasonNamespace')) {
            $this->addDefaultMasonNamespace($document);
        }

        return MasonResponse::create($document, $request, $status, $headers, JSON_UNESCAPED_SLASHES);
    }

    protected function makeSchemaResponse(JsonSchema $schema, Request $request, $status = 200, array $headers = [])
    {
        $schema->id = $request->fullUrl() . '#';

        return SchemaResponse::create($schema, $request, $status, $headers);
    }
}
