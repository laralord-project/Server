<?php

namespace Tests\Unit\Workers;

use PHPUnit\Framework\{MockObject\Exception};
use Psr\Log\LogLevel;
use ReflectionClass;
use ReflectionException;
use Server\Exceptions\ResolverNotFoundException;
use Server\Log;
use Server\Workers\MultiTenantServerWorker;
use Swoole\Http\{Request, Response};
use Tests\TestCase;

use function hash_hmac;
use function implode;

class MultiTenantApplicationTest extends TestCase
{
    protected string $basePath = '/var/www';

    protected string $tenantKey = 'header.TENANT-ID';

    protected MultiTenantServerWorker $application;

    protected $request;

    protected $response;


    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        Log::init('test-multi-tenant-application', logLevel: LogLevel::EMERGENCY);
        $this->application = new MultiTenantServerWorker('/var/www', 'header.TENANT-ID');
        $this->reflection = new ReflectionClass($this->application);
        $this->request = $this->createMock(Request::class);
        $this->response = $this->createMock(Response::class);
        $this->setProperty('request', $this->request);
        $this->setProperty('response', $this->response);
    }


    public function testResolverMapper()
    {
        $resolvers = [
            "header.TENANT-ID",
            "get.tenant_id",
            "jwt.header.authorization",
        ];

        $this->setProperty('tenantKey', implode(',', $resolvers));
        $this->call('mapResolvers');
        $this->assertEquals($resolvers, $this->getProperty('tenantResolvers'));
        $this->expectException(ResolverNotFoundException::class);
        $this->setProperty('tenantKey', 'wongResolver.test');
        $this->call('mapResolvers');

        // test trim the keys
        $this->setProperty('tenantKey', 'get. test');
        $this->assertEquals(
            ['get.test'],
            $this->getProperty('tenantResolvers'),
            'Field test must be mapped without the space');
    }


    /**
     * @return void
     * @throws ReflectionException
     */
    public function testResolveRequestTenantWithValidHeader()
    {
        $this->request->header = ['TENANT-ID' => 'test-tenant-id'];

        // Assert that the resolved tenant ID is correct.
        $resolvers = $this->getProperty('tenantResolvers');
        $this->assertEquals(['header.TENANT-ID'], $resolvers, 'Wrong resolvers map');

        $result = $this->call('resolveRequestTenant');
        $this->assertEquals('test-tenant-id', $result);

        $this->request->header = [];

        $result = $this->call('resolveRequestTenant');
        $this->assertEmpty($result, 'The Tenant Resolver must return empty result');
    }


    /**
     * @return void
     * @throws Exception
     * @throws ReflectionException
     */
    public function testResolveRequestTenantWithJwt()
    {
        $token = $this->generateJWT(['tenant_id' => 'tenant1']);

        $this->request->header = ['Authorization' => "Bearer $token"];

        $this->setProperty('tenantKey', 'jwt.header.Authorization.tenant_id');
        $this->call('mapResolvers');

        $tenantId = $this->call('resolveRequestTenant');
        $this->assertEquals('tenant1', $tenantId, 'Tenant ID must be resolved from JWT token');
    }


    /**
     * @return void
     * @throws Exception
     * @throws ReflectionException
     */
    public function testResolveRequestTenantWithRequestData()
    {
        $this->request->get = ['tenant_id' => 'tenant1'];

        $this->setProperty('tenantKey', 'get.tenant_id');
        $this->call('mapResolvers');

        $tenantId = $this->call('resolveRequestTenant');
        $this->assertEquals('tenant1', $tenantId, 'Tenant ID must be resolved from HTTP params');

        $this->request->get = ['tenant_id' => 'tenant1'];;
        $this->request->post = ['tenant_id' => 'tenant2'];

        $this->setProperty('tenantKey', 'post.tenant_id');
        $this->call('mapResolvers');

        $tenantId = $this->call('resolveRequestTenant');
        $this->assertEquals('tenant2', $tenantId, 'Tenant ID must be resolved from POST data');

        // test resolver order
        $this->setProperty('tenantKey', 'header.TENANT-ID,post.tenant_id,get.tenant_id');
        $this->call('mapResolvers');
        // test from header first
        $this->request->header = ['TENANT-ID' => 'tenant3'];
        $tenantId = $this->call('resolveRequestTenant');
        $this->assertEquals('tenant3', $tenantId, 'Tenant ID must be resolved from HEADER first');
        // test from post second
        $this->request->header = [];
        $tenantId = $this->call('resolveRequestTenant');
        $this->assertEquals('tenant2', $tenantId, 'Tenant ID must be resolved from POST data, wrong order');

        // test from post second
        $this->request->post = [];
        $tenantId = $this->call('resolveRequestTenant');
        $this->assertEquals('tenant1', $tenantId, 'Tenant ID must be resolved from POST data, wrong order');
    }


    /**
     * @return void
     * @throws Exception
     * @throws ReflectionException
     */
    public function testWildcardedSearch()
    {
        $this->request->post = ['organization' => ['org1' => ['tenant_id' => 'tenant2']]];
        $jwt = $this->generateJWT(['organization' => ['org1' => ['tenant_id' => 'tenantFromJwt']]]);
        $this->request->header = ['authorization' => "Bearer $jwt"];

        $this->setProperty(
            'tenantKey',
            'post.organization.*.tenant_id, jwt.header.authorization.organization.*.tenant_id'
        );
        $this->call('mapResolvers');

        // test post data first
        $tenantId = $this->call('resolveRequestTenant');
        $this->assertEquals('tenant2', $tenantId, 'Wildcard search doesn\'t work');
        // test search on jwt payload
        $this->request->post = [];
        $tenantId = $this->call('resolveRequestTenant');
        $this->assertEquals('tenantFromJwt', $tenantId, 'Wildcard search doesn\'t work for JWT');
    }


    public function testOpenIDConnectHeader() {
        $this->request->header = [
            'x-userinfo' => \base64_encode(\json_encode([
                'user_id' => 'test',
                'email' => 'email@example.com',
                'organization' => [
                    'tenant_id' => [
                        'tenant_id' => 'tenant10'
                    ]
                ],
            ]))
        ];

        $this->setProperty(
            'tenantKey',
            'oidc.x-userinfo.organization.*.tenant_id, jwt.header.authorization.organization.*.tenant_id'
        );

        $this->call('mapResolvers');

        // test post data first
        $tenantId = $this->call('resolveRequestTenant');
        $this->assertEquals('tenant10', $tenantId, 'Failed to resolve tenant by OpenID connect header');
    }


    public function testNumericKeyOnPath() {
        $this->request->header = [
            'x-userinfo' => \base64_encode(\json_encode([
                'user_id' => 'test',
                'email' => 'email@example.com',
                'organization' => [
                    'tenant_company_id' => [
                        'tenant_id' => ['tenant10']
                    ]
                ],
            ]))
        ];

        $this->setProperty(
            'tenantKey',
            'oidc.x-userinfo.organization.*.tenant_id.0'
        );

        $this->call('mapResolvers');

        // test post data first
        $tenantId = $this->call('resolveRequestTenant');
        $this->assertEquals('tenant10', $tenantId, 'Failed to resolve tenant by OpenID connect header');
    }


    public function getDefaultObject(): mixed
    {
        return $this->application;
    }
}
