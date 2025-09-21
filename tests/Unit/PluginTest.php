<?php

use SigmaSignet\Plugin;

test('plugin class can be instantiated', function () {
    $plugin = Plugin::getInstance();

    expect($plugin)->toBeInstanceOf(Plugin::class);
});

test('plugin class is singleton', function () {
    $plugin1 = Plugin::getInstance();
    $plugin2 = Plugin::getInstance();

    expect($plugin1)->toBe($plugin2);
});

test('plugin has version', function () {
    $plugin = Plugin::getInstance();

    expect($plugin->getVersion())->toBeString();
    expect($plugin->getVersion())->toBe('0.1.0');
});
