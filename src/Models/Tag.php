<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\PluginStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_system
 * @property PluginStatus::class $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, Plugin::class> $plugins
 * @property \Illuminate\Support\Collection<int, PluginNotificationPermission::class> $pluginNotificationPermissions
 * @property \Illuminate\Support\Collection<int, PluginModulePermission::class> $pluginModulePermissions
 * @property \Illuminate\Support\Collection<int, PluginFilePermission::class> $pluginFilePermissions
 * @property \Illuminate\Support\Collection<int, PluginDbPermission::class> $pluginDbPermissions
 */
class Tag extends Model
{
	protected $table = "scpl_tags";

	protected $fillable = ["name", "is_system", "status"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"status" => PluginStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugins()
	{
		return $this->hasMany(Plugin::class, "tag_id", "id");
	}

	public function pluginNotificationPermissions()
	{
		return $this->hasMany(
			PluginNotificationPermission::class,
			"tag_id",
			"id",
		);
	}

	public function pluginModulePermissions()
	{
		return $this->hasMany(PluginModulePermission::class, "tag_id", "id");
	}

	public function pluginFilePermissions()
	{
		return $this->hasMany(PluginFilePermission::class, "tag_id", "id");
	}

	public function pluginDbPermissions()
	{
		return $this->hasMany(PluginDbPermission::class, "tag_id", "id");
	}
}
