<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string|null $actor
 * @property int|null $actor_author_id
 * @property string $type
 * @property string $action
 * @property string $resource
 * @property array|null $context
 * @property \Carbon\Carbon $created_at
 * @property Plugin::class $plugin
 * @property Author::class $actorAuthor
 */
class PluginAuditLog extends Model
{
	protected $table = "scpl_plugin_audit_logs";

	protected $fillable = [
		"plugin_id",
		"actor",
		"actor_author_id",
		"type",
		"action",
		"resource",
		"context",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"context" => AsArrayObject::class,
		"created_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}

	public function actorAuthor()
	{
		return $this->belongsTo(Author::class, "actor_author_id", "id");
	}
}
