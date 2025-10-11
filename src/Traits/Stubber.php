<?php

namespace Timeax\FortiPlugin\Traits;

use RuntimeException;

trait Stubber
{
    protected function renderStub(string $name, array $params = []): string
    {
        $stubDir = dirname(__DIR__) . '/../stub';
        $path = str_ends_with($name, '.stub') ? "$stubDir/$name" : "$stubDir/$name.stub";
        if (!file_exists($path)) {
            throw new RuntimeException("Stub not found: $path");
        }

        $contents = file_get_contents($path);
        $contents = str_replace("/IGNORE;\\n?/", "", $contents);
        extract($params, EXTR_SKIP);

        return preg_replace_callback('/#\{(.+?)}/s', static function ($m) use (&$params) {
            return (static function () use ($m, $params) {
                extract($params, EXTR_SKIP);
                return eval('return ' . $m[1] . ';');
            })();
        }, $contents);
    }
}