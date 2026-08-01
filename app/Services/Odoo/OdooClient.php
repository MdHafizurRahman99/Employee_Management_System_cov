<?php

namespace App\Services\Odoo;

use DOMDocument;
use DOMElement;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException as HttpRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;
use stdClass;
use Throwable;

class OdooClient
{
    public function __construct(
        private readonly ?string $url = null,
        private readonly ?string $database = null,
        private readonly ?int $timeout = null,
        private readonly bool|string|null $sslVerify = null,
        private readonly ?string $caBundle = null
    ) {
    }

    public function isConfigured(): bool
    {
        return filled($this->getUrl()) && filled($this->getDatabase());
    }

    public function authenticate(string $login, string $password): int|false
    {
        $this->ensureConfigured();

        $result = $this->call('/xmlrpc/2/common', 'authenticate', [
            $this->getDatabase(),
            $login,
            $password,
            new stdClass(),
        ]);

        if (is_numeric($result) && (int) $result > 0) {
            return (int) $result;
        }

        return false;
    }

    public function executeKw(
        int $uid,
        string $password,
        string $model,
        string $method,
        array $args = [],
        array $kwargs = []
    ): mixed {
        $this->ensureConfigured();

        return $this->call('/xmlrpc/2/object', 'execute_kw', [
            $this->getDatabase(),
            $uid,
            $password,
            $model,
            $method,
            $args,
            $kwargs === [] ? new stdClass() : $kwargs,
        ]);
    }

    private function call(string $endpoint, string $method, array $params): mixed
    {
        $payload = $this->buildMethodCall($method, $params);

        try {
            $response = Http::baseUrl($this->getUrl())
                ->timeout($this->getTimeout())
                ->withHeaders([
                    'Content-Type' => 'text/xml',
                    'Accept' => 'text/xml',
                ])
                ->withOptions([
                    'verify' => $this->getSslVerificationOption(),
                ])
                ->send('POST', $endpoint, ['body' => $payload])
                ->throw();
        } catch (ConnectionException|HttpRequestException|GuzzleException $exception) {
            throw new OdooException('Unable to reach Odoo right now. Please try again shortly.', 0, $exception);
        }

        return $this->parseResponse($response->body());
    }

    private function buildMethodCall(string $method, array $params): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;

        $methodCall = $document->createElement('methodCall');
        $document->appendChild($methodCall);

        $methodName = $document->createElement('methodName', $method);
        $methodCall->appendChild($methodName);

        $paramsElement = $document->createElement('params');
        $methodCall->appendChild($paramsElement);

        foreach ($params as $parameter) {
            $paramElement = $document->createElement('param');
            $paramsElement->appendChild($paramElement);
            $this->appendValue($document, $paramElement, $parameter);
        }

        return $document->saveXML() ?: '';
    }

    private function appendValue(DOMDocument $document, DOMElement $parent, mixed $value): void
    {
        $valueElement = $document->createElement('value');
        $parent->appendChild($valueElement);

        if (is_int($value)) {
            $valueElement->appendChild($document->createElement('int', (string) $value));

            return;
        }

        if (is_bool($value)) {
            $valueElement->appendChild($document->createElement('boolean', $value ? '1' : '0'));

            return;
        }

        if (is_float($value)) {
            $valueElement->appendChild($document->createElement('double', (string) $value));

            return;
        }

        if (is_string($value)) {
            $valueElement->appendChild($document->createElement('string', $value));

            return;
        }

        if ($value instanceof stdClass) {
            $this->appendStruct($document, $valueElement, get_object_vars($value));

            return;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $arrayElement = $document->createElement('array');
                $dataElement = $document->createElement('data');
                $arrayElement->appendChild($dataElement);

                foreach ($value as $item) {
                    $this->appendValue($document, $dataElement, $item);
                }

                $valueElement->appendChild($arrayElement);

                return;
            }

            $this->appendStruct($document, $valueElement, $value);

            return;
        }

        if ($value instanceof Throwable) {
            $valueElement->appendChild($document->createElement('string', $value->getMessage()));

            return;
        }

        $valueElement->appendChild($document->createElement('string', (string) $value));
    }

    private function appendStruct(DOMDocument $document, DOMElement $valueElement, array $members): void
    {
        $structElement = $document->createElement('struct');

        foreach ($members as $name => $memberValue) {
            $memberElement = $document->createElement('member');
            $memberElement->appendChild($document->createElement('name', (string) $name));
            $this->appendValue($document, $memberElement, $memberValue);
            $structElement->appendChild($memberElement);
        }

        $valueElement->appendChild($structElement);
    }

    private function parseResponse(string $xml): mixed
    {
        $response = @simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

        if (! $response instanceof SimpleXMLElement) {
            throw new OdooException('Received an invalid response from Odoo.');
        }

        if (isset($response->fault)) {
            $fault = $this->decodeValue($response->fault->value);
            $faultMessage = is_array($fault) && isset($fault['faultString'])
                ? trim(Str::before((string) $fault['faultString'], "\n"))
                : 'Odoo returned an unknown error.';

            throw new OdooException($faultMessage !== '' ? $faultMessage : 'Odoo returned an unknown error.');
        }

        if (! isset($response->params->param->value)) {
            return null;
        }

        return $this->decodeValue($response->params->param->value);
    }

    private function decodeValue(SimpleXMLElement $value): mixed
    {
        if ($value->children()->count() === 0) {
            return (string) $value;
        }

        $typeNode = $value->children()[0];
        $typeName = $typeNode->getName();

        return match ($typeName) {
            'int', 'i4' => (int) $typeNode,
            'boolean' => (string) $typeNode === '1',
            'double' => (float) $typeNode,
            'string', 'dateTime.iso8601' => (string) $typeNode,
            'base64' => base64_decode((string) $typeNode, true) ?: (string) $typeNode,
            'array' => $this->decodeArray($typeNode),
            'struct' => $this->decodeStruct($typeNode),
            default => (string) $typeNode,
        };
    }

    private function decodeArray(SimpleXMLElement $arrayNode): array
    {
        $result = [];

        foreach ($arrayNode->data->value as $item) {
            $result[] = $this->decodeValue($item);
        }

        return $result;
    }

    private function decodeStruct(SimpleXMLElement $structNode): array
    {
        $result = [];

        foreach ($structNode->member as $member) {
            $result[(string) $member->name] = $this->decodeValue($member->value);
        }

        return $result;
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new OdooException('Employee login is unavailable until the Odoo connection is configured.');
        }
    }

    private function getUrl(): string
    {
        return rtrim($this->url ?? (string) config('services.odoo.url'), '/');
    }

    private function getDatabase(): string
    {
        return (string) ($this->database ?? config('services.odoo.database'));
    }

    private function getTimeout(): int
    {
        return (int) ($this->timeout ?? config('services.odoo.timeout', 10));
    }

    private function getSslVerificationOption(): bool|string
    {
        $caBundle = trim((string) ($this->caBundle ?? config('services.odoo.ca_bundle', '')));

        if ($caBundle !== '') {
            return $caBundle;
        }

        $configuredValue = $this->sslVerify ?? config('services.odoo.ssl_verify', true);

        if (is_bool($configuredValue)) {
            return $configuredValue;
        }

        if (is_numeric($configuredValue)) {
            return (bool) $configuredValue;
        }

        if (is_string($configuredValue)) {
            $normalizedValue = filter_var($configuredValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalizedValue !== null) {
                return $normalizedValue;
            }
        }

        return true;
    }
}
