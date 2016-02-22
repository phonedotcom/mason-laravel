<?php namespace PhoneCom\Mason\Routing;

use Route as LaravelRoute;
use Illuminate\Foundation\Application;
use PhoneCom\Mason\Schema\JsonSchema;
use Illuminate\Http\Request;
use PhoneCom\Mason\Builder\Components\Control;
use PhoneCom\Mason\Builder\Document;
use PhoneCom\Mason\Http\MasonResponse;
use PhoneCom\Mason\Http\SchemaResponse;

/**
 * Sets up routes for a Mason service endpoint.  Assumes you will have one controller per URI.
 */
trait MasonPathServiceTrait
{
    public static function registerRoutes($router)
    {
        if (empty(static::$routeName) || empty(static::$routePath)) {
            throw new \Exception(sprintf('%s has no routeName and/or routePath', get_called_class()));
        }

        $path = static::$routePath;
        $class = get_called_class();

        $verbs = ['get', 'post', 'put', 'patch', 'delete'];
        $methods = get_class_methods(static::class);
        $implementedVerbs = array_intersect($verbs, $methods);

        foreach ($implementedVerbs as $verb) {

            $router->$verb($path, ['as' => static::$routeName, 'uses' => "$class@$verb"]);

            if (in_array($verb . 'InputSchema', $methods)) {
                $router->get(static::getInputSchemaPath($verb), [
                    'as' => static::getInputSchemaRouteName($verb), 'uses' => "$class@{$verb}InputSchema"
                ]);
            }

            if (in_array($verb . 'OutputSchema', $methods)) {
                $router->get(static::getOutputSchemaPath($verb), [
                    'as' => static::getOutputSchemaRouteName($verb), 'uses' => "$class@{$verb}OutputSchema"
                ]);
            }

        }

        $router->options($path, ['uses' => "$class@options"]);
    }

    private static function getInputSchemaPath($verb)
    {
        return self::getBaseSchemaPath($verb) . '/input';
    }

    private static function getOutputSchemaPath($verb)
    {
        return self::getBaseSchemaPath($verb) . '/output';
    }

    private static function getBaseSchemaPath($verb)
    {
        return '/schemas/' . str_replace('.', '/', static::$routeName) . '/' . $verb;
    }

    public static function getMasonControl($verb, array $params = [])
    {
        $fieldNames = [
            'title', 'description', 'isHrefTemplate', 'schemaUrl', 'schema', 'template', 'accept',
            'output', 'encoding', 'jsonFile', 'files', 'alt'
        ];

        $properties = [];

        foreach ($fieldNames as $property) {
            if (!empty(static::$$property)) {
                $properties[$property] = static::$$property;
            }
        }

        $isGet = ($verb == 'get');
        if (!$isGet) {
            $properties['method'] = strtoupper($verb);
        }

        if (in_array($verb . 'InputSchema', get_class_methods(static::class))) {
            if (!isset($properties['schemaUrl'])) {
                $properties['schemaUrl'] = route(static::getInputSchemaRouteName($verb));
            }

            if (!$isGet && !isset($properties['encoding'])) {
                $properties['encoding'] = (empty(static::$encoding) ? 'json' : static::$encoding);
            }
        }

        if (!empty(static::$isHrefTemplate) && !empty(static::$routePathTemplateParams)) {
            $patterns = [];
            foreach (static::$routePathTemplateParams as $templateName => $templateValue) {
                $key = '___' . strtoupper($templateName) . '___';
                $patterns[$key] = $templateValue;
                $params[$templateName] = $key;
            }

            $url = strtr(static::getUrl($params), $patterns);

        } else {
            $url = static::getUrl($params);
        }

        return new Control($url, $properties);
    }

    private static function getOutputSchemaRouteName($verb)
    {
        return 'schemas.' . static::$routeName . ".$verb.output";
    }

    private static function getInputSchemaRouteName($verb)
    {
        return 'schemas.' . static::$routeName . ".$verb.input";
    }

    public static function getUrl(array $params = [])
    {
        return route(static::$routeName, $params);
    }

    public function options(Request $request)
    {
        $supportedVerbs = [];

        if (app() instanceof Application) {
            $currentPathInfo = LaravelRoute::current()->getPath();

            $router = app('Illuminate\\Routing\\Router');

            foreach ($router->getRoutes() as $route) {
                if ($route->getPath() == $currentPathInfo) {
                    $supportedVerbs = array_merge($supportedVerbs, $route->getMethods());
                }
            }
            if (in_array('GET', $supportedVerbs) && !in_array('HEAD', $supportedVerbs)) {
                $supportedVerbs[] = 'HEAD';
            }
            $index = array_search('OPTIONS', $supportedVerbs);
            if ($index !== false) {
                unset($supportedVerbs[$index]);
                $supportedVerbs = array_values($supportedVerbs);
            }

        } else {

            $currentPathInfo = $request->getPathInfo();
            foreach (array_keys(app()->getRoutes()) as $key) {
                $verb = strstr($key, '/', true);
                $pathInfo = strstr($key, '/');

                if ($pathInfo == $currentPathInfo && $verb != 'OPTIONS') {
                    $supportedVerbs[] = $verb;
                    if ($verb == 'GET') {
                        $supportedVerbs[] = 'HEAD';
                    }
                }
            }
        }

        $doc = (new Document())
            ->setProperty('methods', $supportedVerbs);

        return MasonResponse::create($doc, $request, 200, ['Allow' => join(',', $supportedVerbs)]);
    }

    public static function getRelation($verb)
    {
        return static::$curieNamespace . ':' . static::$routeName . '-' . $verb;
    }

    protected function makeMasonItemCreatedResponse(Document $document, Request $request, $url, array $headers = [])
    {
        $headers['Location'] = $url;

        return $this->makeMasonResponse($document, $request, [], 201, $headers);
    }

    public static function getOutputSchemaUrl($verb)
    {
        return route(static::getOutputSchemaRouteName($verb));
    }

    public static function getInputSchemaUrl($verb)
    {
        return route(static::getInputSchemaRouteName($verb));
    }

    protected function makeMasonResponse(
        Document $document,
        Request $request,
        array $routeParams = [],
        $status = 200,
        array $headers = []
    ) {

        $verb = strtolower($request->method());

        if (method_exists($this, $verb . 'OutputSchema')) {
            $url = static::getOutputSchemaUrl($verb);
            $document->setMetaProperty('relation', static::getRelation($verb))
                ->setControl('profile', new Control($url, ['output' => [SchemaResponse::MIME_TYPE]]));

            if (isset($headers['Link']) && !is_array($headers['Link'])) {
                $headers['Link'] = [$headers['Link']];
            }
            $headers['Link'][] = sprintf('<%s>; rel="profile"', $url);
        }

        if (!isset($document->{'@controls'}->self)) {
            $document->setControl('self', static::getMasonControl($verb, $routeParams));
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
