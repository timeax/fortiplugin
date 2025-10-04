<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\IssueStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property int $reporter_id
 * @property string $type
 * @property string $description
 * @property IssueStatus::class $status
 * @property string|null $severity
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 * @property Author::class $reporter
 * @property \Illuminate\Support\Collection<int, PluginIssueMessage::class> $messages
 */
class PluginIssue extends Model
{
	protected $table = "scpl_plugin_issues";

	protected $fillable = [
		"plugin_id",
		"reporter_id",
		"type",
		"description",
		"status",
		"severity",
		"meta",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"status" => IssueStatus::class,
		"meta" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}

	public function reporter()
	{
		return $this->belongsTo(Author::class, "reporter_id", "id");
	}

	public function messages()
	{
		return $this->hasMany(PluginIssueMessage::class, "issue_id", "id");
	}
}
