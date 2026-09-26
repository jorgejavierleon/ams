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

test('an authenticated agent can list the registered leave tools', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/mcp/kolvi', mcpJsonRpc('tools/list'));

    $response->assertOk();

    $names = collect($response->json('result.tools'))->pluck('name');

    expect($names)->toContain(
        'create-leave',
        'view-own-leaves',
        'cancel-leave',
        'view-team-leaves',
        'approve-leave',
        'reject-leave',
        'create-leave-for-employee',
        'create-overtime-request',
        'view-own-overtime-requests',
        'view-team-overtime-requests',
        'approve-overtime-request',
        'reject-overtime-request',
    );
});

test('an unauthenticated request to the mcp server is rejected', function () {
    $response = $this->postJson('/mcp/kolvi', mcpJsonRpc('tools/list'));

    $response->assertUnauthorized();
});
