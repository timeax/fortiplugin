<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $natural_key
 * @property string $module
 * @property array $apis
 * @property bool $access
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ModulePermission extends Model
{
	protected $table = "scpl_module_permissions";

	protected $fillable = [
		"natural_key",
		"module",
		"apis",
		"access",
		"limited",
		"limit_type",
		"limit_value",
	];

	protected $guarded = ["id", "natural_key", "id", "natural_key"];

	protected $casts = [
		"apis" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];
}
