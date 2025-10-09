<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $natural_key
 * @property string|null $model
 * @property string|null $table
 * @property array $permissions
 * @property array|null $readable_columns
 * @property array|null $writable_columns
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DbPermission extends Model
{
	protected $table = "scpl_db_permissions";

	protected $fillable = [
		"natural_key",
		"model",
		"table",
		"readable_columns",
		"writable_columns",
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
		"permissions" => AsArrayObject::class,
		"readable_columns" => AsArrayObject::class,
		"writable_columns" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];
}
