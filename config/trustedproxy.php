<?php

$configuredProxies = (string) env('TRUSTED_PROXIES', '127.0.0.1,::1');

return [
    'proxies' => in_array($configuredProxies, ['*', '**'], true)
        ? $configuredProxies
        : array_values(array_filter(array_map(
            trim(...),
            explode(',', $configuredProxies),
        ))),
];
