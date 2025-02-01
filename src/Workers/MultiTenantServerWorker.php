<?php

namespace Server\Workers;

use OpenSwoole\Http\{Request, Response};
use Server\Application\Environment;
use Server\Exceptions\ResolverNotFoundException;
use Server\Log;
use Server\Workers\Traits\ForksManager;

use function array_slice;
use function key_exists;

/**
 * Class MultiTenantServerWorker
 *
 * @author Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\Workers
 */
class MultiTenantServerWorker extends WorkerAbstract implements WorkerContract
{
    use ForksManager;

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var Response
     */
    private Response $response;

    /**
     * @var array
     */
    private array $tenantResolvers = [];


    /**
     * @param  string  $basePath
     * @param  string  $tenantKey
     *
     * @throws ResolverNotFoundException
     */
    public function __construct(protected string $basePath, protected string $tenantKey = 'header.TENANT-ID')
    {
        $this->mapResolvers();
    }


    /**
     * @return \Closure
     */
    public function getInitHandler(): \Closure
    {
        return function ($server, $workerId) {
            $this->initWorker($server, $workerId);

            $this->cleanMemory();
            $_ENV = Environment::getCleaned();

            $this->initForksManager();
            Log::notice("Worker $workerId started");
        };
    }


    /**
     * @return \Closure
     */
    public function getRequestHandler(): \Closure
    {
        return function (Request $request, Response $response) {
            // workaround for the issue with tmpfiles clean
            // after main process request execution complete
            $waitForResponse = (bool)$request->tmpfiles;

            if (!$waitForResponse) {
                $response->detach();
            }

            $pid = $this->isolate(function () use ($request, $response) {
                Log::$logger = Log::$logger->withName("{$this->name}-{$this->workerId}-fork-" . \getmypid());
                $this->request = $request;
                $this->response = $response = Response::create($response->fd);

                Log::info("{$request->getMethod()}", $request->server);
                Log::debug("HEADERS:", $request->header);
                Log::debug('QUERY DATA:', $request->get ?: []);
                Log::debug('POST DATA:', $request->post ?: []);

                try {
                    $envKey = $this->resolveRequestTenant();

                    if (!$envKey) {
                        $this->abort(404, "Page Not Found");

                        return;
                    }

                    $env = Environment::find($envKey);
                    if (!$env) {
                        $this->abort(404, "Tenant Not Found");

                        return;
                    }

                    $_ENV = $env->env();
                    $this->createApplication();
                    define('LARAVEL_START', microtime(true));

                    // Transform the request to symfony request
                    $symfonyRequest = $this->transformRequest($request);

                    $kernel = $this->app->make($this->getProjectClass("@Contracts\\Http\\Kernel"));
                    $symfonyResponse = $kernel->handle($symfonyRequest);
                    $symfonyResponse->header('Server', 'Laralord');

                    $this->respond($symfonyResponse, $response);
                    $kernel->terminate($symfonyRequest, $symfonyResponse);
                    Log::debug('Memory usage: ' . \memory_get_usage());
                    Log::debug('Memory usage real: ' . \memory_get_usage(true));
                } catch (\Throwable $e) {
                    Log::error($e);
                    $response->end('Request Failed');
                }
            }, wait: $waitForResponse);

            if (!$waitForResponse) {
                $this->registerFork($pid);
            }
        };
    }


    /**
     * @return mixed|string|null
     */
    protected function resolveRequestTenant(): ?string
    {
        Log::notice('Resolving tenants', $this->tenantResolvers);
        // iterating the resolvers till first found
        foreach ($this->tenantResolvers as $resolverParts) {
            Log::debug('Processing '.$resolverParts);

            $resolverParts = explode('.', $resolverParts);
            $source = $resolverParts[0];

            $method = match ($source) {
                'jwt' => function () use ($resolverParts) {
                    $tokenResolve = $resolverParts[1] ?? 'header';
                    $key = $resolverParts[2] ?? 'Authorization';
                    Log::debug("Searching token on: $tokenResolve.$key");
                    $token = $this->findByPath($this->request, "$tokenResolve.$key");

                    if (!$token) {
                        return null;
                    }

                    $payload = json_decode(
                        \base64_decode(explode('.', $token)[1] ?? ''),
                        true
                    );

                    if (!$payload) {
                        Log::debug('Failed to parse jwt token');

                        return null;
                    }

                    $path = array_slice($resolverParts, 3);
                    $path = $path ? implode('.', $path) : '';

                    return $this->findByPath($payload, $path);
                },

                'oidc' => function () use ($resolverParts) {
                    $headerKey = $resolverParts[1] ?: 'x-userinfo';
                    $data = $this->findByPath($this->request, "header.$headerKey");

                    $userInfo = json_decode(\base64_decode($data ?: ''), true);

                    if (!$userInfo) {
                        Log::debug("OpenID Connect Header '$headerKey' Not Found");

                        return null;
                    }

                    $path = array_splice($resolverParts, 2);

                    return $this->findByPath($userInfo, $path);
                },
                default => function () use ($resolverParts) {
                    return $this->findByPath($this->request, $resolverParts);
                }
            };

            $result = $method();

            if ($result) {
                Log::info("Tenant ID $result resolved by $source: ".\implode('.', $resolverParts));

                return $result;
            }
        }

        return null;
    }


    /**
     * @return void
     * @throws ResolverNotFoundException
     */
    private function mapResolvers()
    {
        $resolverStrings = array_map(fn($value) => trim($value), \explode(',', $this->tenantKey));

        $resolvers = [];

        \array_walk($resolverStrings, function ($value, $key) use (&$resolvers) {
            $key = trim(explode('.', $value)[0]);

            if (!\in_array($key, ['header', 'jwt', 'oidc', 'cookie', 'get', 'post'])) {
                throw new ResolverNotFoundException("Resolver $key is not supported");
            }

            $resolvers[] = $value;
        });

        if (!$resolvers) {
            throw new ResolverNotFoundException("Resolvers not configured");
        }

        $this->tenantResolvers = $resolvers;

        Log::notice('Tenant Resolvers: ', $this->tenantResolvers);
    }


    /**
     * @param $subject
     * @param  string|array  $path
     *
     * @return string|null
     */
    private function findByPath($subject, string|array $path): ?string
    {
        if (!$path || !$subject) {
            return null;
        }
        $resolvePath = \is_array($path) ? $path : explode('.', $path);

        foreach ($resolvePath as $index => $key) {
            if (!$subject) {
                return null;
            }

            if (\is_object($subject)) {
                if (!\property_exists($subject, $key)) {
                    return null;
                }

                $subject = $subject->{$key};

                continue;
            } elseif (\is_array($subject)) {
                if ($key === '*') {
                    $subPath = array_slice($resolvePath, $index + 1);

                    foreach ($subject as $sIndex => $value) {
                        $subPathResult = $this->findByPath($value, $subPath);

                        if ($subPathResult) {
                            return $subPathResult;
                        }
                    }

                    // subpath not found - search completed
                    return null;
                }

                if (!key_exists($key, $subject) && !key_exists(\strtolower($key), $subject)
                    || (\is_numeric($key) && !isset($subject[(int) $key]))
                ) {
                    return null;
                }

                $subject = $subject[$key] ?? $subject[\strtolower($key)];
                continue;
            }

            return $subject;
        }

        return \is_string($subject) ? $subject : null;
    }


    /**
     * @param  int  $status
     * @param  string  $message
     *
     * @return void
     */
    protected function abort(int $status, string $message)
    {
        $this->response->status($status);
        $this->response->end($message);
        unset($this->request);
        unset($this->response);
    }
}
