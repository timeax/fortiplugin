<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $natural_key
 * @property string $channel
 * @property array $permissions
 * @property array|null $templates_allowed
 * @property array|null $recipients_allowed
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class NotificationPermission extends Model
{
	protected $table = "scpl_notification_permissions";

	protected $fillable = [
		"natural_key",
		"channel",
		"templates_allowed",
		"recipients_allowed",
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
		"templates_allowed" => AsArrayObject::class,
		"recipients_allowed" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];
}
