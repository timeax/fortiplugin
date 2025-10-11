<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $natural_key
 * @property string $module
 * @property array|null $allowed
 * @property bool $access
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class CodecPermission extends Model
{
	protected $table = "scpl_codec_permissions";

	protected $fillable = ["natural_key", "module", "allowed", "access"];

	protected $guarded = ["id", "natural_key", "id", "natural_key"];

	protected $casts = [
		"allowed" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];
}
