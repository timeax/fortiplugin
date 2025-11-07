<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\RoutePermissionStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string $route_id
 * @property RoutePermissionStatus::class $status
 * @property string|null $guard
 * @property array|null $meta
 * @property \Carbon\Carbon|null $approved_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginRoutePermission extends Model
{
	protected $table = "scpl_plugin_route_permissions";

	protected $fillable = [
		"plugin_id",
		"route_id",
		"status",
		"guard",
		"meta",
		"approved_at",
	];

	protected $casts = [
		"status" => RoutePermissionStatus::class,
		"meta" => AsArrayObject::class,
		"approved_at" => "datetime",
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
