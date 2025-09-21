<?php

test('basic math works', function () {
    expect(1 + 1)->toBe(2);
});

test('composer autoloader is available', function () {
    $autoloaderPath = __DIR__ . '/../../vendor/autoload.php';
    expect(file_exists($autoloaderPath))->toBeTrue();
});

test('plugin file exists and is readable', function () {
    $pluginFile = __DIR__ . '/../../sigma-signet.php';

    expect(file_exists($pluginFile))->toBeTrue();
    expect(is_readable($pluginFile))->toBeTrue();

    // Check that the file contains our plugin header
    $content = file_get_contents($pluginFile);
    expect($content)->toContain('Plugin Name: Sigma Signet');
    expect($content)->toContain('Folger Shakespeare Library');
});
