<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tag_id
 * @property string $channel
 * @property bool $send
 * @property bool $receive
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Tag::class $tag
 */
class PluginNotificationPermission extends Model
{
	protected $table = "scpl_plugin_notification_permissions";

	protected $fillable = [
		"tag_id",
		"channel",
		"send",
		"receive",
		"limited",
		"limit_type",
		"limit_value",
	];

	protected $guarded = ["id", "id"];

	protected $casts = ["created_at" => "datetime", "updated_at" => "datetime"];

	public function tag()
	{
		return $this->belongsTo(Tag::class, "tag_id", "id");
	}
}
