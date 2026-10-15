<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Petit client HTTP (cURL, avec repli sur les flux PHP).
 */
class HttpClient
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int, body:string, error:?string, json:mixed}
     */
    public static function get(string $url, array $headers = [], int $timeout = 8): array
    {
        if (function_exists('curl_init')) {
            return self::viaCurl($url, $headers, $timeout);
        }
        return self::viaStream($url, $headers, $timeout);
    }

    /**
     * Requete POST (JSON).
     * @param array<string,mixed>  $body
     * @param array<string,string> $headers
     * @return array{status:int, body:string, error:?string, json:mixed}
     */
    public static function post(string $url, array $body, array $headers = [], int $timeout = 30): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return self::pack(0, '', 'Encodage JSON impossible');
        }
        if (function_exists('curl_init')) {
            return self::viaCurlPost($url, $payload, $headers, $timeout);
        }
        return self::viaStreamPost($url, $payload, $headers, $timeout);
    }

    private static function viaCurlPost(string $url, string $payload, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'Locavente-Litiges/1.0',
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json', 'Content-Type: application/json'], self::formatHeaders($headers)),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return self::pack($status, $body === false ? '' : (string) $body, $error);
    }

    private static function viaStreamPost(string $url, string $payload, array $headers, int $timeout): array
    {
        $entetes = array_merge(['User-Agent: Locavente-Litiges/1.0', 'Accept: application/json', 'Content-Type: application/json'], self::formatHeaders($headers));
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'timeout'       => $timeout,
                'ignore_errors' => true,
                'header'        => implode("\r\n", $entetes),
                'content'       => $payload,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body   = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return self::pack($status, $body === false ? '' : $body, $body === false ? 'Requete HTTP impossible' : null);
    }

    /**
     * @param array<string,string>|array<int,string> $headers
     * @return array<int,string>
     */
    private static function formatHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $nom => $valeur) {
            // Tableau associatif « Nom => valeur », ou liste de lignes deja formatees.
            $out[] = is_int($nom) ? (string) $valeur : $nom . ': ' . $valeur;
        }
        return $out;
    }

    private static function viaCurl(string $url, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'Locavente-Litiges/1.0',
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], self::formatHeaders($headers)),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return self::pack($status, $body === false ? '' : (string) $body, $error);
    }

    private static function viaStream(string $url, array $headers, int $timeout): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => $timeout,
                'ignore_errors' => true,
                'header'        => implode("\r\n", array_merge(['User-Agent: Locavente-Litiges/1.0', 'Accept: application/json'], self::formatHeaders($headers))),
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body   = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return self::pack($status, $body === false ? '' : $body, $body === false ? 'Requete HTTP impossible' : null);
    }

    private static function pack(int $status, string $body, ?string $error): array
    {
        $json = null;
        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = $decoded;
            }
        }
        return ['status' => $status, 'body' => $body, 'error' => $error, 'json' => $json];
    }

    public static function urlWithQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($params);
    }
}
