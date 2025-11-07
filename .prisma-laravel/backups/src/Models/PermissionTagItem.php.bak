<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PermissionType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tag_id
 * @property PermissionType::class $permission_type
 * @property int $permission_id
 * @property array|null $constraints
 * @property array|null $audit
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PermissionTag::class $tag
 */
class PermissionTagItem extends Model
{
	protected $table = "scpl_permission_tag_items";

	protected $fillable = [
		"tag_id",
		"permission_type",
		"permission_id",
		"constraints",
		"audit",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"permission_type" => PermissionType::class,
		"constraints" => AsArrayObject::class,
		"audit" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function tag()
	{
		return $this->belongsTo(PermissionTag::class, "tag_id", "id");
	}
}
