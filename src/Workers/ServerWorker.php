<?php

namespace Server\Workers;

use OpenSwoole\Http\{Request, Response};
use Server\Application\Environment;
use Server\Log;
use Server\Workers\Traits\ForksManager;

/**
 * Class ServerWorker
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\Workers
 */
class ServerWorker extends WorkerAbstract implements WorkerContract
{
    use ForksManager;

    /**
     * @param  Environment  $env
     * @param  string  $shouldWarmUp
     */
    public function __construct(protected Environment $env, public string $shouldWarmUp = '/')
    {
        $this->basePath = $this->env->env()['APP_BASE_PATH'];

        if ($this->shouldWarmUp) {
            self::$healthCheckEndpoint = $this->shouldWarmUp;
        }
    }


    /**
     * @return \Closure
     */
    public function getInitHandler(): \Closure
    {
        return function ($server, $workerId) {
            $this->env->fresh();
            $this->initWorker($server, $workerId);

            $this->cleanMemory();
            $this->createApplication();
            $this->shouldWarmUp && $this->warmUp();

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
            try {
                Log::info(
                    "{$request->server['request_method']} {$request->server['request_uri']}",
                    ['forks_count'=> $this->forkPids->count()]
                );
                Log::debug('Headers: ', $request->header);
                Log::debug('Data: '.$request->rawContent());
                $this->cleanZombieProcesses();

                if (!$this->waitForForkRelease()) {
                    Log::warning('Max worker fork reached');
                    $response->status('503', 'Max Forks Limit Reached');
                    $response->end();

                    return;
                }

                $response->detach();

                // processing request on forked process
                $pid = $this->isolate(function () use ($request, $response) {
                    $response = Response::create($response->fd);
                    $_ENV = $this->env->env();

                    define('LARAVEL_START', microtime(true));
                    // Transform the request to symfony request
                    $symfonyRequest = $this->transformRequest($request);

                    $kernel = $this->app->make($this->getProjectClass("@Contracts\\Http\\Kernel"));

                    $symfonyResponse = $kernel->handle($symfonyRequest);
                    $this->respond($symfonyResponse, $response);
                    $kernel->terminate($symfonyRequest, $symfonyResponse);
                }, wait: false);

                $this->registerFork($pid);
            } catch (\Throwable $e) {
                Log::error($e);
            }
        };
    }
}
