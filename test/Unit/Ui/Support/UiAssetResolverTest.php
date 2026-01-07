<?php

declare(strict_types=1);

namespace Tests\Unit\Ui\Support;

use PHPUnit\Framework\MockObject\MockObject;
use Timeax\FortiPlugin\Autoload\Psr4RegistryStore;
use Timeax\FortiPlugin\Ui\Support\UiAssetResolver;
use Tests\PackageTestCase;

final class UiAssetResolverTest extends PackageTestCase
{
    private UiAssetResolver $resolver;
    private Psr4RegistryStore $psr4Store;
    private string $tempRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRegistry = tempnam(sys_get_temp_dir(), 'forti_registry') . '.php';
        config(['fortiplugin.autoload_registry' => $this->tempRegistry]);

        $this->psr4Store = new Psr4RegistryStore();
        $this->resolver = new UiAssetResolver($this->psr4Store);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempRegistry)) {
            @unlink($this->tempRegistry);
        }
        parent::tearDown();
    }

    private function writeRegistry(array $data): void
    {
        file_put_contents($this->tempRegistry, "<?php return " . var_export($data, true) . ";");
    }

    public function test_resolvePublicPath_uses_alias_from_registry(): void
    {
        $alias = 'my-awesome-plugin';
        $this->writeRegistry([
            'plugins' => [
                $alias => [
                    'plugin_name' => 'MyAwesomePlugin',
                ],
            ],
        ]);

        config(['fortiplugin.ui.embed.public_base' => '/vendor/fortiplugin/{alias}']);

        $path = $this->resolver->resolvePublicPath($alias);

        $this->assertSame('/vendor/fortiplugin/my-awesome-plugin/build', $path);
    }

    public function test_resolvePublicPath_handles_legacy_slug_placeholder(): void
    {
        $alias = 'my-awesome-plugin';
        $this->writeRegistry([
            'plugins' => [
                $alias => [
                    'plugin_name' => 'MyAwesomePlugin',
                ],
            ],
        ]);

        config(['fortiplugin.ui.embed.public_base' => '/vendor/fortiplugin/{slug}']);

        $path = $this->resolver->resolvePublicPath($alias);

        $this->assertSame('/vendor/fortiplugin/my-awesome-plugin/build', $path);
    }

    public function test_resolveAssetUrl_returns_correct_url(): void
    {
        $alias = 'my-awesome-plugin';
        $this->writeRegistry([
            'plugins' => [
                $alias => [
                    'plugin_name' => 'MyAwesomePlugin',
                ],
            ],
        ]);

        config(['fortiplugin.ui.embed.public_base' => '/vendor/fortiplugin/{alias}']);
        
        $url = $this->resolver->resolveAssetUrl($alias, 'assets/main.js');
        
        $this->assertStringContainsString('/vendor/fortiplugin/my-awesome-plugin/build/assets/main.js', $url);
    }

    public function test_resolveAssetUrl_with_explicit_origin(): void
    {
        $alias = 'my-awesome-plugin';
        $this->writeRegistry([
            'plugins' => [
                $alias => [
                    'plugin_name' => 'MyAwesomePlugin',
                ],
            ],
        ]);

        config([
            'fortiplugin.ui.embed.public_base' => '/vendor/fortiplugin/{alias}',
            'fortiplugin.ui.embed.asset_origin' => 'https://cdn.example.com'
        ]);

        $url = $this->resolver->resolveAssetUrl($alias, 'assets/main.js');

        $this->assertSame('https://cdn.example.com/vendor/fortiplugin/my-awesome-plugin/build/assets/main.js', $url);
    }
}
