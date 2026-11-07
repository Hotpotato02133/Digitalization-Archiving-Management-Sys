<?php
declare(strict_types=1);

/**
 * Lightweight, zero-dependency environment loader for DATS.
 * Reads key-value pairs from a root .env file without external packages or Composer,
 * ensuring seamless deployment to any standard PHP host (Alwaysdata, InfinityFree, cPanel, LAMP).
 */

if (!function_exists('dats_load_env')) {
    /**
     * Loads environment variables from a .env file into putenv(), $_ENV, and $_SERVER.
     *
     * @param string|null $filePath Path to .env file (defaults to root .env)
     * @param bool $override Whether to overwrite existing environment variables
     * @return bool True if file was loaded, false otherwise
     */
    function dats_load_env(?string $filePath = null, bool $override = false): bool
    {
        $path = $filePath ?? dirname(__DIR__) . '/.env';
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//') || str_starts_with($line, ';')) {
                continue;
            }

            // Support `export KEY=VALUE` syntax
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            $parsedValue = dats_parse_env_value($value);

            // Populate putenv, $_ENV, and $_SERVER if not already set or override is requested
            $isAlreadySet = (getenv($key) !== false && getenv($key) !== '')
                || (isset($_ENV[$key]) && $_ENV[$key] !== '')
                || (isset($_SERVER[$key]) && $_SERVER[$key] !== '');

            if ($override || !$isAlreadySet) {
                putenv("{$key}={$parsedValue}");
                $_ENV[$key] = $parsedValue;
                $_SERVER[$key] = $parsedValue;
            }
        }

        return true;
    }
}

if (!function_exists('dats_parse_env_value')) {
    /**
     * Parses and strips quotes/inline comments from an environment value string.
     */
    function dats_parse_env_value(string $value): string
    {
        $len = strlen($value);
        if ($len === 0) {
            return '';
        }

        // Double quoted: "..."
        if ($len >= 2 && $value[0] === '"' && $value[$len - 1] === '"') {
            $inner = substr($value, 1, -1);
            return stripcslashes($inner);
        }

        // Single quoted: '...'
        if ($len >= 2 && $value[0] === "'" && $value[$len - 1] === "'") {
            $inner = substr($value, 1, -1);
            return str_replace(["\\'", "\\\\"], ["'", "\\"], $inner);
        }

        // Unquoted: strip trailing inline comments (e.g., VALUE # inline comment)
        if (preg_match('/^(.*?)\s+#.*$/', $value, $matches)) {
            $value = trim($matches[1]);
        }

        return $value;
    }
}

if (!function_exists('dats_env')) {
    /**
     * Safely reads an environment variable with a default fallback.
     */
    function dats_env(string $name, string $default = ''): string
    {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }

        $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }
}

