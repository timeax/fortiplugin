<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tag_id
 * @property string $model
 * @property bool $select
 * @property bool $insert
 * @property bool $update
 * @property bool $grouped_queries
 * @property bool $truncate
 * @property bool $delete
 * @property array|null $hidden_fields
 * @property array|null $writable_fields
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Tag::class $tag
 */
class PluginDbPermission extends Model
{
	protected $table = "scpl_plugin_db_permissions";

	protected $fillable = [
		"tag_id",
		"model",
		"select",
		"insert",
		"update",
		"grouped_queries",
		"truncate",
		"delete",
		"hidden_fields",
		"writable_fields",
		"limited",
		"limit_type",
		"limit_value",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"hidden_fields" => AsArrayObject::class,
		"writable_fields" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function tag()
	{
		return $this->belongsTo(Tag::class, "tag_id", "id");
	}
}
