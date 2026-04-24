<?php
namespace Irbis\Http;
use IteratorAggregate;
use ArrayAccess;
use Countable;
use Traversable;
use InvalidArgumentException;
use ArrayIterator;


final class Headers implements ArrayAccess, IteratorAggregate, Countable 
{
    private array $headers = [];

    public static function fromGlobals(): self 
    {
        $headers = getallheaders();
        return new self($headers);
    }

    public function __construct(array $headers = []) 
    {
        foreach ($headers as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function content(string $search_content): ?string 
    {
        $content_type = $this->get('Content-Type');
        if ($content_type && str_contains($content_type, $search_content)) {
            return true;
        }
        return false;
    }

    public function accept(string $search_content): bool 
    {
        $accept = $this->get('Accept');
        if ($accept == '*/*' || !$accept) {
            return true;
        }

        if (!str_contains($accept, $search_content)) {
            return false;
        }
        return true;
    }

    public function bearer(): ?string 
    {
        $auth = $this->get('Authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function set(string $key, string $value): void 
    {
        $this->validateKey($key);
        $this->headers[$this->normalizeKey($key)] = $value;
    }

    public function get(string $key): ?string {
        return $this->headers[$this->normalizeKey($key)] ?? null;
    }

    public function has(string $key): bool 
    {
        return array_key_exists(
            $this->normalizeKey($key),
            $this->headers
        );
    }

    public function remove(string $key): void 
    {
        unset($this->headers[$this->normalizeKey($key)]);
    }

    private function validateKey($key): void 
    {
        if (!is_string($key) || $key === '') {
            throw new InvalidArgumentException("Header key must be a non-empty string");
        }
    }

    private function normalizeKey(string $key): string 
    {
        return strtolower($key);
    }

    // ArrayAccess

    public function offsetSet($offset, $value): void 
    {
        $this->validateKey($offset);
        $this->set($offset, $value);
    }

    public function offsetGet($offset): ?string 
    {
        return $this->get($offset);
    }

    public function offsetExists($offset): bool 
    {
        return $this->has($offset);
    }

    public function offsetUnset($offset): void 
    {
        $this->remove($offset);
    }

    // IteratorAggregate

    public function getIterator(): Traversable 
    {
        return new ArrayIterator($this->headers);
    }

    // Countable

    public function count(): int 
    {
        return count($this->headers);
    }
}