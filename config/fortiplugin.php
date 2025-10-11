<?php /** @noinspection ALL */

return [

    /*
     * PSR-4 root namespace and folder for plugins.
     */
    'psr4_root' => env('FORTIPLUGIN_PSR4_ROOT', 'Plugins'),
    /*
    |--------------------------------------------------------------------------
    | Authorization / Gates
    |--------------------------------------------------------------------------
    |
    | Read by FortiGateRegistrar. Safe to use env() here (config is cached).
    |
    | allow_all_gates  : Dev-only override; if true, every Gate returns true.
    | allow_read_ops   : If true, Gates that look read-only ("view", "list")
    |                    are allowed by default in the *default* resolver.
    | scope_header     : Middleware passes a comma-separated list of abilities
    |                    in this header so the registrar can allow by scope.
    */

    'allow_all_gates' => env('FORTIPLUGIN_ALLOW_ALL_GATES', false),
    'allow_read_ops' => env('FORTIPLUGIN_ALLOW_READ_OPS', true),
    'scope_header' => env('FORTIPLUGIN_SCOPE_HEADER', 'X-Forti-Scopes'),


    /*
    |--------------------------------------------------------------------------
    | Authentication Tokens (Author & Placeholder)
    |--------------------------------------------------------------------------
    |
    | These govern how *auth* tokens are created and stored (unrelated to the
    | validator “tokens” below). Defaults kept here for convenience.
    |
    | prefix, length  : Cosmetic prefix and random length for raw tokens.
    | hash_algo       : How raw tokens are hashed at rest (e.g. sha256).
    | author.ttl_*    : Default lifetime for Author tokens (login sessions).
    | placeholder.*   : Default lifetime for Placeholder-scoped tokens.
    */

    'tokens' => [
        'prefix' => env('FORTIPLUGIN_TOKEN_PREFIX', 'forti_'),
        'length' => (int)env('FORTIPLUGIN_TOKEN_LENGTH', 64),
        'hash_algo' => env('FORTIPLUGIN_TOKEN_HASH', 'sha256'),

        'author' => [
            'ttl_minutes' => (int)env('FORTIPLUGIN_AUTHOR_TOKEN_TTL_MIN', 60 * 24), // 24h
        ],
        'placeholder' => [
            'ttl_days' => (int)env('FORTIPLUGIN_PLACEHOLDER_TOKEN_TTL_DAYS', 7),
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Policy Loading & Handshake
    |--------------------------------------------------------------------------
    |
    | We keep these *empty* so your PluginPolicy remains the canonical source
    | of defaults. If a host wants to override/inline a full snapshot, they
    | can set one of these.
    |
    | policy            : Inline array policy (skips files if set).
    | policy_php_path   : Path to a legacy PHP file that returns an array.
    | policy_path       : Path to a JSON policy file.
    |
    | Handshake caching:
    |   cache_ttl       : Cache-Control max-age (seconds).
    |   etag            : If true, /forti/handshake emits ETag & honors 304.
    |   expose_legacy_blob : If true, normalized snapshot includes "_legacy".
    */

    'policy' => null,
    'policy_php_path' => env('FORTIPLUGIN_POLICY_PHP', null),
    'policy_path' => env('FORTIPLUGIN_POLICY_PATH', null),

    'handshake' => [
        'cache_ttl' => (int)env('FORTIPLUGIN_HANDSHAKE_CACHE_TTL', 300),
        'etag' => (bool)env('FORTIPLUGIN_HANDSHAKE_ETAG', true),
        'expose_legacy_blob' => (bool)env('FORTIPLUGIN_HANDSHAKE_EXPOSE_LEGACY', true),
    ],


    /*
    |--------------------------------------------------------------------------
    | Keys / Cryptography (HostKeyService)
    |--------------------------------------------------------------------------
    |
    | Algorithm labels and generation parameters for host key pairs.
    | These do not conflict with PluginPolicy; safe to keep defaults here.
    */

    'keys' => [
        'algo' => env('FORTIPLUGIN_KEY_ALGO', 'RS256'),
        'bits' => (int)env('FORTIPLUGIN_KEY_BITS', 2048),
        'digest' => env('FORTIPLUGIN_KEY_DIGEST', OPENSSL_ALGO_SHA256),
        'verify_purpose' => env('FORTIPLUGIN_VERIFY_PURPOSE', 'installer_verify'),
        'sign_purpose' => env('FORTIPLUGIN_SIGN_PURPOSE', 'packager_sign'),
    ],


    'validator' => [
        // Additive risk sets (host *adds* items to Forti defaults)
        'tokens' => [],
        'dangerous_functions' => [],
        'forbidden_namespaces' => [],
        'forbidden_packages' => [],
        'allowed_class_methods' => [
            // 'DB' => ['transactions','rollback','commit'],
            // 'File' => ['exists'],
        ],

        // Optional scanner hints (leave empty unless you need them)
        'ignore' => [],
        'whitelist' => [],
        'scan_size' => [],   // e.g. ['php' => 5000000]
        'max_flagged' => null,

        /*
        |--------------------------------------------------------------------------
        | overrides (ALLOW specific things that defaults would block)
        |--------------------------------------------------------------------------
        |
        | These NEVER replace your defaults. They are exceptions that *permit*
        | specific items otherwise blocked by PluginPolicy’s built-in lists.
        |
        | functions      : allow these function names (unblock if otherwise forbidden)
        | tokens         : allow these “token” functions (unblock if flagged)
        | dangerous      : allow these dangerous functions (use sparingly!)
        | namespaces     : allow these namespaces/classes (remove from forbidden list)
        | packages       : allow these composer packages (remove from forbidden list)
        | wrappers       : allow stream wrappers (e.g. 'phar://') if you must
        | magic_methods  : allow PHP magic methods (e.g. '__call') if you must
        | classes        : allow extra METHODS on classes with method-allowlists
        |                 (adds to the existing allowlist for that class)
        |
        | NOTE: These are surgical exceptions. Prefer granting access via
        |       host review & narrow scopes instead of broad overrides.
        */
        'overrides' => [
            'functions' => [],          // e.g. ['file_get_contents']
            'tokens' => [],          // e.g. ['fopen']
            'dangerous' => [],          // e.g. ['exec']  ← HIGH RISK
            'namespaces' => [],          // e.g. ['Illuminate\\Support\\Facades\\Storage']
            'packages' => [],          // e.g. ['league/flysystem']
            'wrappers' => [],          // e.g. ['phar://']
            'magic_methods' => [],          // e.g. ['__call']
            'classes' => [            // per-class method allowances
                // 'DB' => ['select','statement'],
                // 'Storage' => ['put','get'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance / Publishing / Admin (Legacy knobs)
    |--------------------------------------------------------------------------
    |
    | Kept empty so PluginPolicy (or a full external policy) provides defaults.
    | Hosts can choose to fill *some* of these if they need to surface them
    | in the handshake. PolicyService will pass through if present.
    */

    'security' => [
        // 'must_kyc' => true,
    ],

    'publishing' => [
        // 'max_token_lifetime_days' => 30,
        // 'allow_public_plugins'    => false,
        // 'require_plugin_review'   => true,
    ],

    'admin' => [
        // 'allow_admin_override' => true,
    ],

];
