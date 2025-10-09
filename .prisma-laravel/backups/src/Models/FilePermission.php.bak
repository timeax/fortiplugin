<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $natural_key
 * @property string $base_dir
 * @property array $paths
 * @property array $permissions
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FilePermission extends Model
{
	protected $table = "scpl_file_permissions";

	protected $fillable = [
		"natural_key",
		"base_dir",
		"paths",
		"limited",
		"limit_type",
		"limit_value",
	];

	protected $guarded = [
		"id",
		"permissions",
		"natural_key",
		"id",
		"natural_key",
		"permissions",
	];

	protected $casts = [
		"paths" => AsArrayObject::class,
		"permissions" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];
}
