<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property int $tag_id
 * @property bool $active
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 * @property PermissionTag::class $tag
 */
class PluginPermissionTag extends Model
{
	protected $table = "scpl_plugin_permission_tags";

	protected $fillable = [
		"plugin_id",
		"tag_id",
		"active",
		"limited",
		"limit_type",
		"limit_value",
	];

	protected $guarded = ["id", "id"];

	protected $casts = ["created_at" => "datetime", "updated_at" => "datetime"];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}

	public function tag()
	{
		return $this->belongsTo(PermissionTag::class, "tag_id", "id");
	}
}
