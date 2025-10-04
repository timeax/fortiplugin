<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $actor
 * @property int|null $actor_author_id
 * @property string $action
 * @property array|null $context
 * @property \Carbon\Carbon $created_at
 * @property Author::class $actorAuthor
 */
class AuditLog extends Model
{
	protected $table = "scpl_audit_logs";

	protected $fillable = ["actor", "actor_author_id", "action", "context"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"context" => AsArrayObject::class,
		"created_at" => "datetime",
	];

	public function actorAuthor()
	{
		return $this->belongsTo(Author::class, "actor_author_id", "id");
	}
}
