<?php

declare(strict_types=1);

namespace Pronskiy\Mcp;

use Mcp\Server as SdkServer;
use Mcp\Server\ServerBuilder;
use Mcp\Server\Transport\StdioTransport;
use Psr\Log\NullLogger;

/**
 * Simple MCP Server implementation (using the official mcp/sdk under the hood)
 *
 * This class provides a fluent interface for creating MCP servers.
 * It stores user-defined tools, prompts, and resources, and registers them
 * with the mcp/sdk ServerBuilder when run() is called.
 */
class Server
{
    protected static ?self $instance = null;

    /**
     * Get the singleton instance of the server
     */
    public static function getInstance(string $name = 'mcp-server'): self
    {
        if (self::$instance === null) {
            self::$instance = new self($name);
        }
        return self::$instance;
    }

    public static function addTool(string $name, string $description, callable $callback): self
    {
        return self::getInstance()->tool($name, $description, $callback);
    }

    public static function addPrompt(string $name, string $description, callable $callback): self
    {
        return self::getInstance()->prompt($name, $description, $callback);
    }

    public static function addResource(string $uri, string $name, string $description = '', string $mimeType = 'text/plain', ?callable $callback = null): self
    {
        return self::getInstance()->resource($uri, $name, $description, $mimeType, $callback);
    }

    public static function start(bool $resourcesChanged = true, bool $toolsChanged = true, bool $promptsChanged = true): void
    {
        // Flags kept for API compatibility, but no-op with the new SDK here.
        self::getInstance()->run($resourcesChanged, $toolsChanged, $promptsChanged);
    }

    private string $name;

    /** @var array<int, array{name:string, description:string, callback:callable}> */
    private array $tools = [];

    /** @var array<int, array{name:string, description:string, callback:callable}> */
    private array $prompts = [];

    /** @var array<int, array{uri:string, name:string, description:string, mimeType:string, callback:?callable}> */
    private array $resources = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Define a new tool
     */
    public function tool(string $name, string $description, callable $callback): self
    {
        $this->tools[] = compact('name', 'description', 'callback');
        return $this;
    }

    /**
     * Define a new prompt
     */
    public function prompt(string $name, string $description, callable $callback): self
    {
        $this->prompts[] = compact('name', 'description', 'callback');
        return $this;
    }

    /**
     * Define a new resource
     */
    public function resource(string $uri, string $name, string $description = '', string $mimeType = 'text/plain', ?callable $callback = null): self
    {
        $this->resources[] = compact('uri', 'name', 'description', 'mimeType', 'callback');
        return $this;
    }

    /**
     * Run the server using mcp/sdk's ServerBuilder and StdioTransport
     */
    public function run(bool $resourcesChanged = true, bool $toolsChanged = true, bool $promptsChanged = true): void
    {
        // Build with official SDK
        /** @var ServerBuilder $builder */
        $builder = SdkServer::make()
            ->withServerInfo($this->name, '0.1.1') // @TODO: keep version in sync with package when possible
            ->withLogger(new NullLogger());

        // Register tools
        foreach ($this->tools as $tool) {
            $builder->withTool($tool['callback'], $tool['name'], $tool['description']);
        }

        // Register prompts
        foreach ($this->prompts as $prompt) {
            $builder->withPrompt($prompt['callback'], $prompt['name'], $prompt['description']);
        }

        // Register resources
        foreach ($this->resources as $res) {
            $builder->withResource($res['callback'] ?? fn() => null, $res['uri'], $res['name'], $res['description'], $res['mimeType']);
        }

        $server = $builder->build();

        // Connect to stdio transport (JSON-RPC over STDIN/STDOUT)
        $server->connect(new StdioTransport());
    }
}
