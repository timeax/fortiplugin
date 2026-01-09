<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\ProcessType;
use Timeax\FortiPlugin\Enums\ProcessStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $source_id
 * @property ProcessType::class $type
 * @property ProcessStatus::class $status
 * @property string $run_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class PluginProcess extends Model
{
	protected $table = "scpl_PluginProcess";

	protected $guarded = [];

	protected $casts = [
		"type" => ProcessType::class,
		"status" => ProcessStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];
}
