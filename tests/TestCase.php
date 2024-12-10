<?php

namespace Tests;

use PHPUnit\Framework\TestCase as TestCaseBase;
use ReflectionClass;
use ReflectionException;

abstract class TestCase extends TestCaseBase
{
    public string $objectKey;

    protected ReflectionClass $reflection;
    
    abstract public function getDefaultObject(): mixed;

    public static function delTree($dir) {

        $files = array_diff(scandir($dir), array('.','..'));

        foreach ($files as $file) {

            (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");

        }

        return rmdir($dir);
    }


    /**
     * @param  string  $property
     * @param $object
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function getProperty(string $property, $object = null)
    {
        $object = $object ?:$this->getDefaultObject();
        $reflectedClass = ($object ===$this->getDefaultObject())
            ? ($this->reflection ?? new ReflectionClass($object))
            : new ReflectionClass($object);
        $reflection = $reflectedClass->getProperty($property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }


    /**
     * @param $property
     * @param $value
     * @param $object
     *
     * @return void
     * @throws ReflectionException
     */
    public function setProperty($property, $value, $object = null): void
    {
        $object = $object ?:$this->getDefaultObject();
        $reflectedClass = ($object ===$this->getDefaultObject())
            ? $this->reflection ?? new ReflectionClass($object)
            : new ReflectionClass($object);
        $property = $reflectedClass->getProperty($property);
        $property->setAccessible(true);

        $property->setValue($object, $value);
    }


    /**
     * @param  string  $method
     * @param  array  $args
     * @param $object
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function call(string $method, array $args = [], $object = null): mixed
    {
        $object = $object ?:$this->getDefaultObject() ?? null;
        $reflectedClass = ($object ===$this->getDefaultObject())
            ? $this->reflection ?? new ReflectionClass($object)
            : new ReflectionClass($object);

        $method = $reflectedClass->getMethod($method);
        $method->setAccessible(true);

        return $method->invoke($object, ...$args);
    }


    /**
     * @param $payload
     * @param $header
     *
     * @return string
     */
    public function generateJWT($payload = [], $header = []): string
    {
        $tokenHeader = $this->base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'] + $header));
        $tokenPayload = $this->base64url_encode(json_encode([
                'iss' => 'http://some-service',
            ] + $payload));
        $tokenSignature = $this->base64url_encode(hash_hmac('sha256', "$tokenHeader.$tokenPayload", 'secret'));

        return "$tokenHeader.$tokenPayload.$tokenSignature";
    }


    function base64url_encode($text): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }
}
