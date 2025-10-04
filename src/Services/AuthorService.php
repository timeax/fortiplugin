<?php /** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;
use Timeax\FortiPlugin\Enums\AuthorStatus;
use Timeax\FortiPlugin\Enums\IssueStatus;
use Timeax\FortiPlugin\Models\Author;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Models\PluginIssue;
use Timeax\FortiPlugin\Models\PluginIssueMessage;
use Timeax\FortiPlugin\Models\PluginZip;
use Timeax\FortiPlugin\Support\FortiGates;

class AuthorService
{
    public function __construct(private bool $authorize = true)
    {
    }

    /** Enable/disable Gate checks at runtime */
    public function withAuth(bool $authorize = true): static
    {
        $this->authorize = $authorize;
        return $this;
    }

    /* -----------------------------------------------------------------
     |  Mutations: verify / meta / status
     |-----------------------------------------------------------------*/

    /** Verify an author: set status→active (if pending/inactive), stamp meta.verified_at
     * @throws Throwable
     */
    public function verify(int|Author $author, ?string $note = null): Author
    {
        $author = $this->resolveAuthor($author);
        $this->auth(FortiGates::AUTHOR_VERIFY, $author);

        return DB::transaction(function () use ($author, $note) {
            if (in_array($author->status, [AuthorStatus::pending, AuthorStatus::inactive], true)) {
                $author->status = AuthorStatus::active;
            }

            $meta = (array)($author->meta ?? []);
            $meta['verified_at'] = now()->toIso8601String();
            if ($note !== null) {
                $meta['verified_note'] = $note;
            }
            $author->meta = $meta;

            // If your generated model still exposes a 'verified' boolean, set it defensively.
            if (array_key_exists('verified', $author->getAttributes())) {
                $author->setAttribute('verified', true);
            }

            $author->save();
            $this->audit('author.verify', ['author_id' => $author->id, 'status' => (string)$author->status, 'note' => $note]);

            return $author->fresh();
        });
    }

    /** Merge/replace author meta */
    public function updateMeta(int|Author $author, array $patch, bool $merge = true): Author
    {
        $author = $this->resolveAuthor($author);
        $this->auth(FortiGates::AUTHOR_UPDATE, $author);

        $base = (array)($author->meta ?? []);
        $author->meta = $merge ? $this->deepMerge($base, $patch) : $patch;
        $author->save();

        $this->audit('author.meta.update', ['author_id' => $author->id, 'merge' => $merge, 'keys' => array_keys($patch)]);
        return $author->fresh();
    }

    /** Status: active */
    public function activate(int|Author $author): Author
    {
        return $this->setStatus($author, AuthorStatus::active, FortiGates::AUTHOR_ACTIVATE, 'author.activate');
    }

    /** Status: blocked (optional reason logged) */
    public function block(int|Author $author, ?string $reason = null): Author
    {
        $author = $this->setStatus($author, AuthorStatus::blocked, FortiGates::AUTHOR_BLOCK, 'author.block');
        $this->audit('author.block.reason', ['author_id' => $author->id, 'reason' => $reason]);
        return $author;
    }

    /** Status: inactive */
    public function deactivate(int|Author $author): Author
    {
        return $this->setStatus($author, AuthorStatus::inactive, FortiGates::AUTHOR_DEACTIVATE, 'author.deactivate');
    }

    /* -----------------------------------------------------------------
     |  Queries: plugins / issues
     |-----------------------------------------------------------------*/

    /** Plugins via a declared author role (pivot PluginAuthor) */
    public function pluginsByRole(int|Author $author): Collection
    {
        $author = $this->resolveAuthor($author);
        $this->auth(FortiGates::AUTHOR_VIEW_PLUGINS, $author);

        return Plugin::query()
            ->whereHas('authors', fn($q) => $q->where('author_id', $author->id))
            ->with(['authors' => fn($q) => $q->where('author_id', $author->id)])
            ->get();
    }

    /** Plugins linked by uploaded zips (Zip → Placeholder → Plugin) */
    public function pluginsByUploads(int|Author $author): \Illuminate\Support\Collection
    {
        $author = $this->resolveAuthor($author);
        $this->auth(FortiGates::AUTHOR_VIEW_PLUGINS, $author);

        $placeholderIds = PluginZip::query()
            ->where('uploaded_by_author_id', $author->id)
            ->distinct()
            ->pluck('placeholder_id');

        if ($placeholderIds->isEmpty()) {
            return collect();
        }

        return Plugin::query()
            ->whereHas('placeholder', fn($q) => $q->whereIn('id', $placeholderIds))
            ->get();
    }

    /** All plugins (role ∪ upload), de-duplicated */
    public function plugins(int|Author $author): \Illuminate\Support\Collection
    {
        $byRole = $this->pluginsByRole($author);
        $byZip = $this->pluginsByUploads($author);
        return $byRole->concat((array)$byZip)->unique('id')->values();
    }

    /** Issues reported by author; filters: status, plugin_id */
    public function issuesReported(int|Author $author, array $filters = []): Collection
    {
        $author = $this->resolveAuthor($author);
        $this->auth(FortiGates::AUTHOR_VIEW_ISSUES, $author);

        $q = PluginIssue::query()->where('reporter_id', $author->id);

        if ($status = Arr::get($filters, 'status')) {
            $q->where('status', $status);
        }
        if ($pluginId = Arr::get($filters, 'plugin_id')) {
            $q->where('plugin_id', $pluginId);
        }

        return $q->orderByDesc('created_at')->get();
    }

    /** Issues where author has participated (left a message); optional plugin_id filter */
    public function issuesParticipated(int|Author $author, array $filters = []): Collection
    {
        $author = $this->resolveAuthor($author);
        $this->auth(FortiGates::AUTHOR_VIEW_ISSUES, $author);

        $q = PluginIssue::query()->whereHas('messages', fn($m) => $m->where('author_id', $author->id));

        if ($pluginId = Arr::get($filters, 'plugin_id')) {
            $q->where('plugin_id', $pluginId);
        }

        return $q->orderByDesc('created_at')->get();
    }

    /* -----------------------------------------------------------------
     |  Issue helpers (optional)
     |-----------------------------------------------------------------*/

    /** Open a new issue for a plugin
     * @throws Throwable
     */
    public function openIssue(int|Author $reporter, int|Plugin $plugin, string $type, string $description, ?string $severity = null, ?array $meta = null): PluginIssue
    {
        $reporter = $this->resolveAuthor($reporter);
        $plugin = $this->resolvePlugin($plugin);
        $this->auth(FortiGates::ISSUE_CREATE, $plugin);

        return DB::transaction(function () use ($reporter, $plugin, $type, $description, $severity, $meta) {
            $issue = new PluginIssue();
            $issue->plugin_id = $plugin->id;
            $issue->reporter_id = $reporter->id;
            $issue->type = $type;
            $issue->description = $description;
            $issue->severity = $severity;
            $issue->meta = $meta;
            $issue->save();

            $this->audit('issue.open', ['plugin_id' => $plugin->id, 'issue_id' => $issue->id, 'reporter_id' => $reporter->id]);
            return $issue;
        });
    }

    /** Comment on an existing issue */
    public function commentIssue(int|Author $author, int|PluginIssue $issue, string $message): PluginIssueMessage
    {
        $author = $this->resolveAuthor($author);
        $issue = $this->resolveIssue($issue);
        $this->auth(FortiGates::ISSUE_COMMENT, $issue);

        $msg = new PluginIssueMessage();
        $msg->issue_id = $issue->id;
        $msg->author_id = $author->id;
        $msg->message = $message;
        $msg->save();

        $this->audit('issue.comment', ['issue_id' => $issue->id, 'author_id' => $author->id]);
        return $msg;
    }

    /** Update issue status (triage/in_progress/resolved/rejected/closed) */
    public function updateIssueStatus(int|Author $actor, int|PluginIssue $issue, IssueStatus $status): PluginIssue
    {
        $actor = $this->resolveAuthor($actor);
        $issue = $this->resolveIssue($issue);
        $this->auth(FortiGates::ISSUE_UPDATE_STATUS, $issue);

        $issue->status = $status;
        $issue->save();

        $this->audit('issue.status', ['issue_id' => $issue->id, 'status' => $status->value, 'actor_id' => $actor->id]);
        return $issue->fresh();
    }

    /* -----------------------------------------------------------------
     |  Internals
     |-----------------------------------------------------------------*/

    protected function setStatus(int|Author $author, AuthorStatus $status, string $gate, string $auditAction): Author
    {
        $author = $this->resolveAuthor($author);
        $this->auth($gate, $author);

        $author->status = $status;
        $author->save();

        $this->audit($auditAction, ['author_id' => $author->id, 'status' => (string)$author->status]);
        return $author->fresh();
    }

    protected function resolveAuthor(int|Author $author): Author
    {
        return $author instanceof Author ? $author : Author::query()->findOrFail($author);
    }

    protected function resolvePlugin(int|Plugin $plugin): Plugin
    {
        return $plugin instanceof Plugin ? $plugin : Plugin::query()->findOrFail($plugin);
    }

    protected function resolveIssue(int|PluginIssue $issue): PluginIssue
    {
        return $issue instanceof PluginIssue ? $issue : PluginIssue::query()->findOrFail($issue);
    }

    protected function auth(string $ability, mixed $resource = null): void
    {
        if (!$this->authorize) {
            return;
        }
        Gate::authorize($ability, $resource);
    }

    protected function deepMerge(array $base, array $patch): array
    {
        foreach ($patch as $k => $v) {
            $base[$k] = (is_array($v) && isset($base[$k]) && is_array($base[$k]))
                ? $this->deepMerge($base[$k], $v)
                : $v;
        }
        return $base;
    }

    /** Hook into your central audit trail here */
    protected function audit(string $action, array $context = []): void
    {
        // e.g. AuditLog::create(['action' => $action, 'context' => $context, 'actor' => 'system']);
    }
}