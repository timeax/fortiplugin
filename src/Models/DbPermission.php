<?php

namespace Timeax\FortiPlugin\Models;

use Carbon\Carbon;
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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DbPermission extends Model
{
	protected $table = "scpl_db_permissions";

	protected $fillable = [
		"natural_key",
		"model",
		"table",
		"permissions",
		"readable_columns",
		"writable_columns",
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
