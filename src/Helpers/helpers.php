<?php

if (!function_exists('stripComments')) {
    /**
     * Removes both single-line (//) and multi-line (/* *​/) comments from a JSON string.
     *
     * This function is useful for preprocessing JSON files that may contain comments,
     * which are not officially supported in the JSON specification. It uses regular
     * expressions to strip out both // line comments and /* block comments *​/ from the input string.
     *
     * @param string $json The JSON string potentially containing comments.
     * @return string The JSON string with all comments removed.
     */
    function stripComments(string $json): string
    {
        // Remove // line comments
        $json = preg_replace('/\/\/[^\n\r]*/', '', $json);
        // Remove /* block comments */
        return preg_replace('/\/\*.*?\*\//s', '', $json);
    }
}

if (!function_exists('ensureFileExistsAtomic')) {
    /**
     * Ensure a file exists without race-condition warnings.
     * Creates the file atomically with 'x' mode; silently succeeds if it already exists.
     */
    function ensureFileExistsAtomic(string $path): void
    {
        if (is_file($path)) {
            return;
        }

        // Try atomic create (fails harmlessly if another process won the race)
        $h = @fopen($path, 'xb');
        if ($h !== false) {
            fclose($h);
        } else {
            // If 'x' failed for non-existence reasons, fall back to touch; ignore warnings
            @touch($path);
        }

        // Best-effort: make sure it’s writable
        if (!is_writable($path)) {
            @chmod($path, 0666 & ~umask());
        }
    }
}