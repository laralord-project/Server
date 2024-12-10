<?php

namespace Tests\Unit\EnvResolvers;

use PHPUnit\Framework\{MockObject\Exception};
use Psr\Log\LogLevel;
use Server\Application\Environment;
use Server\EnvironmentResolvers\DirectoryEnvResolver;
use Server\Exceptions\EnvSourceNotFound;
use Server\Log;
use Tests\TestCase;

/**
 * Class DirectoryResolverTest
 *
 * @author Vitalii Liubimov <vitalii@liubimov.org>
 * @package Tests\Unit\EnvResolvers
 */
class DirectoryResolverTest extends TestCase
{
    protected string $envDir = '/tmp/envs';

    protected DirectoryEnvResolver $resolver;


    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        Log::init('test-multi-tenant-application', logLevel: LogLevel::WARNING);
        $this->envDir = "{$this->envDir}.".\microtime(true);
        \mkdir($this->envDir);
        $this->resolver = new DirectoryEnvResolver(['envs_dir' => $this->envDir]);
        Environment::initTable();
    }


    protected function tearDown(): void
    {
        self::delTree($this->envDir);
        parent::tearDown();
    }


    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testBootExceptions()
    {
        $this->setProperty('directory', '/tmp/not-exists');
        $this->expectException(EnvSourceNotFound::class);
        $this->expectExceptionMessage("The env source directory /tmp/not-exists not found");
        $this->resolver->boot();

        touch('/tmp/file');
        $this->setProperty('directory', '/tmp/file');
        $this->expectException(EnvSourceNotFound::class);
        $this->expectExceptionMessage("Provided env source path /tmp/file is not a directory");
    }


    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testListSecrets()
    {
        $dir = $this->getProperty('directory');
        \file_put_contents("$dir/.env.tenant1", 'APP_NAME="app 1"'.\PHP_EOL);
        \file_put_contents("$dir/.env.tenant2", 'APP_NAME="app 2"'.\PHP_EOL);
        $this->resolver->boot();

        $this->assertEquals(['tenant1', 'tenant2'], $this->resolver->listSecrets(), "Dir is $dir");

        $this->assertEquals(
            ['APP_NAME' => 'app 1', 'TENANT_ID' => 'tenant1'],
            $this->resolver->getEnvironmentVariables('tenant1')
        );
        $this->assertEquals(
            ['APP_NAME' => 'app 2', 'TENANT_ID' => 'tenant2'],
            $this->resolver->getEnvironmentVariables('tenant2')
        );
    }


    /**
     * @return mixed
     */
    public function getDefaultObject(): mixed
    {
        return $this->resolver;
    }
}
