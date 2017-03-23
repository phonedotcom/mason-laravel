<?php namespace Phonedotcom\Mason\Routing;

use Route as LaravelRoute;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Phonedotcom\Mason\Schema\JsonSchema;
use Illuminate\Http\Request;
use Phonedotcom\Mason\Builder\Components\Control;
use Phonedotcom\Mason\Builder\Document;
use Phonedotcom\Mason\Http\MasonResponse;
use Phonedotcom\Mason\Http\SchemaResponse;

/**
 * Sets up routes for a Mason service endpoint.  Assumes you will have one controller per URI per Verb.
 */
trait MasonServiceTrait
{
    public static function registerRoute($router)
    {
        if (empty(static::$routeName) || empty(static::$routePath) || empty(static::$verb)) {
            throw new \Exception(sprintf(
                'Controller has no routeName, routePath, and/or verb defined: %s',
                get_called_class()
            ));
        }

        $path = static::$routePath;
        $class = get_called_class();

        $verb = strtolower(static::$verb);
        $router->$verb($path, ['as' => static::$routeName, 'uses' => "$class@action"]);

        if (!empty(static::$altRoutePath)) {
            foreach (static::$altRoutePath as $altPath) {
                $router->$verb($altPath, ['uses' => "$class@action"]);
            }
        }

        $functions = get_class_methods(static::class);

        if (in_array('inputSchema', $functions)) {
            $router->get(self::getInputSchemaPath(), [
                'as' => self::getInputSchemaRouteName(), 'uses' => "$class@inputSchema"
            ]);
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

    private static function getInputSchemaPath()
    {
        return '/inputs/' . static::$routeName;
    }

    public static function getMasonControl($params = [])
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

    private static function getInputSchemaRouteName()
    {
        return 'inputs.' . static::$routeName . '.' . strtolower(static::$verb);
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

    public static function getRelation()
    {
        return static::getDefaultMasonNamespace() . ':' . static::$routeName;
    }

    public static function getProfileUrl()
    {
        return config('mason.namespaces.' . static::getDefaultMasonNamespace()) . static::$routeName;
    }

    protected function makeMasonItemCreatedResponse(Document $document, Request $request, $url, array $headers = [])
    {
        $headers['Location'] = $url;

        return $this->makeMasonResponse($document, $request, [], 201, $headers);
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

        $url = static::getProfileUrl();
        $document->setMetaControl('profile', new Control($url));

        if (isset($headers['Link']) && !is_array($headers['Link'])) {
            $headers['Link'] = [$headers['Link']];
        }
        $headers['Link'][] = sprintf('<%s>; rel="profile"', $url);

        if (!isset($document->{'@controls'}->self)) {
            $document->setControl('self', static::class, $routeParams);
        }

        if (method_exists($this, 'addMasonNamespaces')) {
            $this->addMasonNamespaces($document);
        }

        return MasonResponse::create($document, $request, $status, $headers, JSON_UNESCAPED_SLASHES);
    }

    protected function makeSchemaResponse(JsonSchema $schema, Request $request, $status = 200, array $headers = [])
    {
        $schema->id = $request->fullUrl() . '#';

        return SchemaResponse::create($schema, $request, $status, $headers);
    }
}
