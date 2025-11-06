<?php

namespace Timeax\FortiPlugin\Traits;

use RuntimeException;
use Throwable;

trait Stubber
{
    protected function renderStub(string $name, array $params = []): string
    {
        try {
            $stubDir = dirname(__DIR__) . '/../stubs';
            $path = str_ends_with($name, '.stub') ? "$stubDir/$name" : "$stubDir/$name.stub";
            if (!file_exists($path)) {
                throw new RuntimeException("Stub not found: $path");
            }

            $contents = file_get_contents($path);
            $contents = preg_replace('/IGNORE;[ \t]*\R?/', '', $contents);
            extract($params, EXTR_SKIP);

            return preg_replace_callback('/#\{(.+?)}/s', static function ($m) use (&$params) {
                return (static function () use ($m, $params) {
                    extract($params, EXTR_SKIP);

                    $expr = trim($m[1]);

                    // If it's a bare identifier like `foo`, turn it into `$foo`
                    if ($expr !== '' && $expr[0] !== '$' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expr)) {
                        $expr = '$' . $expr;
                    }

                    return eval('return ' . $expr . ';');
                })();
            }, $contents);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to render stub: {$e->getMessage()}, stub: $name");
        }
    }
}