<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $image
 * @property int $tag_id
 * @property PluginStatus::class $status
 * @property array|null $config
 * @property array|null $meta
 * @property int $plugin_placeholder_id
 * @property string|null $owner_ref
 * @property Tag::class $tag
 * @property PluginPlaceholder::class $placeholder
 * @property \Illuminate\Support\Collection<int, PluginSetting::class> $plugin_settings
 * @property \Illuminate\Support\Collection<int, PluginVersion::class> $plugin_versions
 * @property \Illuminate\Support\Collection<int, PluginAuditLog::class> $logs
 * @property \Illuminate\Support\Collection<int, Author::class> $authors
 * @property \Illuminate\Support\Collection<int, PluginIssue::class> $issues
 */
class Plugin extends Model
{
	protected $table = "scpl_plugins";

	protected $fillable = [
		"name",
		"image",
		"tag_id",
		"status",
		"config",
		"meta",
		"plugin_placeholder_id",
		"owner_ref",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"status" => PluginStatus::class,
		"config" => AsArrayObject::class,
		"meta" => AsArrayObject::class,
	];

	public function tag()
	{
		return $this->belongsTo(Tag::class, "tag_id", "id");
	}

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
}
