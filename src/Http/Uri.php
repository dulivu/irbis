<?php
namespace Irbis\Http;


class Uri 
{
    public readonly string $scheme;
    public readonly string $host;
    public readonly ?int $port;
    public readonly string $path;
    public array $query;
    public ?string $fragment;

    public function __construct(
        string $scheme,
        string $host,
        ?int $port,
        string $path,
        array $query = [],
        ?string $fragment = null
    ) {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
        $this->query = $query;
        $this->fragment = $fragment;
    }

    public function __toString(): string 
    {
        $query = $this->query ? '?' . http_build_query($this->query) : '';
        $port = $this->port ? ':' . $this->port : '';

        return "{$this->scheme}://{$this->host}{$port}{$this->path}{$query}";
    }

    public function origin(): string 
    {
        $port = $this->port ? ':' . $this->port : '';
        return "{$this->scheme}://{$this->host}{$port}";
    }

    public static function fromString(string $url): static 
    {
        $parts = parse_url($url);

        return new static(
            $parts['scheme'] ?? 'http',
            $parts['host'] ?? '',
            $parts['port'] ?? null,
            $parts['path'] ?? '/',
            isset($parts['query']) ? self::parseQuery($parts['query']) : [],
            $parts['fragment'] ?? null
        );
    }

    protected static function parseQuery(string $query): array 
    {
        parse_str($query, $output);
        return $output;
    }

    public static function fromGlobals(): static 
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
        return self::fromString($uri);
    }
}