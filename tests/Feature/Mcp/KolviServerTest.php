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

test('an authenticated agent sees only the searchable tool catalog, not individual tools', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/mcp/kolvi', mcpJsonRpc('tools/list'));

    $response->assertOk();

    $names = collect($response->json('result.tools'))->pluck('name');

    expect($names)->toEqual(collect(['search_tools', 'execute_tools']));
});

test('an authenticated agent can find every catalog tool through search_tools', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/mcp/kolvi', mcpJsonRpc('tools/call', [
        'name' => 'search_tools',
        'arguments' => ['query' => '', 'limit' => 50],
    ]));

    $response->assertOk();

    $found = collect(json_decode($response->json('result.content.0.text'), true)['tools'])->pluck('name');

    expect($found)->toContain(
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
        'get-payroll-summary-report',
        'create-document-template',
        'update-document-template',
        'delete-document-template',
        'list-document-templates',
        'generate-document',
    );
});

test('an unauthenticated request to the mcp server is rejected', function () {
    $response = $this->postJson('/mcp/kolvi', mcpJsonRpc('tools/list'));

    $response->assertUnauthorized();
});
