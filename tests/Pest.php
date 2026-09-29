<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Tool;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Mcp\Concerns\CatalogToolResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Calls a KolviServer tool through the execute_tools catalog endpoint (see
 * app/Mcp/Servers/KolviServer.php), since every tool there lives behind a
 * ToolSearch catalog and is no longer directly reachable via tools/call.
 *
 * @param  class-string<Tool>  $toolClass
 * @param  array<string, mixed>  $arguments
 */
function mcpTool(Authenticatable $user, string $toolClass, array $arguments = []): CatalogToolResponse
{
    Sanctum::actingAs($user);

    $name = app($toolClass)->name();

    $response = test()->postJson('/mcp/kolvi', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'execute_tools',
            'arguments' => [
                'calls' => [
                    ['name' => $name, 'arguments' => $arguments],
                ],
            ],
        ],
    ]);

    return new CatalogToolResponse($response);
}
