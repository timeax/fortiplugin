<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PermissionType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property PermissionType::class $permission_type
 * @property int $permission_id
 * @property bool $active
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property array|null $constraints
 * @property array|null $audit
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginPermission extends Model
{
	protected $table = "scpl_plugin_permissions";

	protected $fillable = [
		"plugin_id",
		"permission_type",
		"permission_id",
		"active",
		"limited",
		"limit_type",
		"limit_value",
		"constraints",
		"audit",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"permission_type" => PermissionType::class,
		"constraints" => AsArrayObject::class,
		"audit" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
