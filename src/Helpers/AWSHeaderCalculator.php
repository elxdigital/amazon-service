<?php

namespace ElxDigital\AmazonService\Helpers;
use GuzzleHttp\Psr7\Utils;
class AWSHeaderCalculator
{
    /**
     * @var string
     */
    private string $host;
    /**
     * @var string
     */
    private string $accessKey;
    /**
     * @var string
     */
    private string $secretKey;
    /**
     * @var string
     */
    private string $method = "GET";
    /**
     * @var string
     */
    private string $region = "auto";
    /**
     * @var string
     */
    private string $service = "s3";
    /**
     * @var array
     */
    private array $headers = [];
    /**
     * @var int
     */
    private int $timestamp;
    /**
     * @var array
     */
    private array $query = [];
    /**
     * @var array|object|string
     */
    private array|object|string $payload = [];
    /**
     * @var string
     */
    private string $uri = "/";

    /**
     *
     */
    private const ALGORITHM = "AWS4-HMAC-SHA256";

    /**
     * @param string $host
     * @param string $accessKey
     * @param string $secretKey
     * @param string $region
     */
    public function __construct(string $host, string $accessKey, string $secretKey, string $region = "auto")
    {
        $this->host = $host;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region;

        $this->timestamp = time();
    }

    /**
     * @param string $method
     * @return void
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * @param string $service
     * @return void
     */
    public function setService(string $service): void
    {
        $this->service = $service;
    }

    /**
     * @param array $query
     * @return void
     */
    public function setQuery(array $query): void
    {
        $this->query = $query;
    }

    /**
     * @param array|object $payload
     * @return void
     */
    public function setPayload(array|object|string $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @param string $uri
     * @return void
     */
    public function setUri(string $uri): void
    {
        $this->uri = !empty($uri) ? $uri : "/";
    }

    /**
     * @param string $key
     * @param string $value
     * @return void
     */
    public function addHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    /**
     * @param string $uri
     * @return string
     */
    private function uriEncode(string $uri): string
    {
        return implode("/", array_map("rawurlencode", explode("/", $uri)));
    }

    /**
     * @param string $string
     * @return string
     */
    private function trim(string $string): string
    {
        return trim($string);
    }

    /**
     * @param string $string
     * @return string
     */
    private function lowercase(string $string): string
    {
        return strtolower($string);
    }

    /**
     * @param string $string
     * @param bool $isBinary
     * @return string
     */
    private function sha256hash(string $string, bool $isBinary = false): string
    {
        return hash("sha256", $string, $isBinary);
    }

    /**
     * @param string $string
     * @param string $key
     * @param bool $isBinary
     * @return string
     */
    private function hmacSHA256(string $string, string $key, bool $isBinary = false): string
    {
        return hash_hmac("sha256", $string, $key, $isBinary);
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    private function getISO8601DateTime(): string
    {
        return gmdate('Ymd\THis\Z', $this->timestamp);
    }

    /**
     * @return string
     */
    private function getISO8601Date(): string
    {
        return gmdate('Ymd', $this->timestamp);
    }

    /**
     * @return void
     */
    private function createSignedHeaders(): void
    {
        unset($this->headers['Authorization']);
        unset($this->headers['Host']);
        unset($this->headers['X-Amz-Content-Sha256']);
        unset($this->headers['X-Amz-Date']);

        $this->headers['Host'] = $this->host;
        $this->headers['X-Amz-Content-Sha256'] = !empty($this->payload) ? Utils::hash($this->payload, 'sha256') : $this->sha256hash("");
        $this->headers['X-Amz-Date'] = $this->getISO8601DateTime();
    }

    /**
     * @return string
     */
    private function createCanonicalQuery(): string
    {
        if (empty($this->query)) return "";
        ksort($this->query);

        $canonicalQuery = [];
        foreach ($this->query as $key => $value) {
            $key = $this->uriEncode($key);
            $value = $this->uriEncode($value);
            $canonicalQuery[$key] = $value;
        }

        return http_build_query($canonicalQuery);
    }

    /**
     * @return string
     */
    private function createCanonicalHeaders(): string
    {
        if (empty($this->headers)) return "";
        ksort($this->headers);

        $canonicalHeadersString = "";
        foreach ($this->headers as $key => $value) {
            $key = $this->lowercase($this->trim($key));
            $value = $this->trim($value);

            $canonicalHeadersString .= $key . ":" . $value . "\n";
        }

        return $canonicalHeadersString;
    }

    /**
     * @return string
     */
    private function createCanonicalRequest(): string
    {
        $httpVerb = $this->method;
        $canonicalUri = $this->uriEncode($this->uri);
        $canonicalQuery = $this->createCanonicalQuery();
        $canonicalHeaders = $this->createCanonicalHeaders();
        $signedHeaders = implode(";", array_map("strtolower", array_keys($this->headers)));
        $hashedPayload = $this->headers['X-Amz-Content-Sha256'];

        return "{$httpVerb}\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$hashedPayload}";
    }

    /**
     * @return string
     */
    private function createSignature(): string
    {
        $dateKey = $this->hmacSHA256($this->getISO8601Date(), "AWS4{$this->secretKey}", true);
        $dateRegionKey = $this->hmacSHA256($this->region, $dateKey, true);
        $dateRegionServiceKey = $this->hmacSHA256($this->service, $dateRegionKey, true);

        return $this->hmacSHA256("aws4_request", $dateRegionServiceKey, true);
    }

    /**
     * @return string
     */
    private function calculateSignature(): string
    {
        $canonicalRequest = $this->createCanonicalRequest();
        $stringToSign = self::ALGORITHM . "\n{$this->getISO8601DateTime()}\n{$this->getISO8601Date()}/{$this->region}/{$this->service}/aws4_request\n{$this->sha256hash($canonicalRequest)}";
        $signingKey = $this->createSignature();

        return $this->hmacSHA256($stringToSign, $signingKey);
    }

    /**
     * @return array
     */
    public function generateAuthorizationHeader(): array
    {
        $this->timestamp = time();
        $this->createSignedHeaders();

        $signature = $this->calculateSignature();
        $signedHeaders = implode(";", array_map("strtolower", array_keys($this->headers)));
        $value = self::ALGORITHM . " Credential={$this->accessKey}/{$this->getISO8601Date()}/{$this->region}/{$this->service}/aws4_request, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $this->addHeader("Authorization", $value);

        return array_map(function ($key, $value) {
            return "{$key}: {$value}";
        }, array_keys($this->headers), $this->headers);
    }
}
