# FortiPlugin

**FortiPlugin** is a secure, policy‑driven plugin system for PHP applications. It fortifies your plugin ecosystem with **install‑time scanning**, **signed packaging**, and **host‑enforced rules** so third‑party code is verified, auditable, and under your control.

> **Fortified plugin system for PHP — secure packaging, validation, and install‑time policy enforcement.**

---

## ✨ Key capabilities

* **Install‑time scanner** — Parse PHP into an AST and flag dangerous function calls, URL/file streams, path traversal, obfuscation hints, and high‑entropy blobs before anything is installed.
* **Signed packaging** — Embed a validation report, policy snapshot, and cryptographic signature inside each plugin archive.
* **Host policy enforcement** — Apply host‑defined rules both at build‑time (packaging) and at install‑time (re‑validation).
* **Admin override with audit** — Block by default; allow explicit overrides with full reports and tamper‑evident logs.
* **Defense‑in‑depth** — Optional runtime hardening (separate pools/containers, `disable_functions`, `open_basedir`).

---

## 🧭 Why FortiPlugin?

PHP is powerful and dynamic; uploaded plugins can hide payloads in non‑PHP files, comments, or encoded strings. Static scanning alone is not enough — **policy must be enforced at packaging and again at installation**. FortiPlugin brings that discipline:

* Treat plugins as **untrusted** until they pass scanning and signature checks.
* Keep a **paper trail** (validation report, signature, policy snapshot) with every build.
* Ensure the **host remains the final source of truth** for what is allowed.

---

## 🧱 Architecture at a glance

```
Developer                      Host (Your App)
---------                      ----------------
packager ──► fetch policy ──► policy API
   │                         ◄─ response (rules, cert)
   │
   ├─ scan plugin (AST + heuristics) ──┐
   ├─ produce validation report        │
   ├─ sign report/package ◄────────────┘ (host-provided key/cert)
   └─ embed report + snapshot + sig

                       ┌─────────────────────────────────────┐
Installer on Host      │ 1) Unpack to staging                │
   ├─ verify signature │ 2) Verify embedded report/snapshot  │
   ├─ rescan w/ latest │ 3) Compare w/ embedded results      │
   ├─ decide: block/ok │ 4) Install or prompt admin override │
   └─ audit everything └─────────────────────────────────────┘
```

---

## 🔒 What the scanner looks for

### Dangerous function calls (examples)

`eval`, `assert`, `exec`, `shell_exec`, `passthru`, `system`, `popen`, `proc_open`, `dl`, `create_function`, reflection‑based invocation.

### Dangerous patterns

* Remote includes: `include('http://...')`
* Dynamic function variables: `$f = 'e'.'val'; $f($code)`
* URL/stream wrappers: `php://`, `data://`, `zip://`, `glob://`
* Path traversal: `../..`, absolute sensitive paths (`/etc/`, `/proc/`)
* Obfuscation hints: long Base64, `gzinflate(base64_decode(...))`, `chr()` chains, XOR loops
* High‑entropy blobs or files with suspicious density vs behaviour

> The scanner is **extension‑agnostic**. It inspects **all files**, not just `.php`.

---

## 🧩 Host policy (deny‑by‑default)

FortiPlugin applies a host‑owned policy configuration. At packaging time the developer’s tool fetches it; at install time the host re‑applies it. This makes policy **centralised and versioned**.

### Example: `security-policy.json`

```jsonc
{
  "directory": "Plugins",
  "loader": "default",
  "validator": { "version": 1 },

  // Functions considered risky for file manipulation; flagged for review
  "tokens": [
    "file_get_contents", "fopen", "fwrite", "fread", "unlink",
    "copy", "rename", "mkdir", "rmdir", "glob", "scandir"
  ],

  // Folders to ignore while scanning
  "ignore": ["vendor", "tests"],

  // Vendor packages allowed even if they use flagged tokens
  "whitelist": ["nikic/php-parser"],

  // Method allowlist per sensitive class (empty array ⇒ no methods allowed)
  "blocklist": {
    "DB": ["transactions", "rollback", "commit"],
    "File": ["exists"],
    "Storage": []
  },

  // Instantly‑blocked functions (hard fail)
  "dangerous_functions": [
    "eval", "exec", "shell_exec", "system", "passthru",
    "proc_open", "popen", "pcntl_exec", "dl"
  ],

  // Per‑type scan limits (bytes)
  "scan_size": { "php": 50000, "js": 50000, "json": 50000, "txt": 50000 },

  // Maximum allowed issues before installation is blocked
  "max_flagged": 0
}
```

> **Philosophy:** start from **deny by default**, allow with intent. Policy changes are audited and versioned.

---

## 📦 Package contents (signed)

A valid plugin archive typically includes:

```
my-plugin.zip
├── plugin.php
├── manifest.json
├── validation/
│   ├── validation.log
│   ├── policy_snapshot.json
│   ├── signature.pem
│   └── plugin_fingerprint.sig
```

* **validation.log** — detector matches, AST call map, heuristics,
* **policy_snapshot.json** — the host policy used at packaging time,
* **plugin_fingerprint.sig** — signature over the package/report.

---

## 🧪 Install‑time validation flow

1. **Unpack** to a staging area (no execution).
2. **Verify** the embedded signature & report against the host’s trusted key.
3. **Rescan** with the **current** host policy.
4. **Compare** to embedded results; if mismatched/new issues → block.
5. **Decide**: install if clean, otherwise require admin override.
6. **Audit**: log files, policy version, signature fingerprints, override rationale.

---

## ⚙️ Quick start (repo)

> Pending publication to Packagist. Until then use a VCS/path repo.

**composer.json (app):**

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/timeax/fortiplugin" }
  ]
}
```

**Install (dev):**

```bash
composer require timeax/fortiplugin:*@dev
```

**Publish default policy (framework‑agnostic example):**

```bash
php vendor/bin/fortiplugin policy:publish   # optional CLI
```

> If you don’t use the CLI, place your `security-policy.json` where your host expects it and point the policy server to it.

---

## 🧰 Minimal scanning example (PHP)

> Illustrative snippet to show the approach (uses `nikic/php-parser`). Your implementation will be more robust.

```php
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

$code = file_get_contents($path);
$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
$ast    = $parser->parse($code);

$bad = [];
$traverser = new NodeTraverser();
$traverser->addVisitor(new class($bad) extends NodeVisitorAbstract {
    public array $bad = [];
    public function enterNode(Node $node) {
        if ($node instanceof Node\Expr\FuncCall) {
            $name = $node->name instanceof Node\Name ? $node->name->toString() : null;
            if (in_array($name, ['eval','exec','system','passthru','assert','shell_exec','proc_open'], true)) {
                $this->bad[] = $name;
            }
        }
    }
});
$traverser->traverse($ast);

if (!empty($traverser->visitors[0]->bad)) {
    // Block or flag per policy
}
```

---

## 🔏 Admin override (explicit & audited)

* Overrides are **off by default** and require an admin to confirm a review.
* The override record includes **who, when, why**, and the exact **policy/report** used.
* The plugin’s management UI shows a **warning banner** if installed via override.

---

## 🧮 Auditing & logs

For every installation attempt store:

* File list & hashes
* Flagged issues and AST call map
* Policy version & snapshot
* Signature fingerprints and verification status
* Whether an override was used (and rationale)

---

## 🧰 Optional runtime hardening

* Run plugins in separate PHP‑FPM pools or containers
* Set `disable_functions` & `open_basedir`
* Avoid dynamic `include` paths; never `eval` plugin code

---

## 📚 Terminology

* **Policy**: The host’s rules for what’s allowed, including tokens, dangerous functions, size limits, and class/method allowlists.
* **Scan**: Static analysis across **all** files (not just `.php`).
* **Report**: Machine‑readable log of matches, metrics, and timing.
* **Signature**: Cryptographic proof that the report and package weren’t altered.

---

## 🗺️ Roadmap

* Rich policy editor UI & diffing
* Deeper heuristics (n‑gram models, entropy thresholds per file type)
* First‑class Laravel integration (service provider, middleware, audit model)
* Marketplace protocol (capabilities, permission manifests)
* Revocation API & timestamp authority integration

---

## 🔁 Migration note (renaming)

This repository supersedes earlier drafts labelled **“Secure Plugin”**. All docs and identifiers are being migrated to **FortiPlugin**.

---

## 📄 License

TBD — choose a license that matches your distribution goals (MIT/Apache‑2.0/Proprietary). A `LICENSE` file will be added before tagging a stable release.

---

## 🙌 Contributing

* Open issues for bugs, naming, or policy defaults.
* PRs welcome for rules, detectors, and docs (include tests & rationale).

---

## 🧭 Maintainers

**Timeax** — security‑first plugin infrastructure.
