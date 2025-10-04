<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tag_id
 * @property string $module
 * @property bool $access
 * @property array $permissions
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Tag::class $tag
 */
class PluginModulePermission extends Model
{
	protected $table = "scpl_plugin_module_permissions";

	protected $fillable = [
		"tag_id",
		"module",
		"access",
		"permissions",
		"limited",
		"limit_type",
		"limit_value",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"permissions" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function tag()
	{
		return $this->belongsTo(Tag::class, "tag_id", "id");
	}
}
