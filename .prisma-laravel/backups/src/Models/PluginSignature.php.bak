<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $placeholder_id
 * @property string $host_domain
 * @property string $owner_host
 * @property string $plugin_key
 * @property string $signature
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 */
class PluginSignature extends Model
{
	protected $table = "scpl_plugin_signatures";

	protected $fillable = [
		"placeholder_id",
		"host_domain",
		"owner_host",
		"plugin_key",
		"signature",
	];

	protected $guarded = ["id", "id"];

	protected $casts = ["created_at" => "datetime", "updated_at" => "datetime"];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"placeholder_id",
			"id",
		);
	}
}
