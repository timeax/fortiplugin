<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\PluginStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property PluginStatus::class $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, PluginPermissionTag::class> $plugins
 * @property \Illuminate\Support\Collection<int, PermissionTagItem::class> $items
 */
class PermissionTag extends Model
{
	protected $table = "scpl_permission_tags";

	protected $fillable = ["name", "description", "is_system", "status"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"status" => PluginStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugins()
	{
		return $this->hasMany(PluginPermissionTag::class, "tag_id", "id");
	}

	public function items()
	{
		return $this->hasMany(PermissionTagItem::class, "tag_id", "id");
	}
}
