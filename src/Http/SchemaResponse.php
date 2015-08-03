<?php
namespace PhoneCom\Mason\Http;

use App\Libraries\JsonSchema\RootSchema;
use App\Libraries\JsonSchema\SubSchema;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SchemaResponse extends JsonResponse
{
    const MIME_TYPE = 'application/schema+json';

    public static function create($document = null, $request = null, $status = 200, $headers = [], $options = 0)
    {
        return new static($document, $request, $status, $headers, $options);
    }

    public function __construct($document = null, $request = null, $status = 200, $headers = [], $options = 0)
    {
        if (!$request instanceof Request) {
            throw new \InvalidArgumentException(sprintf('Request is not an instance of %s', Request::class));
        }

        if ($document === null) {
            $document = new RootSchema();

        } elseif (!$document instanceof RootSchema) {
            throw new \InvalidArgumentException(sprintf('Document is not an instance of %s', RootSchema::class));
        }

        $document->setId('/' . ltrim($request->path(), '/'));
        $options += JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

        SubSchema::sortSchemaProperties($document);
        $headers['Content-Type'] = self::MIME_TYPE;

        parent::__construct($document, $status, $headers, $options);

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
