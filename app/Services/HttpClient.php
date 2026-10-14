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
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
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
                'header'        => implode("\r\n", array_merge(['User-Agent: Locavente-Litiges/1.0', 'Accept: application/json'], $headers)),
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
