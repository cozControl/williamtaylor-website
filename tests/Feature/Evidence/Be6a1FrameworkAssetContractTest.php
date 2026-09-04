<?php

namespace Tests\Feature\Evidence;

use Illuminate\Http\Response as IlluminateResponse;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

require_once dirname(__DIR__, 3).'/scripts/evidence/be6a1-framework-asset-contract.php';

class Be6a1FrameworkAssetContractTest extends TestCase
{
    #[DataProvider('fluxAssetProvider')]
    public function test_flux_routes_are_exact_and_framework_owned(string $path, string $kind): void
    {
        $this->assertSame($kind, be6a1FrameworkAssetCandidateKind($path));
        $this->assertSame($kind, be6a1ApprovedFrameworkAssetKind($path));
    }

    public function test_flux_javascript_route_and_manifest_query_return_javascript(): void
    {
        $manifest = json_decode(
            file_get_contents(base_path('vendor/livewire/flux/dist/manifest.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $response = $this->get('/flux/flux.js?id='.urlencode($manifest['/flux.js']));

        $response->assertOk();
        $this->assertTrue(
            be6a1FrameworkAssetResponseIsValid($response->baseResponse, 'javascript'),
        );
    }

    public function test_generated_livewire_javascript_route_and_manifest_query_return_javascript(): void
    {
        $path = EndpointResolver::scriptPath(minified: ! config('app.debug'));
        $manifest = json_decode(
            file_get_contents(base_path('vendor/livewire/livewire/dist/manifest.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $response = $this->get($path.'?id='.urlencode($manifest['/livewire.js']));

        $response->assertOk();
        $this->assertSame('javascript', be6a1ApprovedFrameworkAssetKind($path));
        $this->assertTrue(
            be6a1FrameworkAssetResponseIsValid($response->baseResponse, 'javascript'),
        );
    }

    #[DataProvider('rejectedPathProvider')]
    public function test_misspelled_unrelated_and_external_paths_are_not_candidates(string $path): void
    {
        $this->assertNull(be6a1FrameworkAssetCandidateKind($path));
    }

    public function test_a_different_livewire_installation_hash_is_rejected(): void
    {
        $prefix = EndpointResolver::prefix();
        $differentHash = str_ends_with($prefix, 'deadbeef') ? 'feedface' : 'deadbeef';
        $path = '/livewire-'.$differentHash.'/livewire.js';

        $this->assertSame('javascript', be6a1FrameworkAssetCandidateKind($path));
        $this->assertNull(be6a1ApprovedFrameworkAssetKind($path));
    }

    public function test_component_asset_shapes_are_anchored_to_the_effective_livewire_prefix(): void
    {
        $prefix = EndpointResolver::prefix();

        $this->assertSame(
            'javascript',
            be6a1ApprovedFrameworkAssetKind($prefix.'/js/admin-dashboard.js'),
        );
        $this->assertSame(
            'css',
            be6a1ApprovedFrameworkAssetKind($prefix.'/css/admin-dashboard.css'),
        );
        $this->assertSame(
            'css',
            be6a1ApprovedFrameworkAssetKind($prefix.'/css/admin-dashboard.global.css'),
        );
        $this->assertNull(
            be6a1FrameworkAssetCandidateKind($prefix.'/js/nested/admin-dashboard.js'),
        );
    }

    #[DataProvider('invalidResponseProvider')]
    public function test_javascript_response_contract_rejects_fallbacks(
        Response $response,
    ): void {
        $this->assertFalse(
            be6a1FrameworkAssetResponseIsValid($response, 'javascript'),
        );
    }

    public function test_not_modified_framework_asset_response_is_valid_without_a_body(): void
    {
        $this->assertTrue(
            be6a1FrameworkAssetResponseIsValid(
                new Response('', Response::HTTP_NOT_MODIFIED),
                'javascript',
            ),
        );
    }

    public static function fluxAssetProvider(): array
    {
        return [
            'Flux debug JavaScript' => ['/flux/flux.js', 'javascript'],
            'Flux minified JavaScript' => ['/flux/flux.min.js', 'javascript'],
            'Flux editor JavaScript' => ['/flux/editor.js', 'javascript'],
            'Flux minified editor JavaScript' => ['/flux/editor.min.js', 'javascript'],
            'Flux editor CSS' => ['/flux/editor.css', 'css'],
        ];
    }

    public static function rejectedPathProvider(): array
    {
        return [
            'misspelled Flux' => ['/flux/flx.js'],
            'unrelated Flux prefix' => ['/flux/arbitrary.js'],
            'nested Flux path' => ['/flux/nested/flux.js'],
            'ordinary missing JavaScript' => ['/js/be6a1-missing.js'],
            'ordinary missing module' => ['/js/be6a1-missing.mjs'],
            'ordinary missing CSS' => ['/css/be6a1-missing.css'],
            'external URL' => ['https://example.com/flux/flux.js'],
        ];
    }

    public static function invalidResponseProvider(): array
    {
        return [
            'HTML fallback' => [
                new IlluminateResponse(
                    '<!doctype html><html><body>fallback</body></html>',
                    200,
                    ['Content-Type' => 'application/javascript'],
                ),
            ],
            'plain text' => [
                new IlluminateResponse(
                    'console.log("wrong MIME");',
                    200,
                    ['Content-Type' => 'text/plain'],
                ),
            ],
            'error page' => [
                new IlluminateResponse(
                    'Internal Server Error',
                    200,
                    ['Content-Type' => 'application/javascript'],
                ),
            ],
            'empty JavaScript' => [
                new IlluminateResponse(
                    '',
                    200,
                    ['Content-Type' => 'application/javascript'],
                ),
            ],
            '404 JavaScript' => [
                new IlluminateResponse(
                    'console.log("not successful");',
                    404,
                    ['Content-Type' => 'application/javascript'],
                ),
            ],
        ];
    }
}
