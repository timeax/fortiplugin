<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $unique_key
 * @property string|null $owner_ref
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, PluginToken::class> $tokens
 * @property \Illuminate\Support\Collection<int, PluginSignature::class> $signatures
 * @property \Illuminate\Support\Collection<int, PluginZip::class> $zips
 * @property Plugin::class $plugin
 */
class PluginPlaceholder extends Model
{
	protected $table = "scpl_placeholders";

	protected $fillable = ["slug", "name", "unique_key", "owner_ref", "meta"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function tokens()
	{
		return $this->hasMany(
			PluginToken::class,
			"plugin_placeholder_id",
			"id",
		);
	}

	public function signatures()
	{
		return $this->hasMany(PluginSignature::class, "placeholder_id", "id");
	}

	public function zips()
	{
		return $this->hasMany(PluginZip::class, "placeholder_id", "id");
	}

	public function plugin()
	{
		return $this->hasOne(Plugin::class, "plugin_placeholder_id", "id");
	}
}
