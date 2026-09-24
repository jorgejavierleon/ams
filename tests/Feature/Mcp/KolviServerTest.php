<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses()->group('mcp');

function mcpJsonRpc(string $method, array $params = []): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => $params,
    ];
}

test('an authenticated agent can reach the mcp server with no tools listed yet', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/mcp/kolvi', mcpJsonRpc('tools/list'));

    $response->assertOk();
    $response->assertJsonPath('result.tools', []);
});

test('an unauthenticated request to the mcp server is rejected', function () {
    $response = $this->postJson('/mcp/kolvi', mcpJsonRpc('tools/list'));

    $response->assertUnauthorized();
});
