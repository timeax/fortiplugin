<?php

namespace Timeax\FortiPlugin\Core\Security;

use Timeax\FortiPlugin\Core\Exceptions\HostConfigException;
use Timeax\FortiPlugin\Core\Exceptions\DuplicateSettingIdException;

final class HostConfigValidator
{
    /**
     * Validate a HostConfig object:
     * - 'global' has no 'id' and only SettingValue values
     * - 'settings' is an array of Setting objects with unique 'id'
     * - Each Setting's non-id props are valid SettingValue types
     *
     * @throws HostConfigException
     */
    public static function validate(array $hostConfig): void
    {
        // ----- global (optional) -----
        if (array_key_exists('global', $hostConfig)) {
            if (!is_array($hostConfig['global'])) {
                throw new HostConfigException("'global' must be an object.");
            }
            if (array_key_exists('id', $hostConfig['global'])) {
                throw new HostConfigException("'global' must not contain an 'id'.");
            }
            foreach ($hostConfig['global'] as $k => $v) {
                if (!self::isValidSettingValue($v)) {
                    throw new HostConfigException("Invalid SettingValue at global['{$k}'].");
                }
            }
        }

        // ----- settings (optional) -----
        $ids = [];
        if (array_key_exists('settings', $hostConfig)) {
            if (!is_array($hostConfig['settings'])) {
                throw new HostConfigException("'settings' must be an array of Setting objects.");
            }

            foreach ($hostConfig['settings'] as $i => $setting) {
                $path = "settings[{$i}]";

                if (!is_array($setting)) {
                    throw new HostConfigException("'{$path}' must be an object.");
                }
                if (!array_key_exists('id', $setting)) {
                    throw new HostConfigException("'{$path}.id' is required.");
                }

                $id = $setting['id'];
                if (!is_string($id) && !is_int($id) && !is_float($id)) {
                    throw new HostConfigException("'{$path}.id' must be a string or number.");
                }

                // Enforce uniqueness (stringify so '1' and 1 collide)
                $idKey = (string)$id;
                if (isset($ids[$idKey])) {
                    throw new DuplicateSettingIdException($id, "at {$path}");
                }
                $ids[$idKey] = true;

                // Validate each non-id property value
                foreach ($setting as $k => $v) {
                    if ($k === 'id') {
                        continue;
                    }
                    if (!self::isValidSettingValue($v)) {
                        throw new HostConfigException("Invalid SettingValue at {$path}['{$k}'].");
                    }
                }
            }
        }
    }

    /** SettingValue = boolean | null | string | number | string[] | map<string, TriState> */
    private static function isValidSettingValue(mixed $v): bool
    {
        if (is_bool($v) || is_null($v) || is_string($v) || is_int($v) || is_float($v)) {
            return true;
        }

        if (is_array($v)) {
            // list of strings?
            if (self::isStringList($v)) {
                return true;
            }
            // map<string, TriState> ?
            if (!self::isList($v)) {
                foreach ($v as $kk => $vv) {
                    if (!is_string($kk)) return false;
                    if (!is_bool($vv) && !is_null($vv)) return false; // TriState
                }
                return true;
            }
        }

        return false;
    }

    private static function isStringList(array $arr): bool
    {
        if (!self::isList($arr)) return false;
        foreach ($arr as $item) {
            if (!is_string($item)) return false;
        }
        return true;
    }

    /** Polyfill for PHP < 8.1 array_is_list */
    private static function isList(array $arr): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($arr);
        }
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i++) return false;
        }
        return true;
    }
}