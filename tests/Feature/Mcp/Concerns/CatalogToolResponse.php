<?php

namespace Tests\Feature\Mcp\Concerns;

use Closure;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse as HttpTestResponse;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mirrors Laravel\Mcp\Server\Testing\TestResponse's assertions, but reads a
 * single tool's result out of an execute_tools envelope instead of a
 * top-level tools/call response, since KolviServer's tools all live behind
 * the ToolSearch catalog and are no longer directly callable.
 *
 * @see TestResponse
 */
class CatalogToolResponse
{
    /**
     * @var array<string, mixed>
     */
    protected array $result;

    public function __construct(HttpTestResponse $response)
    {
        // execute_tools can emit progress notifications, so Laravel MCP's
        // web transport answers tools/call with an SSE stream instead of a
        // single JSON body in that case; plain JSON responses (tools/list,
        // search_tools) never hit this branch.
        $rpc = $response->baseResponse instanceof StreamedResponse
            ? $this->finalEventFrom($response->streamedContent())
            : (array) $response->json();

        $text = $rpc['result']['content'][0]['text'] ?? '{}';

        /** @var array<string, mixed> $envelope */
        $envelope = json_decode($text, true) ?? [];

        $this->result = $envelope['results'][0] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function finalEventFrom(string $streamed): array
    {
        $final = [];

        foreach (explode("\n\n", $streamed) as $chunk) {
            $chunk = trim($chunk);

            if (! str_starts_with($chunk, 'data:')) {
                continue;
            }

            $decoded = json_decode(trim(substr($chunk, strlen('data:'))), true);

            // Notifications carry no "id"; the final JSON-RPC response does.
            if (is_array($decoded) && array_key_exists('id', $decoded)) {
                $final = $decoded;
            }
        }

        return $final;
    }

    public function assertOk(): static
    {
        Assert::assertSame([], $this->errors(), 'The response has errors.');

        return $this;
    }

    /**
     * @param  array<string>  $messages
     */
    public function assertHasErrors(array $messages = []): static
    {
        $errors = $this->errors();

        Assert::assertNotEmpty($errors, 'The response has no errors.');

        foreach ($messages as $message) {
            foreach ($errors as $error) {
                if (str_contains($error, $message)) {
                    continue 2;
                }
            }

            Assert::fail("The expected error message [{$message}] was not found in the response.");
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure(AssertableJson): mixed  $structuredContent
     */
    public function assertStructuredContent(Closure|array $structuredContent): static
    {
        $actual = $this->result['structuredContent'] ?? null;

        Assert::assertIsArray($actual, 'The response does not contain any structured content.');

        if ($structuredContent instanceof Closure) {
            $assertableJson = AssertableJson::fromArray($actual);

            $structuredContent($assertableJson);

            $assertableJson->interacted();

            return $this;
        }

        Assert::assertSame($structuredContent, $actual, 'The expected structured content does not match the actual structured content.');

        return $this;
    }

    /**
     * @param  array<string>|string  $text
     */
    public function assertSee(array|string $text): static
    {
        $seeable = collect([...$this->content(), ...$this->errors()])->filter()->unique()->values()->all();

        foreach (is_array($text) ? $text : [$text] as $segment) {
            foreach ($seeable as $message) {
                if (str_contains($message, $segment)) {
                    continue 2;
                }
            }

            Assert::assertTrue(false, "The expected text [{$segment}] was not found in the response content.");
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    protected function content(): array
    {
        return collect($this->result['content'] ?? [])
            ->map(fn (array $message): string => $message['text'] ?? $message['data'] ?? '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function errors(): array
    {
        return ($this->result['isError'] ?? false) ? $this->content() : [];
    }
}
