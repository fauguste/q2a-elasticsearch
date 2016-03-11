<?php

use Aws\Credentials\Credentials;
use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use Elasticsearch\ClientBuilder;
use GuzzleHttp\Ring\Future\CompletedFutureArray;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;

// Documentation : https://docs.aws.amazon.com/aws-sdk-php/v3/guide/service/es-data-plane.html
function create_es_client ( $host , $port, $region, $key, $secret ) {
    require 'vendor/autoload.php';
    
    $psr7Handler = Aws\default_http_handler();
    $signer = new SignatureV4('es', $region);
    //$credentialProvider = CredentialProvider::defaultProvider();
    $credentialProvider = new Credentials($key, $secret);

    // Construct the handler that will be used by Elasticsearch-PHP
    $handler = function (array $request) use ($psr7Handler,$signer,$credentialProvider) {
        // Amazon ES listens on standard ports (443 for HTTPS, 80 for HTTP).
        $request['headers']['host'][0] = parse_url($request['headers']['host'][0], PHP_URL_HOST);

        // Create a PSR-7 request from the array passed to the handler
        $psr7Request = new Request($request['http_method'], (new Uri($request['uri']))->withScheme($request['scheme'])->withHost($request['headers']['host'][0]),$request['headers'],$request['body']);

        // Sign the PSR-7 request with credentials from the environment
        $signedRequest = $signer->signRequest($psr7Request, $credentialProvider);

        // TODO : Improuve security
        $client = new GuzzleHttp\Client(['verify' => false]);

        $response = $client->send($signedRequest);

        // Convert the PSR-7 response to a RingPHP response
        return new CompletedFutureArray([
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => $response->getBody()->detach(),
            'transfer_stats' => ['total_time' => 0],
            'effective_url' => (string) $psr7Request->getUri(),
        ]);
    };

    return ClientBuilder::create()->setHandler($handler)->setHosts(["$host:$port"])->build();
}
