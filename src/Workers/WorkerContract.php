<?php

namespace Server\Workers;

interface WorkerContract
{
    public function getInitHandler(): \Closure;


    public function getRequestHandler(): \Closure;
}
