<?php

<<<<<<<
namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Timeax\FortiPlugin\Enums\ValidationStatus;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string $version
 * @property string $archive_url
 * @property array|null $manifest
 * @property array|null $validation_report
 * @property ValidationStatus::class $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginVersion extends Model
{
	protected $table = "scpl_plugin_versions";

	protected $fillable = [
		"plugin_id",
		"version",
		"archive_url",
		"manifest",
		"validation_report",
		"status",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"manifest" => AsArrayObject::class,
		"validation_report" => AsArrayObject::class,
		"status" => ValidationStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}

=======
namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string $version
 * @property string $archive_url
 * @property array|null $manifest
 * @property array|null $validation_report
 * @property ValidationStatus::class $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginVersion extends Model
{
	protected $table = "scpl_plugin_versions";

	protected $fillable = [
		"plugin_id",
		"version",
		"archive_url",
		"manifest",
		"validation_report",
		"status",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"manifest" => AsArrayObject::class,
		"validation_report" => AsArrayObject::class,
		"status" => ValidationStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}

>>>>>>>