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
