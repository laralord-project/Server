<?php

namespace Server\Configurator;

interface ConfiguratorContract
{
    const CONFIG_TYPE_SERVER = 'server';
    const CONFIG_TYPE_QUEUE = 'queue';
    const CONFIG_TYPE_OPTION = 'option';
    const CONFIG_TYPE_ENVIRONMENT = 'environment';
    const CONFIG_TYPE_SYNCHRONIZER = 'synchromizer';
    const MODE_SINGLE = "single";
    const MODE_MULTI_TENANT = "multi-tenant";

    public function loadConfig();


    public function getInfo();

    public function getCliOptions(): array;
}
