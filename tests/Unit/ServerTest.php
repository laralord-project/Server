<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Server\Server;
use Server\Configurator\ServerConfigurator;
use Server\Workers\WorkerContract as Worker;
use OpenSwoole\HTTP\Server as HttpServer;
use Monolog\Logger;
use Server\EnvironmentResolvers\EnvResolverContract;

/**
 * Class ServerTest
 *
 * @author Vitalii Liubimov <vitalii@liubimov.org>
 * @package Tests\Unit
 */
class ServerTest extends TestCase
{

    //TODO: implement testcase

    private $configurator;
    private $server;
    private $httpServerMock;
    private $envResolverMock;
    private $workerMock;
    private $loggerMock;

    protected function setUp(): void
    {
        // Mocking ServerConfigurator
        $this->configurator = $this->createMock(ServerConfigurator::class);

        // Creating Server instance with mocked dependencies
        $this->server = new Server($this->configurator);

        // Mocking HttpServer
        $this->httpServerMock = $this->createMock(HttpServer::class);
        $this->server->server = $this->httpServerMock;

        // Mocking Worker
        $this->workerMock = $this->createMock(Worker::class);
        $this->server->worker = $this->workerMock;

        // Mocking EnvResolver
        $this->envResolverMock = $this->createMock(EnvResolverContract::class);
        $this->server->envResolver = $this->envResolverMock;

        // Mocking Logger
        $this->loggerMock = $this->createMock(Logger::class);
    }

    public function testStart()
    {
        // Arrange: Set necessary configurations and expectations
        $this->configurator->expects($this->once())
            ->method('listServerVariables')
            ->willReturn([]);

        $this->httpServerMock->expects($this->once())
            ->method('set')
            ->with($this->isType('array'));

        $this->httpServerMock->expects($this->once())
            ->method('start');

        $this->envResolverMock->expects($this->once())
            ->method('common')
            ->with($this->isType('array'))
            ->willReturn($this->envResolverMock);

        $this->envResolverMock->expects($this->once())
            ->method('exclude')
            ->with($this->isType('array'))
            ->willReturn($this->envResolverMock);

        $this->envResolverMock->expects($this->once())
            ->method('boot');

        // Act: Call the start method
        $this->server->start();

        // Assert: Verify the logger info call
        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Starting server'));
    }

    public function testEnvInitFailsForNonVaultEnvSource()
    {
        // Arrange
        $this->server->envSource = 'file';
        $this->server->mode = ServerConfigurator::MODE_SINGLE;

        // Expect log error and exit (using exit instead of PHPUnit's exception handling for simplicity)
        $this->expectOutputString("Env init works only when env-source is vault\n");

        // Act
        $this->server->envStore('someEnvFile');
    }

    public function testEnvInitSuccess()
    {
        // Arrange
        $this->server->envSource = 'vault';
        $this->server->mode = ServerConfigurator::MODE_SINGLE;
        $this->server->envResolver = $this->envResolverMock;
        $this->server->configurator->envSourceConfig = ['env_file' => '/path/to/.env'];

        $this->envResolverMock->expects($this->once())
            ->method('storeToEnvFile')
            ->with($this->equalTo('/path/to/.env'));

        // Act
        $this->server->envStore('');

        // Assert
        // If no exception or errors are thrown, the test passes
    }

    public function testGetWorkerSingleMode()
    {
        // Arrange
        $this->server->mode = ServerConfigurator::MODE_SINGLE;
        $this->envResolverMock->expects($this->once())
            ->method('getEnvironment')
            ->willReturn('someEnvironment');

        // Act
        // $worker = $this->server->getWorker();

        // Assert
        // $this->assertInstanceOf(Worker::class, $worker);
    }
}
