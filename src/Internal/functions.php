<?php declare(strict_types=1);

namespace Amp\Internal;

/**
 * Formats a stacktrace obtained via `debug_backtrace()`.
 *
 * @param list<array{
 *     args?:list<mixed>,
 *     class?: class-string,
 *     file?: string,
 *     function: string,
 *     line?: int,
 *     object?: object,
 *     type?: string,
 * }> $trace Output of `debug_backtrace()`.
 *
 * @return string Formatted stacktrace.
 *
 * @codeCoverageIgnore
 * @internal
 *
 * @psalm-pure
 */
function formatStacktrace(array $trace): string
{
    return \implode("\n", \array_map(static function (array $e, int $i): string {
        $line = "#{$i} ";

        if (isset($e["file"], $e['line'])) {
            $line .= "{$e['file']}:{$e['line']} ";
        }

        if (isset($e["class"], $e["type"])) {
            $line .= $e["class"] . $e["type"];
        }

        return $line . $e["function"] . "()";
    }, $trace, \array_keys($trace)));
}

/**
 * Captures a debug backtrace when assertions are enabled.
 *
 * `assert()` does not evaluate its argument unless zend.assertions is 1, so assigning inside an assertion leaves
 * the trace unset when assertions are disabled. Capture it directly instead. The frame of this function is removed
 * so the first frame is the caller, matching a trace taken in the caller.
 *
 * @return list<array{
 *     args?: list<mixed>,
 *     class?: class-string,
 *     file?: string,
 *     function: string,
 *     line?: int,
 *     object?: object,
 *     type?: string,
 * }>|null
 *
 * @internal
 */
function captureDebugTrace(): ?array
{
    if (\ini_get('zend.assertions') !== '1') {
        return null;
    }

    $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
    \array_shift($trace);

    return $trace;
}

/**
 * Returns the next alphanumeric id (`a`, `b`, …, `z`, `aa`), the same sequence as `++` on a non-numeric string.
 *
 * PHP 8.3 and newer use {@see \str_increment()}. Older versions do not have that function.
 *
 * @psalm-pure
 * @internal
 */
function incrementId(string $id): string
{
    if (\PHP_VERSION_ID >= 80300) {
        return \str_increment($id);
    }

    $characters = \str_split($id);
    for ($i = \count($characters) - 1; $i >= 0; $i--) {
        $character = $characters[$i];
        if ($character === '9' || $character === 'Z' || $character === 'z') {
            $characters[$i] = match ($character) {
                '9' => '0',
                'Z' => 'A',
                default => 'a',
            };
            continue;
        }

        $characters[$i] = \chr(\ord($character) + 1);

        return \implode('', $characters);
    }

    $prefix = match ($characters[0] ?? '') {
        '0' => '1',
        'A' => 'A',
        default => 'a',
    };

    return $prefix . \implode('', $characters);
}

/**
 * @return bool True if the environment variable AMP_DEBUG is set to a value other than "0", "false", or "off" or
 *      if the constant AMP_DEBUG is defined and set to a truthy value.
 * @internal
 */
function isDebugEnabled(): bool
{
    $value = \getenv("AMP_DEBUG");
    if ($value === false || $value === "") {
        $value = "0";
    }

    return match ($value) {
        "0", "false", "off" => false,
        default => true,
    } || (\defined("AMP_DEBUG") && \AMP_DEBUG);
}
