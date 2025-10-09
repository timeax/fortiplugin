<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $natural_key
 * @property string|null $label
 * @property bool $access
 * @property array $hosts
 * @property array $methods
 * @property array|null $schemes
 * @property array|null $ports
 * @property array|null $paths
 * @property array|null $headers_allowed
 * @property array|null $ips_allowed
 * @property bool $auth_via_host_secret
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class NetworkPermission extends Model
{
	protected $table = "scpl_network_permissions";

	protected $fillable = [
		"label",
		"access",
		"hosts",
		"methods",
		"schemes",
		"ports",
		"paths",
		"headers_allowed",
		"ips_allowed",
		"auth_via_host_secret",
		"limited",
		"limit_type",
		"limit_value",
	];

	protected $guarded = ["id", "rule_key", "id"];

	protected $casts = [
		"hosts" => AsArrayObject::class,
		"methods" => AsArrayObject::class,
		"schemes" => AsArrayObject::class,
		"ports" => AsArrayObject::class,
		"paths" => AsArrayObject::class,
		"headers_allowed" => AsArrayObject::class,
		"ips_allowed" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];
}
