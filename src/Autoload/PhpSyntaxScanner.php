<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Autoload;

use PhpParser\Error as PhpParserError;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class PhpSyntaxScanner
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @param list<string> $dirs Absolute directories to scan for *.php
     * @return array{ok: bool, errors: list<array{file:string, error:string}>}
     */
    public function scanDirs(array $dirs): array
    {
        $errors = [];

        foreach ($dirs as $dir) {
            $dir = rtrim($dir, "/\\");
            if ($dir === '' || !is_dir($dir)) {
                continue;
            }

            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $f */
            foreach ($it as $f) {
                if (!$f->isFile()) continue;

                $path = $f->getPathname();
                if (!str_ends_with(strtolower($path), '.php')) continue;

                // skip obvious non-source folders if you want
                if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;

                $code = @file_get_contents($path);
                if ($code === false) {
                    $errors[] = ['file' => $path, 'error' => 'read_failed'];
                    continue;
                }

                try {
                    $this->parser->parse($code); // parse only; no execution
                } catch (PhpParserError $e) {
                    $errors[] = ['file' => $path, 'error' => $e->getMessage()];
                }
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /**
     * Convenience: throw if not ok.
     * @param list<string> $dirs
     */
    public function assertDirsOk(array $dirs, string $pluginAlias): void
    {
        $r = $this->scanDirs($dirs);

        if ($r['ok']) return;

        // Keep message short; store full list in installer logs/meta
        $first = $r['errors'][0] ?? ['file' => 'unknown', 'error' => 'unknown'];
        throw new RuntimeException("Plugin {$pluginAlias} contains PHP parse errors (e.g. {$first['file']}: {$first['error']}).");
    }
}