<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\AuthorStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $handle
 * @property string|null $email
 * @property string $password
 * @property string|null $avatar_url
 * @property string|null $org
 * @property string|null $website
 * @property array|null $meta
 * @property AuthorStatus::class $status
 * @property bool $verified
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, Plugin::class> $pluginLinks
 * @property \Illuminate\Support\Collection<int, PluginIssue::class> $reportedIssues
 * @property \Illuminate\Support\Collection<int, PluginIssueMessage::class> $issueMessages
 * @property \Illuminate\Support\Collection<int, PluginZip::class> $uploadedZips
 * @property \Illuminate\Support\Collection<int, PluginToken::class> $pluginTokens
 * @property \Illuminate\Support\Collection<int, AuthorToken::class> $tokens
 * @property \Illuminate\Support\Collection<int, PluginAuditLog::class> $pluginAuditActors
 * @property \Illuminate\Support\Collection<int, AuditLog::class> $auditActors
 */
class Author extends Model
{
	protected $table = "scpl_authors";

	protected $fillable = [
		"slug",
		"name",
		"handle",
		"email",
		"password",
		"avatar_url",
		"org",
		"website",
		"meta",
		"status",
		"verified",
	];

	protected $hidden = ["password"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"status" => AuthorStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function pluginLinks()
	{
		return $this->belongsToMany(
			Plugin::class,
			"plugin_author",
			"author_id",
			"plugin_id",
			"id",
			"id",
		); // pivot: plugin_author
	}

	public function reportedIssues()
	{
		return $this->hasMany(PluginIssue::class, "reporter_id", "id");
	}

	public function issueMessages()
	{
		return $this->hasMany(PluginIssueMessage::class, "author_id", "id");
	}

	public function uploadedZips()
	{
		return $this->hasMany(PluginZip::class, "uploaded_by_author_id", "id");
	}

	public function pluginTokens()
	{
		return $this->hasMany(PluginToken::class, "author_id", "id");
	}

	public function tokens()
	{
		return $this->hasMany(AuthorToken::class, "author_id", "id");
	}

	public function pluginAuditActors()
	{
		return $this->hasMany(PluginAuditLog::class, "actor_author_id", "id");
	}

	public function auditActors()
	{
		return $this->hasMany(AuditLog::class, "actor_author_id", "id");
	}
}
