# Installations Module — README (Draft)

> **Scope:** Host-side installer that makes plugin installs safe and predictable without executing plugin code. This module orchestrates verification, optional security scans, vendor policy, Composer planning, atomic file copy, DB persistence, and decision/override flows — while writing a single canonical state file.

---

## 1) Goals & Non‑Goals

**Goals**

* Guarantee **program‑integrity** at install time (PSR‑4 root sync, `fortiplugin.json`, host config, permissions manifest, routes).
* Optionally run security file scans (host decides) and support a human‑in‑the‑loop **ASK** override.
* Keep a **single canonical log/state** file: `Plugins/{slug}/.internal/logs/installation.json`.
* Plan Composer dependencies; the **host** runs Composer — the installer never does.
* Persist final state to DB (`Plugin`, `PluginVersion`, `PluginZip` linkage) and keep a tamper‑evident audit trail.

**Non‑Goals**

* No runtime activation here (Activator is separate and comes later).
* No automatic Composer execution.
* No mutation of validator emissions — **verbatim** logging.

---

## 2) Public API (Installer)

```php
$installer
  ->emitWith(callable $fn)                 // unified emitter for validators + installer sections
  ->enableFileScan()                       // optional (default OFF)
  ->onFileScanError(fn($errors, $tokenCtx): Install) // default returns Install::ASK
  ->onValidationEnd(fn($summary) => void)
  ->install(int|string $plugin_zip_id, ?string $installer_token = null): DecisionResult;
```

**Enum:**

```php
enum Install { case BREAK; case INSTALL; case ASK; }
```

---

## 3) Life‑Cycle Phases (High level)

1. **Preflight (staging)**
   Safe extract, resolve slug, compute fingerprint and validator_config_hash. *(No validation yet.)*

2. **Validation block (everything that uses the Validation service)**

    * Mandatory program‑integrity checks: PSR‑4 root sync, `fortiplugin.json`, host config, permissions, routes.
    * Optional **file scan** (only if `enableFileScan()` was set).
    * Capture **all** validator emissions verbatim into `logs.validation_emits`.
    * Build the full **validation summary** (includes file‑scan results if it ran) and persist it to `installation.json`.

3. **`onValidationEnd($summary)`**
   Invoke your callback **exactly once here**, at the **end of the validation block** and **before** any non‑validation steps.

4. **Zip Validation Gate**
   Read `PluginZip.validation_status`: `verified` → continue; `pending` → issue/extend **background_scan** token and return **ASK**; `failed/unknown` → **BREAK**.

5. **Vendor Policy**
   Choose `STRIP_BUNDLED_VENDOR` (default) or `ALLOW_BUNDLED_VENDOR`; record in `installation.json.vendor_policy`.

6. **Composer Plan (dry)**
   Diff plugin requires vs host lock; mark `skip|add|conflict`; build **packages** map (foreign/non‑foreign) for UI and policy.

7. **Install (atomic)**
   Copy to `Plugins/{slug}/…` then promote pointer; update `installation.json.install`.

8. **DB Persist**
   Upsert `Plugin`, create `PluginVersion`, link `PluginZip` (must be `verified`); mirror `Plugin.meta.packages`.

9. **Decision (return)**
   Return `installed | ask | break` with summary and (where applicable) safe token metadata.

## 4) Directory Structure (module only) (module only)

```
src/Installations/
  Installer.php
  InstallerPolicy.php
  
  Enums/
    Install.php
    VendorMode.php
    ZipValidationStatus.php
    PackageStatus.php

  Contracts/
    Emitter.php
    ZipRepository.php
    PluginRepository.php
    RouteRegistry.php
    PermissionRegistry.php
    LockManager.php
    Clock.php
    Filesystem.php

  DTO/
    InstallContext.php
    InstallSummary.php
    ComposerPlan.php
    PackageEntry.php
    TokenContext.php
    DecisionResult.php

  Sections/
    VerificationSection.php
    ZipValidationGate.php
    FileScanSection.php
    VendorPolicySection.php
    ComposerPlanSection.php
    InstallFilesSection.php
    DbPersistSection.php

  Support/
    InstallationLogStore.php
    InstallerTokenManager.php
    ValidatorBridge.php
    ComposerInspector.php
    Psr4Checker.php
    AtomicFilesystem.php
    PathSecurity.php
    Fingerprint.php
    EmitterMux.php

  Exceptions/
    ValidationFailed.php
    ZipValidationFailed.php
    TokenInvalid.php
    ComposerConflict.php
    FilesystemError.php
    DbPersistError.php
```

> **Activator** will live later in `src/Installations/Activator/` (out of scope in this README draft).

---

## 5) Emissions (Event Contract)

* The Installer **bridges validator `$emit` verbatim** to the unified emitter and to disk logs.
* Installer‑origin events use the same envelope: `{ title, description, error, stats:{filePath,size}, meta:? }`.
* **No mutation** of validator `meta`; installer may add context only to its **own** events.

Typical installer titles:

* `Installer: Zip Validation`
* `Installer: Vendor Policy`
* `Installer: Composer Plan`
* `Installer: Files Copied`
* `Installer: DB Persist`
* `Installer: Decision <install|ask|break>`

---

## 6) Canonical State File

**Path:** `Plugins/{slug}/.internal/logs/installation.json`

**Write discipline:** atomic (tmp → rename), section merge (no clobber), optional short‑lived `.lock`.

**Contents (top‑level):**

* `meta` — slug, zip_id, fingerprint, validator_config_hash, psr4_root, actor, timestamps, paths.
* `verification` — `status`, `errors[]`, `warnings[]`, per‑check details, `finished_at`.
* `zip_validation` — `plugin_zip_status: verified|pending|failed|unknown`.
* `file_scan` — `enabled`, `status: skipped|pass|fail|pending`, `errors[]`.
* `vendor_policy` — `mode: strip_bundled_vendor|allow_bundled_vendor`.
* `composer_plan` — actions (`skip|add|conflict`) + core conflicts.
* `packages` — full map for **all** packages used by the plugin (see §9).
* `decision` — last decision, reason, and **safe** token metadata (no secrets).
* `install` — status, paths, timestamps.
* `activate` — status (will be used by Activator later).
* `logs.validation_emits[]` — validator events **verbatim**.
* `logs.installer_emits[]` — installer events.

---

## 7) Vendor Policy

* **Default:** `STRIP_BUNDLED_VENDOR` — ignore/delete plugin `vendor/` from staging (safer, faster scans).
* **Optional:** `ALLOW_BUNDLED_VENDOR` — keep plugin `vendor/` (higher collision risk). If chosen, file scans can target this directory.

Record decision in `installation.json.vendor_policy.mode`.

---

## 8) Composer Plan (Dry)

* Compare plugin requirements (from plugin’s `composer.json` or `fortiplugin.json` requires) with host `composer.lock`.
* For each package: decide `skip|add|conflict` and collect **core conflicts** (e.g., `laravel/framework`, `php`, `ext-*`).
* Persist under `installation.json.composer_plan`.
* The **host** executes Composer separately at the project root if they approve the plan.

---

## 9) Foreign Package Scanning & Meta

During Composer Plan we produce **full package visibility** and store it in both `installation.json` and (later) `Plugin.meta`.

```ts
interface Meta {
  packages: {
    [name: string]: {
      is_foreign: boolean;                    // true if host lock doesn't satisfy constraint
      status: 'verified'|'unverified'|'pending'|'failed';
    };
  };
}
```

**Defaults**

* Foreign packages → `unverified`.
* Already‑satisfied (from host lock) → `verified`.

**Optional host action:** “Scan foreign packages now?”

* If **Yes**: mark `pending`, run scans against allowed sources, then set `verified` or `failed` and reuse `onFileScanError()` decision path (`ASK|BREAK|INSTALL`).
* If **No**: keep `unverified` and proceed; **Activator** policy can block activation until they’re `verified` or override is granted.

---

## 10) Zip Validation Gate

* Reads `PluginZip.validation_status` right after Verification.
* Actions:

    * `verified` → continue.
    * `pending` → ISSUE/EXTEND **background_scan** token (10‑min TTL, one‑time; bound to zip_id + fingerprint + config hash + actor). Update `decision` snapshot and return **ASK**.
    * `failed` or unknown → BREAK.

Tokens are **encrypted to client** and **hashed server‑side**. No secrets are written to disk logs.

---

## 11) File Scan (Optional)

* Only runs if `enableFileScan()` was called.
* On any hit: call `onFileScanError($errors,$tokenCtx)` → `Install::ASK|BREAK|INSTALL`.
* If `ASK`: issue **install_override** token and return ASK; if accepted later, proceed to Install without re‑calling `onValidationEnd`.

Validator emissions remain verbatim in `logs.validation_emits`.

---

## 12) Install (Atomic) & DB Persist

* **InstallFilesSection**: copy from staging → `Plugins/{slug}/versions/{ver}` (or similar), then promote `current` pointer; update `installation.json.install`.
* **DbPersistSection**: upsert `Plugin` (Prisma model), create `PluginVersion`, link `PluginZip` (must be `verified`), persist `Plugin.meta.packages`, queue providers/routes (not live).

**Plugin model (excerpt)**

* `plugins.name` unique; `plugin_placeholder_id` unique.
* Status typically `active` (or `installed_inactive` if you prefer) — activation is separate.

---

## 13) Error Taxonomy (Installer‑level)

* `COMPOSER_PSR4_MISMATCH | MISSING_ROOT | JSON_READ_ERROR`
* `CONFIG_MISSING | CONFIG_SCHEMA_INVALID | CONFIG_READ_ERROR`
* `HOST_CONFIG_INVALID | HOST_CONFIG_MISSING_FLAG`
* `PERMISSION_MANIFEST_INVALID | PERMISSION_CATEGORY_UNKNOWN | PERMISSION_ACTION_INVALID`
* `ROUTE_SCHEMA_INVALID | ROUTE_ID_DUPLICATE | ROUTE_PATH_INVALID | ROUTE_METHOD_INVALID | ROUTE_CONTROLLER_OUT_OF_ROOT | ROUTE_MIDDLEWARE_NOT_ALLOWED | ROUTE_FILE_READ_ERROR`
* `ZIP_VALIDATION_FAILED | ZIP_VALIDATION_PENDING`
* `SCAN_*` (when optional scanning is enabled)
* `COMPOSER_CORE_CONFLICT`
* `INSTALL_COPY_FAILED | INSTALL_PROMOTION_FAILED`
* `DB_PERSIST_FAILED`
* `TOKEN_ISSUED | TOKEN_EXTENDED | TOKEN_ACCEPTED | TOKEN_INVALID`

All verification errors are **hard BREAK**. File‑scan errors follow `onFileScanError()`’s return.

---

## 14) Concurrency, Atomicity & Idempotency

* Per‑slug install lock via `LockManager`.
* Atomic writes for `installation.json` (tmp → rename) and pointer promotion.
* Re‑install of same fingerprint is a no‑op (idempotent).

---

## 15) Acceptance Criteria (Checklist)

* [ ] Verification runs, logs verbatim validator emits, and persists snapshot to `installation.json`.
* [ ] Any verification error → installer returns `status:"break"` and no files are installed.
* [ ] Zip gate enforces `verified|pending|failed` with correct token behavior.
* [ ] Optional file scan uses `onFileScanError()` to decide `ASK|BREAK|INSTALL` and records decision safely.
* [ ] Vendor policy recorded; default = strip bundled vendor.
* [ ] Composer plan persists actions and full `packages` map.
* [ ] Foreign package scanning path updates per‑package `status` and can block activation by policy.
* [ ] Files are copied/promoted atomically; DB persisted with `Plugin.meta.packages`.
* [ ] `installation.json` contains both `logs.validation_emits[]` (verbatim) and `logs.installer_emits[]`.
* [ ] No plugin code executed during install.

---

## 16) Quick Usage Example

```php
$installer
  ->emitWith($uiEmitter)
  ->enableFileScan() // optional
  ->onFileScanError(fn($errors,$ctx) => Install::ASK)
  ->onValidationEnd(function($summary){ /* update UI, etc. */ })
  ->install($zipId, $maybeToken);
```

Return shape:

```php
new DecisionResult(
  status: 'installed'|'ask'|'break',
  summary: InstallSummary,
  tokenEncrypted?: string,
  expiresAt?: string
);
```

---

## 17) Next (Out‑of‑scope here)

* **Activator** module: preflight (zip verified, routes approved, packages verified or override), write routes/providers, flip active pointer, audit.
* CLI/Jobs to run Composer and to scan foreign packages post‑plan.

---

# Implementation Path & Build Order

> This is a pragmatic, sequential build plan with a **strict order**. Follow it to land the Installations module incrementally while keeping the surface area testable at each step. No business logic should execute plugin code at any stage.

## Phase 0 — Scaffolding & Contracts (Foundation)

**Goal:** Create stable interfaces and utilities so later sections can compile and run with stubs.

**Create first:**

```
src/Installations/
  Installer.php                                   # empty orchestrator skeleton (methods only)
  InstallerPolicy.php                              # default knobs; file-scan OFF, vendor=STRIP

  Enums/
    Install.php                                    # BREAK | INSTALL | ASK
    VendorMode.php                                 # STRIP_BUNDLED_VENDOR | ALLOW_BUNDLED_VENDOR
    ZipValidationStatus.php                        # verified | pending | failed | unknown
    PackageStatus.php                              # verified | unverified | pending | failed

  Contracts/
    Emitter.php                                    # function(array $payload): void
    ZipRepository.php                              # getZip(), getValidationStatus(), etc. (stubs)
    PluginRepository.php                           # upsertPlugin(), createVersion(), linkZip(), saveMeta()
    RouteRegistry.php                              # ensureUniqueGlobalId(), queueRoutes()
    PermissionRegistry.php                         # registerDefinitions()
    LockManager.php                                # acquire(slug), release(slug)
    Clock.php                                      # now(): DateTimeImmutable
    Filesystem.php                                 # safe fs ops interface

  Support/
    InstallationLogStore.php                       # atomic merge-write to installation.json (empty impl)
    EmitterMux.php                                 # fan-out to UI + log store
    AtomicFilesystem.php                           # tmp→rename helpers (no logic yet)
    PathSecurity.php                               # stubs: validateNoTraversal(), validateNoSymlink()
    Fingerprint.php                                # compute(zip), configHash()
    Psr4Checker.php                                # check(host composer.json, psr4_root)
    ValidatorBridge.php                            # pass-through from ValidatorService emit to Installer emitter (verbatim)
```

**Definition of Done:**

* Installer can be constructed with dependencies, `emitWith()` wires to `EmitterMux`, and `installation.json` can be created with a minimal shell (meta only).

---

## Phase 1 — VerificationSection (Program‑Integrity)

**Goal:** Land the mandatory checks and the `onValidationEnd($summary)` callback. Stop on any error.

**Add:**

```
src/Installations/Sections/
  VerificationSection.php                          # PSR-4, fortiplugin.json, host config, permissions, routes
```

**Wire:**

* `Installer::install()` → acquire lock → **VerificationSection::run()** with a **ValidatorService** instance.
* Bridge validator `$emit` via **ValidatorBridge** into **EmitterMux** and **InstallationLogStore** (verbatim).
* Build a `summary` object and persist into `installation.json.verification`.
* **Do not** call `onValidationEnd` yet; it will be invoked **after** the optional file scan completes (end of the validation block).
* If any error exists → return Decision `break` (no further phases).

**Definition of Done:**

* Fails hard on: PSR‑4 mismatch, missing/invalid fortiplugin.json, host config invalid, permission manifest invalid, route errors.
* Logs show **validation_emits** verbatim; installer emits `Installer: Verification complete`.

---

## Phase 3 — ZipValidationGate

**Goal:** Enforce `PluginZip.validation_status` before any optional scanning or copy.

**Add:**

```
src/Installations/Sections/
  ZipValidationGate.php
Support/
  InstallerTokenManager.php                         # encrypted-to-client, hashed server side
```

**Wire:**

* After Verification, read zip status via **ZipRepository**:

    * `failed|unknown` → emit `Installer: Zip Validation (failed)` → Decision `break`.
    * `pending` → issue/extend token (purpose=`background_scan`), persist decision snapshot, emit `ask`, return.
    * `verified` → continue.

**Definition of Done:**

* `installation.json.zip_validation` updated; `decision` reflects ask/break/continue.

---

## Phase 2 — FileScanSection (Optional)

> **Timing note:** `onValidationEnd($summary)` is invoked **after this phase completes**, capturing both the mandatory checks and any file‑scan results.

**Goal:** Allow hosts to opt‑in scanning and control outcome via `onFileScanError()`.

**Add:**

```
src/Installations/Sections/
  FileScanSection.php
```

**Wire:**

* Run only if `enableFileScan()` was called.
* Collect errors; call `onFileScanError($errors,$ctx)` → act on `Install::ASK|BREAK|INSTALL`.
* On `ASK`, issue **install_override** token; persist decision and return.
* Update `installation.json.file_scan` and append verbatim emits to logs.

**Definition of Done:**

* Default path (no enable) skips cleanly. With enable, errors route through ASK/BREAK/INSTALL.

---

## Phase 4 — VendorPolicySection

**Goal:** Decide STRIP vs ALLOW for plugin `vendor/` and record it.

**Add:**

```
src/Installations/Sections/
  VendorPolicySection.php
```

**Wire:**

* Default `VendorMode::STRIP_BUNDLED_VENDOR`.
* Persist `installation.json.vendor_policy.mode`.

**Definition of Done:**

* If STRIP, staging `vendor/` is excluded from later copy. If ALLOW, it remains (no scanning unless host opts in separately).

---

## Phase 5 — ComposerPlanSection (+ Packages Map)

**Goal:** Produce a dry plan and full package visibility (foreign/non‑foreign) for UI & policy.

**Add:**

```
src/Installations/Sections/
  ComposerPlanSection.php
Support/
  ComposerInspector.php                              # read host composer.json/lock; satisfy/skip/conflict
DTO/
  ComposerPlan.php
  PackageEntry.php                                   # { name, is_foreign, status }
```

**Wire:**

* Compute actions: `skip|add|conflict`; detect **core conflicts** (e.g., laravel/framework, php, ext-*).
* Build `packages` map for **all** plugin packages:

    * `is_foreign = true` if host lock doesn’t satisfy constraint → status `unverified`.
    * else status `verified`.
* Persist: `installation.json.composer_plan` and `installation.json.packages`.
* If your policy marks core conflicts as fatal → Decision `break`.

**Definition of Done:**

* UI can display counts (all vs foreign) and offer “Scan foreign packages now?” using this data.

---

## Phase 6 — InstallFilesSection (Atomic Copy & Promote)

**Goal:** Safely place files under `Plugins/{slug}` without code exec.

**Add:**

```
src/Installations/Sections/
  InstallFilesSection.php
```

**Wire:**

* Use **AtomicFilesystem** + **PathSecurity** to copy from staging → `versions/{ver}` (or fingerprint), then promote `current` pointer.
* Persist `installation.json.install` paths + status `installed`.

**Definition of Done:**

* Atomic rename works; failure emits `INSTALL_COPY_FAILED`/`INSTALL_PROMOTION_FAILED` and returns Decision `break`.

---

## Phase 7 — DbPersistSection

**Goal:** Reflect the install in DB and mirror meta (packages) to the Plugin model.

**Add:**

```
src/Installations/Sections/
  DbPersistSection.php
```

**Wire:**

* **PluginRepository**: upsert `Plugin`, create `PluginVersion`, link `PluginZip` (must be `verified`).
* Save `Plugin.meta.packages` exactly from `installation.json.packages`.
* Queue routes/providers (definitions only; not active).

**Definition of Done:**

* DB rows exist and are linked; `installation.json` updated with IDs/paths.

---

## Phase 8 — Decision & Returns

**Goal:** Finalize, unlock, and return a stable result.

**Wire:**

* Emit `Installer: Decision <installed|ask|break>`.
* Release lock; return `DecisionResult { status, summary, token? }`.

**Definition of Done:**

* Re‑invocation with the **same fingerprint** and clean state is idempotent (no duplicate work).

---

## Test Plan (Minimum per Phase)

* **P0:** creates `installation.json` with meta; emitter streams.
* **P1:** each verification error path returns `break`; logs contain validator emits verbatim.
* **P2:** zip `pending` issues token and returns `ask`; `failed` breaks.
* **P3:** file-scan enabled → `ASK` yields token; `INSTALL` proceeds; `BREAK` stops.
* **P4:** vendor STRIP removes staged `vendor/` from copy set.
* **P5:** composer plan marks foreign vs satisfied; core conflict can break.
* **P6:** copy/promote is atomic; failures are surfaced.
* **P7:** DB rows/links created and meta.packages mirrored.
* **P8:** result object stable; lock released.

---

## Suggested Sprinting

* **Sprint 1:** Phases 0–1
* **Sprint 2:** Phases 2–3
* **Sprint 3:** Phases 4–5
* **Sprint 4:** Phases 6–7
* **Sprint 5:** Phase 8 + hardening (locks, idempotency, race tests)

---

## Notes

* **Emitters:** validator payloads remain **verbatim**; installer adds its own under `logs.installer_emits`.
* **Composer:** installer never executes Composer; only plans.
* **Tokens:** encrypted to client, hashed server-side; bound to zip_id + fingerprint + config hash + actor.
* **Activation:** separate module (later).

---

# File‑by‑File Purpose (Detailed)

Below is a concise but comprehensive description of **every file** in the Installations module, what it owns, the inputs/outputs it works with, and any notable side‑effects or failure modes.

## Root

* **Installer.php**
  Orchestrator for the entire install flow. Coordinates phases (staging → verification → zip gate → optional file scan → vendor policy → composer plan → file copy/promote → DB persist → decision). Holds hooks (`emitWith`, `enableFileScan`, `onFileScanError`, `onValidationEnd`) and the public `install($zipId, $token)` entrypoint. Ensures validator emissions are bridged **verbatim**, writes to `installation.json` via `InstallationLogStore`, and guarantees **no plugin code executes**. Main failure modes: validation failure, zip gate failure, composer core conflict, file copy/promote error, DB persist error, invalid/expired token.

* **InstallerPolicy.php**
  Central default configuration and guardrails. Defines defaults (file scan OFF, vendor mode = STRIP, activation gating rules for foreign packages, core conflict handling). Exposes getters used by sections to make consistent decisions without duplicating magic values.

## Enums

* **Enums/Install.php**
  Ternary decision from file‑scan (and similar) callbacks: `BREAK` (abort), `ASK` (pause & issue token), `INSTALL` (proceed). Used by `FileScanSection` and token flows.

* **Enums/VendorMode.php**
  Vendor strategy chosen by host: `STRIP_BUNDLED_VENDOR` (default; ignore/delete plugin `vendor/`) or `ALLOW_BUNDLED_VENDOR` (keep; higher collision risk). Consumed by `VendorPolicySection` and `InstallFilesSection`.

* **Enums/ZipValidationStatus.php**
  Mirrors `PluginZip.validation_status`: `verified`, `pending`, `failed`, `unknown`. Interpreted by `ZipValidationGate` to either continue, ASK with background token, or BREAK.

* **Enums/PackageStatus.php**
  Lifecycle for each dependency in `Meta.packages`: `verified`, `unverified`, `pending`, `failed`. Set by `ComposerPlanSection` (initial), optionally updated by foreign‑package scans.

## Contracts (Interfaces)

* **Contracts/Emitter.php**
  Unified event callback signature: accepts the standard payload `{ title, description, error, stats:{filePath,size}, meta? }`. Implementations may multiplex to UI, logs, metrics.

* **Contracts/ZipRepository.php**
  Accessor for plugin zip records and validation status. Methods typically include: `getZip(zipId)`, `getValidationStatus(zipId)`, `setValidationStatus(zipId, status)`. Used by `ZipValidationGate` and `Installer`.

* **Contracts/PluginRepository.php**
  DB façade for Prisma models: upsert `Plugin`, create `PluginVersion`, link `PluginZip`, persist `Plugin.meta` (including packages), and append audit logs. Used by `DbPersistSection`.

* **Contracts/RouteRegistry.php**
  Read/Write access for global route ID uniqueness checks and registration queue. `ensureUniqueGlobalId(id)`, `queueRoutes(slug, routes)`. Used by `VerificationSection` (validation) and later by Activator for writing.

* **Contracts/PermissionRegistry.php**
  Persists permission definitions extracted from the plugin manifest. Used by `DbPersistSection` to register permissions without granting them.

* **Contracts/LockManager.php**
  Per‑slug install lock: `acquire(slug)`/`release(slug)`. Prevents concurrent installs of the same plugin.

* **Contracts/Clock.php**
  Abstraction for time (e.g., `now()`), enabling deterministic tests and token expiry checks.

* **Contracts/Filesystem.php**
  Safe FS operations used by sections and helpers (exists, readJson, writeAtomic, copyTree, rename, delete). Default implementation should guard against symlinks and traversal via `PathSecurity`.

## DTO

* **DTO/InstallContext.php**
  Immutable context assembled by Installer: zipId, slug, psr4Root, staging & install paths, actor, fingerprint, `validator_config_hash`, vendor mode, policy refs. Passed into sections to avoid parameter bloat.

* **DTO/InstallSummary.php**
  Aggregated, serializable snapshot of the install attempt: statuses/errors for each phase, token metadata (safe), counts, and pointers used for the return value and for writing `installation.json`.

* **DTO/ComposerPlan.php**
  Dry‑run plan: array of actions (`skip|add|conflict`) per package, plus a list of **core conflicts** that may be fatal. Consumed by UI and policy checks.

* **DTO/PackageEntry.php**
  A single entry for `Meta.packages`: `{ name, is_foreign, status }`. `is_foreign = true` when host lock doesn’t satisfy constraint.

* **DTO/TokenContext.php**
  Internal, non‑secret token context: purpose (`install_override` or `background_scan`), expiresAt, zipId, fingerprint, configHash, actor. Stored server‑side (hashed token stored elsewhere), echoed in `installation.json.decision` without secrets.

* **DTO/DecisionResult.php**
  The return type of `Installer::install`: `{ status: installed|ask|break, summary, tokenEncrypted?, expiresAt? }`.

## Sections (Business Logic)

* **Sections/VerificationSection.php**
  Runs **mandatory** program‑integrity checks: PSR‑4 root sync vs host composer, `fortiplugin.json` presence/shape (optionally via schema), Host config expectations, Permission manifest structure/labels, and **Routes** (JSON schema, global unique IDs, path/method rules, controller FQCN sanity, middleware allow‑list, collisions). Bridges ValidatorService emissions verbatim; compiles a `summary` and updates `installation.json.verification`. Any error → Installer returns `break`.

* **Sections/ZipValidationGate.php**
  Checks `PluginZip.validation_status`. `verified` → continue; `pending` → issue/extend **background_scan** token (10‑min TTL, one‑time), update `installation.json.decision`, emit ASK and return; `failed/unknown` → BREAK.

* **Sections/FileScanSection.php**
  Optional deep scans (FileScanner/Content/Token/AST) only if Installer called `enableFileScan()`. On hits, invokes `onFileScanError($errors,$ctx)` which returns `ASK|BREAK|INSTALL`. On `ASK`, issues **install_override** token and returns. Updates `installation.json.file_scan` and logs; validator events stored verbatim.

* **Sections/VendorPolicySection.php**
  Applies vendor strategy. Default: `STRIP_BUNDLED_VENDOR` (ignore plugin `vendor/`). Optional: `ALLOW_BUNDLED_VENDOR` (keep). Records choice in `installation.json.vendor_policy` so subsequent phases honor it (e.g., excluding `vendor/` from copy if STRIP).

* **Sections/ComposerPlanSection.php**
  Reads plugin requirements and host `composer.lock` to compute a dry plan: `skip|add|conflict`. Detects **core conflicts** (e.g., `laravel/framework`, `php`, `ext-*`). Builds the complete `packages` map for visibility (`is_foreign` + initial `status`: foreign→`unverified`, satisfied→`verified`). Persists to `installation.json.composer_plan` and `installation.json.packages`. Policy may BREAK on core conflicts.

* **Sections/InstallFilesSection.php**
  Secure, atomic installation: copies from staging to `Plugins/{slug}/versions/{version|fingerprint}` and promotes the `current` pointer/symlink. Honors vendor policy (skips plugin `vendor/` on STRIP). Updates `installation.json.install`. Failure surfaces `INSTALL_COPY_FAILED` or `INSTALL_PROMOTION_FAILED` and returns `break`.

* **Sections/DbPersistSection.php**
  Persists DB state using `PluginRepository`: upsert `Plugin`, create `PluginVersion`, link `PluginZip` (must be `verified`), persist `Plugin.meta` (including `packages`). Queues routes/providers for later activation. Updates `installation.json` with record IDs/paths. On failure → `DB_PERSIST_FAILED` and `break`.

## Support (Helpers)

* **Support/InstallationLogStore.php**
  Single source of truth for `Plugins/{slug}/.internal/logs/installation.json`. Provides atomic read/merge/write, maintains `logs.validation_emits[]` (verbatim) and `logs.installer_emits[]`, and ensures section updates don’t clobber each other. Handles optional `.lock` and tmp→rename.

* **Support/InstallerTokenManager.php**
  Issues and validates installer tokens. Generates encrypted‑to‑client tokens and stores a server‑side hash bound to `zipId + fingerprint + validator_config_hash + actor`. Supports purposes `install_override` and `background_scan`, one‑time use, TTL extension for pending scans. Never writes secrets to logs.

* **Support/ValidatorBridge.php**
  Thin adapter that forwards ValidatorService `$emit` events to the unified emitter and `InstallationLogStore` **without mutation**. Guarantees validator metadata (`meta`) stays untouched.

* **Support/ComposerInspector.php**
  Parses host `composer.json/lock`, evaluates constraint satisfaction, enumerates existing packages, and flags core packages. Used solely for planning; never runs Composer.

* **Support/Psr4Checker.php**
  Verifies that `env('FORTIPLUGIN_PSR4_ROOT','Plugins')` is mapped in host composer autoload and that the plugin’s intended namespace resolves beneath the root. Returns structured info for errors and for `verification` details.

* **Support/AtomicFilesystem.php**
  High‑level FS ops with atomic guarantees (tmp write + rename, tree copy with rollback). Used by staging extract, install copy, and pointer promotion.

* **Support/PathSecurity.php**
  Guards against path traversal, absolute paths, symlinks, nested phars/zip bombs. Called by extract/copy routines prior to any write.

* **Support/Fingerprint.php**
  Computes a canonical fingerprint (e.g., SHA‑256) of the zip and a `validator_config_hash` for reproducibility and token binding.

* **Support/EmitterMux.php**
  Fan‑out emitter that forwards the same payload to multiple sinks (UI emitter, log store, metrics) without altering content/order.

## Exceptions

* **Exceptions/ValidationFailed.php**
  Thrown when mandatory verification finds any error; caught by Installer to return `break` with a structured summary.

* **Exceptions/ZipValidationFailed.php**
  Raised when `PluginZip.validation_status` is `failed`/`unknown`. Installer converts to a `break` decision.

* **Exceptions/TokenInvalid.php**
  Used for expired/invalid/mismatched installer tokens (purpose, zipId, fingerprint, actor). Leads to `break` with an appropriate installer emit.

* **Exceptions/ComposerConflict.php**
  Signals fatal core conflicts from planning (depending on policy). Installer may choose `break` directly when thrown.

* **Exceptions/FilesystemError.php**
  Wraps copy/promotion/delete failures with safe details (no sensitive paths). Always results in `break`.

* **Exceptions/DbPersistError.php**
  Wraps DB write/link failures. Installer emits failure and returns `break`.

---

## Cross‑Cutting Guarantees

* **No plugin code execution** in any file or phase.
* **Verbatim logging** of validator emissions; installer adds its own events separately.
* **Atomic writes & idempotency** wherever possible (logs, install pointer).
* **Security by construction:** strip plugin `vendor/` by default; host chooses otherwise and can scan foreign packages before activation.

---

## Appendix — Flows (Text Sequence Diagrams)

### A) Clean path (no file-scan hits)

```
Host UI -> Installer               : install(zipId, token? = null)
Installer -> LockManager           : acquire(slug)
Installer -> AtomicFS              : extract to staging
Installer -> ValidatorService      : run(root, emit)  // mandatory checks
ValidatorService -> Installer.emit : (verbatim validator events)
Installer -> InstallationLogStore  : append validation_emits + summary
Installer -> onValidationEnd       : call once with full summary (end of validation block)
Installer -> ZipRepository         : getValidationStatus(zipId)
ZipRepository --> Installer        : "verified"
Installer -> VendorPolicySection   : decide mode (default STRIP)
Installer -> ComposerPlanSection   : build dry plan + packages map
Installer -> InstallFilesSection   : copy to Plugins/{slug}/...; promote pointer
Installer -> DbPersistSection      : upsert Plugin, create PluginVersion, link PluginZip, save meta.packages
Installer -> LockManager           : release(slug)
Installer --> Host UI              : Decision { status: installed, summary }
```

### B) Zip is **pending** (background scan) after validation

```
Host UI -> Installer               : install(zipId)
... (same validation block as A) ...
Installer -> onValidationEnd       : call with summary
Installer -> ZipRepository         : getValidationStatus(zipId)
ZipRepository --> Installer        : "pending"
Installer -> TokenManager          : issue token (purpose = background_scan, TTL 10m)
Installer -> InstallationLogStore  : decision = ask (safe token metadata only)
Installer -> LockManager           : release(slug)
Installer --> Host UI              : Decision { status: ask, token: (encrypted), purpose: background_scan }
```

**Later: resume with background token**

```
Host UI -> Installer               : install(zipId, token=background_scan)
Installer -> TokenManager          : validate token (zipId, fingerprint, configHash, actor)
Installer -> InstallationLogStore  : read installation.json (latest)
[if file_scan.errors exist]
  Installer -> onFileScanError     : ($errors, ctx)  // ASK|BREAK|INSTALL
  alt ASK:
    Installer -> TokenManager      : issue token (install_override)
    Installer --> Host UI          : Decision { status: ask, purpose: install_override }
  alt BREAK:
    Installer --> Host UI          : Decision { status: break }
  alt INSTALL:
    (continue as in A from VendorPolicySection)
[else no file_scan errors]
  (continue as in A from VendorPolicySection)
```

### C) File-scan **enabled** and hits found (zip already verified)

> Note: `onValidationEnd` happens **before** this decision, because file-scan is part of the validation block.

```
Host UI -> Installer               : install(zipId)
... (validation block runs: mandatory checks + file scan) ...
Installer -> onValidationEnd       : call with summary (includes scan results)
Installer -> ZipRepository         : getValidationStatus(zipId) = "verified"
[scan_errors > 0]
  Installer -> onFileScanError     : ($errors, ctx)
  alt ASK:
    Installer -> TokenManager      : issue token (purpose = install_override)
    Installer -> InstallationLog   : decision = ask (safe token metadata)
    Installer -> LockManager       : release
    Installer --> Host UI          : Decision { status: ask, purpose: install_override }
  alt BREAK:
    Installer -> LockManager       : release
    Installer --> Host UI          : Decision { status: break }
  alt INSTALL:
    (continue as in A from VendorPolicySection)
[scan_errors = 0]
  (continue as in A from VendorPolicySection)
```

**Later: resume with install-override token**

```
Host UI -> Installer               : install(zipId, token=install_override)
Installer -> TokenManager          : validate token
// Skip re-running validation and skip onValidationEnd (already completed)
(continue as in A from VendorPolicySection)
```

### D) Emission & logging (applies to all paths)

* **Validator events**: forwarded verbatim to the unified emitter and appended to `installation.json.logs.validation_emits[]`.
* **Installer events**: same envelope, appended to `installation.json.logs.installer_emits[]`.
* **`onValidationEnd`** fires **once**, after the entire validation block (mandatory checks + optional file scan) and before ZipValidationGate.

### E) Token rules (recap)

* **background_scan**: issued only when `PluginZip.validation_status = pending`. On resume, the installer reads `installation.json` and then decides whether to call `onFileScanError` (if scan errors exist) or continue.
* **install_override**: issued only after verified zip **and** file-scan hits; on resume, validation is **not** re-run and `onValidationEnd` is **not** called again—flow proceeds to non-validation phases.
* Tokens are encrypted to the client, hashed server-side, TTL default **10 minutes**, single-use, and bound to `{ zipId, fingerprint, validator_config_hash, actor }`.
