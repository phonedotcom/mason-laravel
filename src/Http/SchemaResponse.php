<?php
namespace PhoneCom\Mason\Http;

use PhoneCom\Mason\Schema\JsonSchema;
use PhoneCom\Mason\Schema\SubSchema;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SchemaResponse extends JsonResponse
{
    const MIME_TYPE = 'application/schema+json';

    private static $defaultHeaders = [];

    public static function setDefaultHeaders(array $headers)
    {
        self::$defaultHeaders = $headers;
    }

    public static function create($schema = null, $request = null, $status = 200, $headers = [], $options = 0)
    {
        return new static($schema, $request, $status, $headers, $options);
    }

    public function __construct($schema = null, $request = null, $status = 200, $headers = [], $options = 0)
    {
        if (!$request instanceof Request) {
            throw new \InvalidArgumentException(sprintf('Request is not an instance of %s', Request::class));
        }

        if ($schema === null) {
            $schema = new JsonSchema();

        } elseif (!$schema instanceof JsonSchema) {
            throw new \InvalidArgumentException(sprintf('Document is not an instance of %s', JsonSchema::class));
        }

        $options |= JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

        $schema->setSchema('http://json-schema.org/draft-04/schema#');

        $schema->sort([
            'id', '$schema', 'title', 'description', 'type', 'properties', 'patternProperties',
            'required', 'additionalProperties', '{data}', '@meta', '@controls', '@error', '@namespaces'
        ]);

        $headers['Content-Type'] = self::MIME_TYPE;
        $headers = array_merge($headers, self::$defaultHeaders);

        parent::__construct($schema, $status, $headers, $options);

        $this->applyEtag($request);
    }

    private function applyEtag(Request $request)
    {
        if ($this->getStatusCode() == 200) {
            $eTag = md5($this->getContent());
            $this->headers->set('ETag', '"' . $eTag . '"', true);

            $ifNoneMatch = $request->headers->get('If-None-Match');
            if (trim($ifNoneMatch, '"') == $eTag) {
                $this->setStatusCode(304);
                $this->setContent('');
            }
        }
    }
}
