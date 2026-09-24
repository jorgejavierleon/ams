<?php

use App\Mcp\Tools\AuthorizedTool;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response;

uses()->group('mcp');

function probeTool(): AuthorizedTool
{
    return new class extends AuthorizedTool
    {
        public function handle(McpRequest $request): Response
        {
            if ($response = $this->authorize($request, 'probe-ability')) {
                return $response;
            }

            return Response::text('ok');
        }
    };
}

beforeEach(function () {
    Gate::define('probe-ability', fn (User $user): bool => $user->name === 'Allowed');
});

test('authorize lets the tool proceed when the user has the ability', function () {
    $this->actingAs(User::factory()->create(['name' => 'Allowed']));

    $response = probeTool()->handle(new McpRequest);

    expect($response->isError())->toBeFalse();
});

test('authorize returns an error response when the user lacks the ability', function () {
    $this->actingAs(User::factory()->create(['name' => 'Denied']));

    $response = probeTool()->handle(new McpRequest);

    expect($response->isError())->toBeTrue();
});
