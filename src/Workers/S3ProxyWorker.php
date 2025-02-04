<?php

namespace Server\Workers;

use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use OpenSwoole\Coroutine;
use OpenSwoole\Table;
use Server\Log;
use Swoole\Http\{Request, Response};

/**
 * Class S3ProxyWorker
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\Workers
 */
class S3ProxyWorker implements WorkerContract
{
    /**
     * @var string
     */
    private string $name = "laralord:s3-proxy:worker";

    /**
     * @var array|string[]
     */
    private array $errorLocations = [
        "404" => "/index.html",
    ];

    private array $rules = [
        //  any files like /test/test.json
        '/([\w\/-]+(?:\/[\w-]+)?\.[\w]{1,10})$/' => '$1',
        // list like locations /test or /test/
        '/^((\/[\w\-;=]+)*\/?)$/'                => '/index.html',
    ];

    /**
     * @var string
     */
    private string $listPath = "/";


    /**
     * @param S3Client $client
     * @param string $bucket
     * @param string $cacheDir
     */
    public function __construct(
        private S3Client $client,
        private string $bucket,
        private Table $cache,
        private string $cacheDir = "/tmp/s3-proxy"
    ) {
    }


    /**
     * @return \Closure
     */
    public function getInitHandler(): \Closure
    {
        return function ($server, $workerId) {
            $this->name = "{$this->name}-$workerId";
            cli_set_process_title($this->name);
            Log::$logger = Log::$logger->withName($this->name);
            Log::notice('Started');
            Coroutine::set(['hook_flags' => \OpenSwoole\Runtime::HOOK_ALL]);
        };
    }


    /**
     * @return \Closure
     */
    public function getRequestHandler(): \Closure
    {
        return function (Request $request, Response $response) {
            Log::info("{$request->getMethod()}  {$request->server['request_uri']}");
            Log::debug("Request HEADERS:", $request->header);

            try {
                $this->processRequest($request, $response);
            } catch (\Throwable $e) {
                Log::error($e);
                $response->status(500);
                $response->end('Server Error');
            }

            Log::debug('Request Completed:' . time());
        };
    }


    /**
     * @param Request $request
     * @param Response $response
     *
     * @return void
     */
    private function processRequest(Request $request, Response &$response)
    {
        $server = $request->server;
        $headers = $request->header;
        $basePath = $headers['document-root'] ?? '';

        if (!$basePath) {
            $response->status(500);
            $response->end('Server Error. Failed to retrieve the document-root');

            return;
        }

        $path = $this->getLocation($server['request_uri'] ?: '/');
        Log::debug("Path: '$path'");

        if (!$path) {
            $response->status(404);
            $response->end("Route Not Found");

            return;
        }

        $s3Path = $this->getS3Path($path, $basePath);
        Log::debug("s3 Path: $path");
        $cacheControl = $headers['cache-control'] ?? '';

        if ($this->fromCache($s3Path, $response, $cacheControl)) {
            return;
        }

        try {
            $this->getS3Content($s3Path, $response);
        } catch (S3Exception $e) {
            Log::error($e);
            Log::debug("Status code is: {$e->getStatusCode()}", [$e->getStatusCode()]);
            // $rewriteLocation = $this->errorLocations[$e->getStatusCode()] ?? '';
            // Log::error("Rewrite location is: {$rewriteLocation}");
            //
            // if ($rewriteLocation) {
            //     $rewriteS3Path = $this->getS3Path($rewriteLocation, $basePath);
            //     Log::info("new s4 path: $rewriteS3Path");
            //
            //     if ($s3Path !== $rewriteS3Path) {
            //         if ($this->fromCache($rewriteS3Path, $response, $cacheControl)) {
            //             return;
            //         }
            //
            //         if ($this->getS3Content($rewriteS3Path, $response, true)) {
            //             return;
            //         }
            //     }
            // }

            $response->status($e->getStatusCode());
            $response->end('404 Not Found');
        }
    }


    /**
     * @param string $s3Path
     * @param Response $response
     * @param bool $catchExceptions
     *
     * @return bool
     */
    public function getS3Content(string $s3Path, Response $response, bool $catchExceptions = false): bool
    {
        Log::debug("Proxy bucket {$this->bucket}: key: $s3Path");
        try {
            $options = [
                'Bucket'     => $this->bucket,
                'Key'        => $s3Path,
                'x-amz-date' => gmdate('Ymd\THis\Z'),
            ];
            // Fetch the file from S3
            $result = $this->client->getObject($options);
        } catch (S3Exception $e) {
            Log::notice("Failed the retrieve the content from S3 : {$e->getMessage()}");

            if (!$catchExceptions) {
                throw $e;
            }

            return false;
        }

        // Cache the file locally
        $this->cache($s3Path, $result);

        // Send response
        $response->header("Content-Type", $result->get('ContentType'));
        $response->header("Content-Length", $result->get('ContentLength'));
        $response->end($result['Body']);

        return true;
    }


    public function getLocation($uri): ?string
    {
        Log::debug("Checking for the route for location '$uri'");

        foreach ($this->rules as $regex => $replace) {
            if (\preg_match($regex, $uri)) {
                Log::debug("Location '$uri' math route: '$regex'");

                return \preg_replace($regex, $replace, $uri, 1);
            }
        }

        Log::notice("Location '$uri' doesn't match any route");

        return null;
    }


    /**
     * @param string $location
     * @param string $basePath
     *
     * @return string
     */
    public function getS3Path(string $location, string $basePath): string
    {
        $basePath = trim($basePath, '/');

        // Handle directory request (serve index.html)
        if (\str_ends_with($location, '/')) {
            return "$basePath/index.html";
        }

        $location = trim($location, '/');

        return "$basePath/$location";
    }


    /**
     * @param string $s3Path
     *
     * @return string
     */
    public function getCachePath(string $s3Path): string
    {
        return "{$this->cacheDir}/" . $this->getCacheKey($s3Path);
    }


    public function getCacheKey(string $s3Path)
    {
        return md5($s3Path);
    }


    /**
     * @param string $s3Path
     *
     * @return bool
     */
    public function isCached(string $s3Path): bool
    {
        $cacheKey = md5($s3Path);

        if (!$this->cache->exists($cacheKey)) {
            Log::info("Cache for $s3Path not found");
        }

        return \file_exists($this->getCachePath($s3Path)) && $this->cache->exists($cacheKey);
    }


    /**
     * @param string $s3Path
     * @param          $content
     *
     * @return bool
     */
    public function cache(string $s3Path, Result $result): bool
    {
        $cachePath = $this->getCachePath($s3Path);
        $cacheFileSaved = \file_put_contents($cachePath, $result->get('Body'));

        $result = $result->toArray();
        unset($result['Body']);
        Log::debug("$s3Path result :", $result);
        $cacheData = [
            's3_path'        => $s3Path,
            'content_type'   => $result['ContentType'],
            'content_length' => $result['ContentLength'],
            'created_at'     => \time(),
            'last_usage_at'  => time(),
            'metadata'       => json_encode($result),
        ];

        Log::debug('Cache Data ', $cacheData);

        $cacheDataStored = $this->cache->set(md5($s3Path), $cacheData);

        Log::debug("Request Cached $s3Path on $cachePath");

        return $cacheFileSaved && $cacheDataStored;
    }


    /**
     * @param string $s3Path
     * @param Response $response
     *
     * @return bool
     */
    public function fromCache(string $s3Path, Response &$response, $cacheControl): bool
    {
        if ($cacheControl == 'no-cache') {
            return false;
        }

        if (!$this->isCached($s3Path)) {
            return false;
        }

        $cachePath = $this->getCachePath($s3Path);
        $cacheKey = md5($s3Path);
        $cacheData = $this->cache->get($cacheKey);
        Log::info("Response from cache: $s3Path");

        $response->header("Content-Type", $cacheData['content_type']);
        $response->header("Content-Length", $cacheData['content_length']);
        $response->end(file_get_contents($cachePath));

        Log::debug('Cached Data ', $cacheData);
        $cacheData['last_usage'] = \time();

        if (!$this->cache->set($cacheKey, $cacheData)) {
            Log::error('Failed to update cache data');
        };

        return true;
    }
}
