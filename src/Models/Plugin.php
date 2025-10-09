<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $image
 * @property PluginStatus::class $status
 * @property array|null $config
 * @property array|null $meta
 * @property int $plugin_placeholder_id
 * @property string|null $owner_ref
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property \Illuminate\Support\Collection<int, PluginSetting::class> $plugin_settings
 * @property \Illuminate\Support\Collection<int, PluginVersion::class> $plugin_versions
 * @property \Illuminate\Support\Collection<int, PluginAuditLog::class> $logs
 * @property \Illuminate\Support\Collection<int, Author::class> $authors
 * @property \Illuminate\Support\Collection<int, PluginIssue::class> $issues
 * @property \Illuminate\Support\Collection<int, PluginPermission::class> $plugin_permissions
 * @property \Illuminate\Support\Collection<int, PluginPermissionTag::class> $permission_tags
 * @property \Illuminate\Support\Collection<int, PluginRoutePermission::class> $routes
 */
class Plugin extends Model
{
	protected $table = "scpl_plugins";

	protected $casts = [
		"status" => PluginStatus::class,
		"config" => AsArrayObject::class,
		"meta" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"plugin_placeholder_id",
			"id",
		);
	}

	public function plugin_settings()
	{
		return $this->hasMany(PluginSetting::class, "plugin_id", "id");
	}

	public function plugin_versions()
	{
		return $this->hasMany(PluginVersion::class, "plugin_id", "id");
	}

	public function logs()
	{
		return $this->hasMany(PluginAuditLog::class, "plugin_id", "id");
	}

	public function authors()
	{
		return $this->belongsToMany(
			Author::class,
			"plugin_author",
			"plugin_id",
			"author_id",
			"id",
			"id",
		); // pivot: plugin_author
	}

	public function issues()
	{
		return $this->hasMany(PluginIssue::class, "plugin_id", "id");
	}

	public function plugin_permissions()
	{
		return $this->hasMany(PluginPermission::class, "plugin_id", "id");
	}

	public function permission_tags()
	{
		return $this->hasMany(PluginPermissionTag::class, "plugin_id", "id");
	}

	public function routes()
	{
		return $this->hasMany(PluginRoutePermission::class, "plugin_id", "id");
	}
}
