<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Core\Security;

use RuntimeException;

/**
 * TokenUsageAnalyzer
 *
 * Analyzes a PHP file for direct (and lightly-obfuscated) usage of forbidden tokens
 * (e.g., eval, exec, shell_exec). Designed to be used as the callback with FileScanner.
 *
 * Detection strategy (fast and with low false positives):
 *  1) Uses token_get_all() to walk PHP tokens and capture T_STRING calls that match
 *     forbidden function names, only when followed by "(" (i.e., actual calls).
 *  2) Detects simple string-concatenation obfuscation of function names, e.g. "ev"."al"(
 *     by concatenating adjacent string literals directly before "(" and comparing.
 *  3) Flags backtick command execution (`...`) by scanning raw code segments (rare in tokenizer).
 *
 * Not a full parser; intentionally lightweight. This should be paired with your AST scanner
 * for deep analysis.
 */
final class TokenUsageAnalyzer
{
    /**
     * Scan a file and return a list of issues for direct/obfuscated token usage.
     *
     * @param  string        $filePath         Absolute path to the PHP file.
     * @param  array<string> $forbiddenTokens  Lowercase function names to flag (e.g., ['eval','exec','shell_exec'])
     * @return array<int,array{
     *     type: string,
     *     token: string,
     *     file: string,
     *     line: int,
     *     snippet: string,
     *     issue: string
     * }>
     * @noinspection ForeachInvariantsInspection
     */
    public static function analyzeFile(string $filePath, array $forbiddenTokens): array
    {
        // Normalize to lowercase set for cheap lookup
        $forbidden = [];
        foreach ($forbiddenTokens as $t) {
            $forbidden[strtolower($t)] = true;
        }

        $code = @file_get_contents($filePath);
        if ($code === false) {
            throw new RuntimeException("Unable to read file: $filePath");
        }

        $lines = preg_split('/\R/u', $code) ?: [];

        // Quick check for backticks: flag any line with non-escaped backtick outside string contexts.
        // (Tokenizer doesn’t give a special token for backticks; this heuristic is acceptable here.)
        $issues = self::scanBackticks($filePath, $lines);

        // Tokenize once
        $tokens = @token_get_all($code, TOKEN_PARSE);

        // Walk tokens and detect:
        //  A) T_STRING function calls that are forbidden and followed by '('
        //  B) String-concatenated function names immediately followed by '(' (e.g., "ev"."al"()
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $tk = $tokens[$i];

            // A) Direct function call: T_STRING '('
            if (is_array($tk) && $tk[0] === T_STRING) {
                $name = strtolower($tk[1]);

                if (isset($forbidden[$name])) {
                    $next = self::skipWhitespaceAndComments($tokens, $i + 1, $count);
                    if ($next < $count && $tokens[$next] === '(') {
                        /** @noinspection PhpConditionAlreadyCheckedInspection */
                        $line = is_array($tk) ? $tk[2] : self::safeLineGuess($lines);
                        $issues[] = self::issueRow($name, $filePath, $line, $lines, 'Direct usage of invalid token');
                    }
                }

                continue;
            }

            // B) Obfuscated via concatenated strings right before '(':
            //    e.g., "ev" . "al" ( ... )
            // Collect a run of [string-literal] (dot [string-literal])* immediately followed by '('
            if (self::isStringLiteral($tk)) {
                [$assembled, $line, $nextIndex] = self::assembleConcatenatedString($tokens, $i, $count);
                if ($assembled !== '') {
                    // Check if immediately followed by '('
                    $after = self::skipWhitespaceAndComments($tokens, $nextIndex, $count);
                    if ($after < $count && $tokens[$after] === '(') {
                        $lower = strtolower($assembled);
                        if (isset($forbidden[$lower])) {
                            $issues[] = self::issueRow($lower, $filePath, $line, $lines, 'Obfuscated usage of invalid token');
                        }
                    }
                }
                // Move the cursor to the end of the processed segment
                $i = max($i, ($nextIndex - 1));
            }
        }

        return $issues;
    }

    /**
     * Create an issue row in the exact structure requested.
     *
     * @param  string        $token
     * @param  string        $file
     * @param  int           $lineNumber
     * @param  array<int,string> $lines
     * @param  string        $message
     * @return array<string,mixed>
     */
    private static function issueRow(string $token, string $file, int $lineNumber, array $lines, string $message): array
    {
        $snippet = isset($lines[$lineNumber - 1]) ? trim($lines[$lineNumber - 1]) : '';
        return [
            'type'    => 'invalid_token_usage',
            'token'   => $token,
            'file'    => $file,
            'line'    => $lineNumber,
            'snippet' => $snippet,
            'issue'   => $message,
        ];
    }

    /**
     * Skip whitespace/comments and return the index of the next significant token.
     */
    private static function skipWhitespaceAndComments(array $tokens, int $i, int $count): int
    {
        for (; $i < $count; $i++) {
            $t = $tokens[$i];
            if (is_string($t)) {
                // Single-char tokens like '(' or ')'
                break;
            }
            $id = $t[0];
            if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                break;
            }
        }
        return $i;
    }

    /**
     * True if token is a PHP string literal (single or double quoted).
     */
    private static function isStringLiteral($token): bool
    {
        return is_array($token) && ($token[0] === T_CONSTANT_ENCAPSED_STRING);
    }

    /**
     * Assemble a concatenated string sequence starting at $i:
     *   T_CONSTANT_ENCAPSED_STRING ( (T_WHITESPACE|T_COMMENT|'.') T_CONSTANT_ENCAPSED_STRING )*
     * Returns [assembledString, lineNumberOfFirstLiteral, nextIndexAfterSequence].
     *
     * Only concatenates adjacent literals, ignoring whitespace/comments and literal '.' operators.
     */
    private static function assembleConcatenatedString(array $tokens, int $i, int $count): array
    {
        $assembled = '';
        $line = is_array($tokens[$i]) ? $tokens[$i][2] : 1;
        $idx = $i;

        // First literal
        if (!self::isStringLiteral($tokens[$idx])) {
            return ['', $line, $i];
        }
        $assembled .= self::unquoteLiteral($tokens[$idx][1]);
        $idx++;

        // Zero or more: (whitespace/comment | '.') + literal
        while ($idx < $count) {
            $t = $tokens[$idx];

            // Skip whitespace or comments between pieces
            if (is_array($t) && ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
                $idx++;
                continue;
            }

            // Require '.' operator to continue, otherwise stop
            if ($t !== '.') {
                break;
            }
            $idx++;

            // Skip whitespace/comments after '.'
            while ($idx < $count && is_array($tokens[$idx]) && in_array($tokens[$idx][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $idx++;
            }

            // Next must be a literal
            if ($idx >= $count || !self::isStringLiteral($tokens[$idx])) {
                break;
            }

            $assembled .= self::unquoteLiteral($tokens[$idx][1]);
            $idx++;
        }

        return [$assembled, $line, $idx];
    }

    /**
     * Remove surrounding quotes from a PHP literal and unescape simple escapes.
     * Handles both single- and double-quoted literals conservatively.
     */
    private static function unquoteLiteral(string $literal): string
    {
        $len = strlen($literal);
        if ($len < 2) {
            return $literal;
        }
        $q = $literal[0];
        if (($q !== '\'' && $q !== '"') || $literal[$len - 1] !== $q) {
            return $literal;
        }
        $body = substr($literal, 1, $len - 2);

        // Minimal unescape to cover common cases used for obfuscation
        // (We don’t need full PHP string semantics here)
        return str_replace(
            $q === '\'' ? ["\\'","\\\\"] : ['\\"','\\\\','\n','\r','\t'],
            $q === '\'' ? ["'","\\"]      : ['"','\\',"\n","\r","\t"],
            $body
        );
    }

    /**
     * Heuristic backtick execution detection: flags lines that contain an unescaped backtick
     * outside obvious quoted string contexts. This is intentionally simple and errs on the side of caution.
     *
     * @param  string              $filePath
     * @param  array<int,string>   $lines
     * @return array<int,array<string,mixed>>
     */
    private static function scanBackticks(string $filePath, array $lines): array
    {
        $issues = [];
        foreach ($lines as $i => $line) {
            // quick skip
            if (!str_contains($line, '`')) {
                continue;
            }
            // Very light check: if the line has an odd number of backticks (unbalanced),
            // or any backtick not obviously inside a quoted string, we flag it.
            // We avoid heavy parsing; this is just a heads-up.
            $tickCount = substr_count($line, '`');
            if ($tickCount > 0) {
                $issues[] = [
                    'type'    => 'invalid_token_usage',
                    'token'   => '`',
                    'file'    => $filePath,
                    'line'    => $i + 1,
                    'snippet' => trim($line),
                    'issue'   => 'Backtick shell execution detected',
                ];
            }
        }
        return $issues;
    }

    /**
     * Fallback when a line number is not available (shouldn’t happen with token_get_all).
     */
    private static function safeLineGuess(array $lines): int
    {
        return max(1, count($lines));
    }
}