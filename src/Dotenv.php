<?php

declare(strict_types=1);

namespace G\Dotenv;

use RuntimeException;

class Dotenv implements DotenvInterface
{
    /**
     * @var array<string,string>
     */
    protected array $data = [];

    /**
     * @var array<string,null>
     */
    protected array $dataUnset = [];

    /**
     * @var array<string,mixed>
     */
    protected array $options = [];

    protected const DOUBLE_QUOTE = '"';
    protected const SINGLE_QUOTE = "'";
    protected const TRIPLE_QUOTE_MIN_LEN = 6;
    protected const QUOTED_MIN_LEN = 2;

    protected const REGEX_KEY = '[a-zA-Z_][\w]*';
    protected const REGEX_EXTRACT = '/^\s*(?:export[^\S\r\n]+)?' // optional export keyword
        . '(?P<key>[a-zA-Z_][\w]*)' // key
        . '[^\S\r\n]*=[^\S\r\n]*' // equal sign
        . '(?P<value>(\'\'\'|""")?(?(3)[\s\S]*?(\3)|(\'|")?(?(5).*?(\5)|.*?)))' // value
        . '[^\S\r\n]*(?:(?<=[\s=\'"])#.*)?$/mi'; // comment: a # at the start of the value or after a space or a quote

    /**
     * Permit to use putenv()
     */
    public const OPT_UNSAFE = 'unsafe';

    /**
     * Enable cascading file loading (load and merge multiple files)
     * or stop after the first file found
     */
    public const OPT_CASCADE_FILES = 'cascade_files';

    /**
     * Throw exception when file is not found
     */
    public const OPT_STRICT_FILES = 'strict_files';

    public const DEFAULT_ENV_FILES = ['.env', '.env.local', '.env.${APP_ENV}', '.env.${APP_ENV}.local'];

    /**
     * @param array<string,mixed> $options Options:
     * - OPT_UNSAFE: bool _(false)_ - Permit to use putenv() - _(Not recommended)_
     * - OPT_CASCADE_FILES: bool _(true)_ - Enable cascading file loading, or stop after first file found
     * - OPT_STRICT_FILES: bool _(false)_ - Throw exception when file is not found
     * @return void
     */
    public function __construct(
        array $options = []
    ) {
        $defaultOptions = [
            self::OPT_UNSAFE => false,
            self::OPT_CASCADE_FILES => true,
            self::OPT_STRICT_FILES => false,
        ];
        $this->options = array_merge($defaultOptions, array_intersect_key($options, $defaultOptions));
    }

    public function load(string ...$string): DotenvInterface
    {
        $dotenv = clone $this;

        foreach ($string as $s) {
            $s = preg_replace_callback(
                '/\$\{(' . self::REGEX_KEY . ')}/',
                static function (array $matches) use ($dotenv): string {
                    $env = getenv($matches[1]);
                    if ($env !== false) {
                        return $env;
                    }

                    $superGlobal = $_ENV[$matches[1]] ?? null;

                    return is_string($superGlobal) ? $superGlobal : $dotenv->get($matches[1], $matches[0]);
                },
                $s
            ) ?? $s;

            if ($dotenv->isPotentialFilename($s)) {
                $realpath = realpath($s);
                if ($realpath !== false && is_readable($realpath)) {
                    // Load the found file
                    $content = file_get_contents($realpath);

                    if ($content === false) {
                        throw new RuntimeException('File not readable: ' . $realpath);
                    }

                    if ($content !== '') {
                        $dotenv->parse($content);
                    }

                    // If not cascading, return after first file
                    if (!$dotenv->options[self::OPT_CASCADE_FILES]) {
                        break;
                    }
                    continue;
                } else {
                    // GLOB_BRACE does not exist on every platform (e.g. Alpine Linux)
                    $paths = glob($s) ?: (defined('GLOB_BRACE') ? glob($s, GLOB_BRACE) : []);

                    if ($paths) {
                        $paths = array_filter($paths, fn($p) => is_file($p) && is_readable($p));
                    }

                    if ($paths) {
                        /** @var self */
                        $dotenv = $dotenv->load(...$paths);

                        if (!$dotenv->options[self::OPT_CASCADE_FILES]) {
                            break;
                        }
                        continue;
                    } elseif ($dotenv->options[self::OPT_STRICT_FILES]) {
                        throw new RuntimeException('File not found: ' . $s);
                    }
                }
            }

            if (strpos($s, '=') !== false) {
                $dotenv->parse($s);
            }
        }

        return $dotenv;
    }

    protected function stripQuotes(string $value): string
    {
        // Triple-quoted multiline block: strip the 3-char delimiters and surrounding newlines
        $tripleDouble = str_repeat(self::DOUBLE_QUOTE, 3);
        $tripleSingle = str_repeat(self::SINGLE_QUOTE, 3);
        if (
            strlen($value) >= self::TRIPLE_QUOTE_MIN_LEN &&
            (
                str_starts_with($value, $tripleDouble) ||
                str_starts_with($value, $tripleSingle)
            )
        ) {
            return trim(substr($value, 3, -3), "\n\r");
        }
        // Single or double-quoted: strip exactly the outer pair of matching quotes
        if (
            strlen($value) >= self::QUOTED_MIN_LEN && ($value[0] === self::DOUBLE_QUOTE ||
                $value[0] === self::SINGLE_QUOTE) && $value[-1] === $value[0]
        ) {
            return substr($value, 1, -1);
        }
        // Unquoted: remove leading/trailing control characters
        return trim($value, "\n\r\x00");
    }

    protected function isPotentialFilename(string $path): bool
    {
        // Strings with chars invalid in filenames or with newlines are not filenames
        if (preg_match('/[<>"|&\n]/', $path)) {
            return false;
        }
        // Strings matching KEY=VALUE pattern at the start are inline env content, not file paths
        if (preg_match('/^\s*(?:export\s+)?[a-zA-Z_][\w]*\s*=/', $path)) {
            return false;
        }
        return true;
    }

    /**
     * @param string $content
     *
     * @return Dotenv
     */
    protected function parse(string $content): self
    {
        preg_match_all(
            self::REGEX_EXTRACT,
            str_replace(["\r\n", "\r"], "\n", $content), // files saved with Windows or old Mac line endings
            $matches
        );

        for ($i = 0, $matchesCount = count($matches[0]); $i < $matchesCount; $i++) {
            $this->setValue(
                $matches['key'][$i],
                $this->stripQuotes($matches['value'][$i])
            );
        }

        // TODO: Optimize for large files
        // $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        // Maybe using yield ?
        // foreach ($lines as $line) { }

        return $this;
    }


    public function getValues(): array
    {
        return $this->data;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function withValue(string $key, string $value): DotenvInterface
    {
        $dotenv = clone $this;
        $dotenv->setValue($key, $value);
        return $dotenv;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return Dotenv
     * @throws RuntimeException
     */
    protected function setValue(string $key, string $value): self
    {
        if (!preg_match('/^' . self::REGEX_KEY . '$/', $key)) {
            throw new RuntimeException('Invalid key: ' . $key);
        }

        unset($this->dataUnset[$key]);
        $this->data[$key] = $value;
        return $this;
    }

    public function withoutValue(string $key): DotenvInterface
    {
        $dotenv = clone $this;
        $dotenv->unsetValue($key);
        return $dotenv;
    }

    /**
     * @param string $key
     *
     * @return Dotenv
     */
    protected function unsetValue(string $key): self
    {
        unset($this->data[$key]);
        $this->dataUnset[$key] = null;
        return $this;
    }

    public function store(): bool
    {
        foreach ($this->dataUnset as $key => $value) {
            $this->unsetEnv($key);
        }
        $this->dataUnset = [];

        foreach ($this->data as $key => $value) {
            $this->putEnv($key, $value);
        }

        return true;
    }

    /**
     * @param string $key
     *
     * @return void
     */
    protected function unsetEnv(string $key): void
    {
        $key = trim($key);
        unset($_ENV[$key]);
        if ($this->options[self::OPT_UNSAFE]) {
            putenv($key);
        }
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return void
     */
    protected function putEnv(string $key, string $value): void
    {
        $key = trim($key);
        $_ENV[$key] = $value;
        if ($this->options[self::OPT_UNSAFE]) {
            putenv("$key=$value");
        }
    }
}
