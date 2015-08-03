<?php namespace PhoneCom\Mason\Http;

use App\Libraries\JsonSchema\RootSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhoneCom\Mason\Builder\Components\Control;
use PhoneCom\Mason\Builder\Document;

trait MasonService
{
    protected static $verb;
    protected static $routeName;
    protected static $routePath;
    protected static $title;
    protected static $encoding;

    public static function registerRoute(Application $app)
    {
        if (!static::$routeName || !static::$routePath || !static::$verb) {
            throw new \Exception(sprintf(
                'Service has no routeName, routePath, and/or verb defined: %s',
                get_called_class()
            ));
        }

        $name = static::$routeName;
        $path = static::$routePath;
        $class = get_called_class();

        $method = strtolower(static::$verb);
        $app->$method($path, ['as' => $name, 'uses' => "$class@action"]);

        $reflection = new \ReflectionClass($class);
        if ($reflection->hasMethod('inputSchema')) {
            $app->get("/schemas/inputs/" . self::getSchemaSlug(), [
                'as' => self::getInputSchemaRouteName(), 'uses' => "$class@inputSchema"
            ]);
        }
        if ($reflection->hasMethod('outputSchema')) {
            $app->get("/schemas/outputs/" . self::getSchemaSlug(), [
                'as' => self::getOutputSchemaRouteName(), 'uses' => "$class@outputSchema"
            ]);
        }

        if (!isset($app->getRoutes()['OPTIONS'.$path])) {
            $app->options($path, ['uses' => "$class@options"]);
        }
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

        if (!empty(static::$title)) {
            $properties['title'] = static::$title;
        }

        $isGet = (strtoupper(static::$verb) == 'GET');
        if (!$isGet) {
            $properties['method'] = strtoupper(static::$verb);
        }

        $reflection = new \ReflectionClass(get_called_class());
        if ($reflection->hasMethod('inputs')) {
            $properties['schemaUrl'] = route(static::getInputSchemaRouteName());

            if (!$isGet) {
                $properties['encoding'] = (static::$encoding ?: 'json');
            }
        }

        return new Control(static::getUrl($params), $properties);
    }

    private static function getOutputSchemaRouteName()
    {
        return 'schemas.output.' . static::$verb . '.' . static::$routeName;
    }

    private static function getInputSchemaRouteName()
    {
        return 'schemas.input.' . static::$verb . '.' . static::$routeName;
    }

    public static function getUrl(array $params = [])
    {
        return route(static::$routeName, $params);
    }

    public function options(Request $request)
    {
        $app = Application::getInstance();

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
        return SchemaResponse::DEFAULT_NAMESPACE . ':' . self::getSchemaSlug();
    }

    protected function getVoipId(Request $request)
    {
        return $request->headers->get('X_VOIP_ID');
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
        if (method_exists($this, 'outputSchema')) {
            $document->setMetaProperty('profile', static::getOutputSchemaUrl())
                ->setControl('profile', new Control(static::getOutputSchemaUrl(), [
                    'output' => [SchemaResponse::MIME_TYPE]
                ]));

            if (isset($headers['Link']) && !is_array($headers['Link'])) {
                $headers['Link'] = [$headers['Link']];
            }
            $headers['Link'][] = sprintf('<%s>; rel="profile"', static::getOutputSchemaUrl());
        }

        if (!isset($document->{'@controls'}->self)) {
            $document->setControl('self', static::getMasonControl($routeParams));
        }

        $document->addNamespace(SchemaResponse::DEFAULT_NAMESPACE, '/schemas/outputs/');

        return MasonResponse::create($document, $request, $status, $headers, JSON_UNESCAPED_SLASHES);
    }

    protected function makeSchemaResponse(RootSchema $schema, Request $request, $status = 200, array $headers = [])
    {
        $schema->id = $request->url();

        return SchemaResponse::create($schema, $request, $status, $headers);
    }




    /*
    public function validate(Request $request, array $rules, array $messages = array(), array $customAttributes = [])
    {
        $validator = $this->getValidationFactory()->make($request->all(), $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
    */
}
