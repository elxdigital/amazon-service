<?php

namespace ElxDigital\AmazonService;

use Exception;
use ElxDigital\AmazonService\Helpers\AWSHeaderCalculator;

class AmazonService
{
    /**
     * @var string
     */
    private static string $endpoint;
    /**
     * @var object|array|int|string|null
     */
    private static object|array|int|string|null $params = '';
    /**
     * @var string
     */
    private static string $query = "";
    /**
     * @var string
     */
    private static string $host;
    /**
     * @var array|object|string|int|null
     */
    private static array|object|string|int|null $callback;
    /**
     * @var AWSHeaderCalculator
     */
    private static AWSHeaderCalculator $AWSHeaderCalculator;

    /**
     * @param string $host
     * @param string $accessKey
     * @param string $secretKey
     * @param string $region
     */
    public function __construct(string $host, string $accessKey, string $secretKey, string $region = 'auto')
    {
        self::$AWSHeaderCalculator = new AWSHeaderCalculator(
            $host,
            $accessKey,
            $secretKey,
            $region
        );

        self::$host = $host;
    }

    /**
     * @param string $endpoint
     * @return void
     */
    public static function setEndpoint(string $endpoint): void
    {
        self::$endpoint = $endpoint;
        self::$AWSHeaderCalculator->setUri($endpoint);
    }

    /**
     * @return object|int|string|array|null
     */
    public static function getCallback(): object|int|string|null|array
    {
        return self::$callback;
    }

    /**
     * @param object|array|int|string|null $params
     * @return void
     */
    public static function setParams(object|array|int|string|null $params): void
    {
        self::$params = $params;
        self::$AWSHeaderCalculator->setPayload(self::$params);
    }

    /**
     * @param array $query
     * @return void
     */
    public static function setQuery(array $query): void
    {
        self::$query = '?' . http_build_query($query);
        self::$AWSHeaderCalculator->setQuery($query);
    }

    /**
     * @param string $service
     * @return void
     */
    public static function setService(string $service): void
    {
        self::$AWSHeaderCalculator->setService($service);
    }

    /**
     * @return string
     */
    public static function getHost(): string
    {
        return self::$host;
    }

    /**
     * @param string $key
     * @param string $value
     * @return void
     */
    public static function addHeader(string $key, string $value): void
    {
        self::$AWSHeaderCalculator->addHeader($key, $value);
    }

    /**
     * @return void
     * @throws Exception
     */
    public static function get(): void
    {
        if (empty(self::$endpoint)) {
            throw new Exception("Endpoint is required");
        }

        self::$AWSHeaderCalculator->setMethod("GET");
        $uri = "https://" . self::$host . self::$endpoint . self::$query;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => self::$AWSHeaderCalculator->generateAuthorizationHeader(),
        ]);

        $response = curl_exec($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpStatus >= 200 && $httpStatus < 300) {

            $xml = simplexml_load_string($response);
            $json = json_encode($xml);
            self::$callback = json_decode($json);
        } else {
            self::$callback = false;
        }

        curl_close($curl);
        return;
    }

    /**
     * @return void
     * @throws Exception
     */
    public static function put(): void
    {
        if (empty(self::$endpoint)) {
            throw new Exception("Endpoint is required");
        }

        self::$AWSHeaderCalculator->setMethod("PUT");
        $uri = "https://" . self::$host . self::$endpoint . self::$query;

        $headers = self::$AWSHeaderCalculator->generateAuthorizationHeader();

        $headers[] = 'Expect:';
        if (is_string(self::$params)) {
            $headers[] = 'Content-Length: ' . strlen(self::$params);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => self::$params,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TCP_KEEPALIVE  => 1,
            CURLOPT_TCP_KEEPIDLE   => 30,
            CURLOPT_TCP_KEEPINTVL  => 15,
        ]);

        $response   = curl_exec($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $errNo  = curl_errno($curl);
            $errMsg = curl_error($curl);
            error_log("[R2 PUT ERROR] cURL #$errNo: $errMsg | URL: $uri");
            self::$callback = false;
            curl_close($curl);
            return;
        }

        if ($httpStatus >= 200 && $httpStatus < 300) {
            self::$callback = true;
        } else {
            error_log("[R2 PUT FAIL] HTTP $httpStatus | Body: " . substr($response, 0, 2000));
            self::$callback = false;
        }

        curl_close($curl);
        return;
    }

    public static function putStreamed(): void
    {
        if (empty(self::$endpoint)) {
            throw new Exception("Endpoint is required");
        }
        if (!is_resource(self::$params)) {
            throw new Exception("Stream resource esperado em setParams()");
        }

        self::$AWSHeaderCalculator->setMethod("PUT");
        $uri = "https://" . self::$host . self::$endpoint . self::$query;

        $headers = self::$AWSHeaderCalculator->generateAuthorizationHeader();
        $headers[] = 'Expect:';

        $stat = fstat(self::$params);
        if ($stat && isset($stat['size'])) {
            $headers[] = 'Content-Length: ' . $stat['size'];
        }

        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_READDATA, self::$params);
        if (isset($stat['size'])) {
            curl_setopt($ch, CURLOPT_INFILESIZE, $stat['size']);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            error_log("[R2 PUT( streamed ) ERROR] #" . curl_errno($ch) . ": " . curl_error($ch));
            self::$callback = false;
        } else {
            self::$callback = ($httpStatus >= 200 && $httpStatus < 300);
            if (!self::$callback) {
                error_log("[R2 PUT( streamed ) FAIL] HTTP $httpStatus | Body: " . substr($response, 0, 2000));
            }
        }
        curl_close($ch);
    }

    /**
     * @return void
     * @throws Exception
     */
    public static function delete(): void
    {
        if (empty(self::$endpoint)) {
            throw new Exception("Endpoint is required");
        }

        self::$AWSHeaderCalculator->setMethod("DELETE");
        $uri = "https://" . self::$host . self::$endpoint . self::$query;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => self::$AWSHeaderCalculator->generateAuthorizationHeader(),
        ]);

        $response = curl_exec($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpStatus >= 200 && $httpStatus < 300) {
            self::$callback = true;
        } else {
            self::$callback = false;
        }

        curl_close($curl);
        return;
    }
}
