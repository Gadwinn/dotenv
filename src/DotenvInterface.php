<?php

declare(strict_types=1);

namespace G\Dotenv;

interface DotenvInterface
{
    /**
     * Load environment variables from a string or file.
     *
     * @param string ...$string The environment content or file path. Can be either:
     * - A string containing environment variables in the format `KEY=VALUE`
     * - A file path to a `.env` file or similar, which will be read
     *  and parsed to extract environment variables.
     * * If multiple strings are provided, they will be processed in order,
     *  allowing for cascading file loading or merging of multiple environment
     *  variable definitions.
     *
     * @return DotenvInterface
     * @throws \RuntimeException
     */
    public function load(string ...$string): DotenvInterface;

    /**
     * Get all environment variables as an associative array.
     *
     * @return array<string,string>
     */
    public function getValues(): array;

    /**
     * Get a specific environment variable by key.
     *
     * @param string $key Key name
     * @param string $default Default value if key not found
     *
     * @return string
     */
    public function get(string $key, string $default = ''): string;

    /**
     * Check if an environment variable exists.
     *
     * @param string $key Key name
     *
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Set or update an environment variable with a new value.
     *
     * @param string $key Key name
     * @param string $value Value
     *
     * @return DotenvInterface
     * @throws \RuntimeException
     */
    public function withValue(string $key, string $value): DotenvInterface;

    /**
     * Remove an environment variable by key.
     *
     * @param string $key Key name
     *
     * @return DotenvInterface
     */
    public function withoutValue(string $key): DotenvInterface;

    /**
     * Persist environment variables.
     *
     * @return bool
     */
    public function store(): bool;
}
