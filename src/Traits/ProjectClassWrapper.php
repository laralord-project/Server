<?php

namespace Server\Traits;

trait ProjectClassWrapper
{
    /**
     * Workaround for building phar with php-scoper
     * - which replace Illuminate classes by scoped classes
     *
     * @param  string  $class
     * @param          $rootNamespace
     *
     * @return string
     */
    public function getProjectClass(string $class, $rootNamespace = "Illuminate"): string
    {
        $rootNamespace = trim($rootNamespace, ' @');
        // $rootNamespace = $rootNamespace ? "$rootNamespace" : "";

        $class = trim($class, ' \\@');

        return "$rootNamespace\\$class";
    }
}
