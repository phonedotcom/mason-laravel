<?php
namespace PhoneCom\Mason\Http;

use PhoneCom\Mason\Builder\Contrib\MasonCollection;
use Illuminate\Http\Request;
use PhoneCom\Mason\Builder\Document;
use Illuminate\Http\JsonResponse;

class MasonResponse extends JsonResponse
{
    const MIME_TYPE = 'application/vnd.mason+json';

    private static $defaultHeaders = [];

    private static $defaultSorting = [
        '@meta', '@error', '{data}', '@controls', '@namespaces'
    ];

    private static $controlsSorting = [
        '{data}', 'self', 'profile'
    ];

    private static $metaSorting = [
        '@title', '@description', 'profile', 'voip_id', 'application_id', '{data}'
    ];

    public static function setDefaultHeaders(array $headers)
    {
        self::$defaultHeaders = $headers;
    }

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
            $document = new Document();

        } elseif (!$document instanceof Document) {
            throw new \InvalidArgumentException(sprintf('Document is not an instance of %s', Document::class));
        }

        if ($document instanceof MasonCollection) {
            $document->assemble();
        }

        $headers = array_merge(['Content-Type' => self::MIME_TYPE], self::$defaultHeaders, $headers);

        $this->applyPreferHeader($document, $request, $headers, $options);
        $document->sort(self::$defaultSorting, self::$controlsSorting, self::$metaSorting);

        parent::__construct($document, $status, $headers, $options);

        $this->applyEtag($request);
    }

    private function applyPreferHeader(Document $document, Request $request, &$headers, &$options)
    {
        $prefer = $request->headers->get('Prefer');
        $minimal = preg_match("/\brepresentation\s*=\s*(minimal\b|\"minimal\")/i", $prefer);
        if ($minimal) {
            $document->minimize();
            if ($options >= JSON_PRETTY_PRINT) {
                $options -= JSON_PRETTY_PRINT;
            }
            $headers['Preference-Applied'] = 'representation=minimal';

        } elseif ($options < JSON_PRETTY_PRINT) {
            $options += JSON_PRETTY_PRINT;
        }
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
