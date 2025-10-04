<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\PluginSettingValueType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string $key
 * @property string $value
 * @property PluginSettingValueType::class $type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginSetting extends Model
{
	protected $table = "scpl_plugin_settings";

	protected $fillable = ["plugin_id", "key", "value", "type"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"type" => PluginSettingValueType::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
