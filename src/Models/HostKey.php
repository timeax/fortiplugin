<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\KeyPurpose;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property KeyPurpose::class $purpose
 * @property string $public_pem
 * @property string|null $private_pem
 * @property string $fingerprint
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $rotated_at
 */
class HostKey extends Model
{
	protected $table = "scpl_host_keys";

	protected $fillable = [
		"purpose",
		"public_pem",
		"private_pem",
		"fingerprint",
		"created_at",
		"rotated_at",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"purpose" => KeyPurpose::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
		"rotated_at" => "datetime",
	];
}
