<?php

<<<<<<<
<<<<<<<
<<<<<<<
namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Enums\ValidationStatus;

/**
 * @property int $id
 * @property int $placeholder_id
 * @property string $path
 * @property array $meta
 * @property PluginStatus::class $status
 * @property ValidationStatus::class $validation_status
 * @property int|null $uploaded_by_author_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property Author::class $uploadedBy
 */
class PluginZip extends Model
{
	protected $table = "scpl_plugin_zips";

	protected $fillable = [
		"placeholder_id",
		"path",
		"meta",
		"status",
		"validation_status",
		"uploaded_by_author_id",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"status" => PluginStatus::class,
		"validation_status" => ValidationStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"placeholder_id",
			"id",
		);
	}

	public function uploadedBy()
	{
		return $this->belongsTo(Author::class, "uploaded_by_author_id", "id");
	}
}

=======
namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $placeholder_id
 * @property string $path
 * @property array $meta
 * @property PluginStatus::class $status
 * @property ValidationStatus::class $validation_status
 * @property int|null $uploaded_by_author_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property Author::class $uploadedBy
 */
class PluginZip extends Model
{
	protected $table = "scpl_plugin_zips";

	protected $fillable = [
		"placeholder_id",
		"path",
		"meta",
		"status",
		"validation_status",
		"uploaded_by_author_id",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"status" => PluginStatus::class,
		"validation_status" => ValidationStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"placeholder_id",
			"id",
		);
	}

	public function uploadedBy()
	{
		return $this->belongsTo(Author::class, "uploaded_by_author_id", "id");
	}
}

>>>>>>>
=======
namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $placeholder_id
 * @property string $path
 * @property array $meta
 * @property PluginStatus::class $status
 * @property ValidationStatus::class $validation_status
 * @property int|null $uploaded_by_author_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property Author::class $uploadedBy
 */
class PluginZip extends Model
{
	protected $table = "scpl_plugin_zips";

	protected $fillable = [
		"placeholder_id",
		"path",
		"meta",
		"status",
		"validation_status",
		"uploaded_by_author_id",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"status" => PluginStatus::class,
		"validation_status" => ValidationStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"placeholder_id",
			"id",
		);
	}

	public function uploadedBy()
	{
		return $this->belongsTo(Author::class, "uploaded_by_author_id", "id");
	}
}

>>>>>>>
=======
namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $placeholder_id
 * @property string $path
 * @property array $meta
 * @property PluginStatus::class $status
 * @property ValidationStatus::class $validation_status
 * @property int|null $uploaded_by_author_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property Author::class $uploadedBy
 */
class PluginZip extends Model
{
	protected $table = "scpl_plugin_zips";

	protected $fillable = [
		"placeholder_id",
		"path",
		"meta",
		"status",
		"validation_status",
		"uploaded_by_author_id",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"status" => PluginStatus::class,
		"validation_status" => ValidationStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"placeholder_id",
			"id",
		);
	}

	public function uploadedBy()
	{
		return $this->belongsTo(Author::class, "uploaded_by_author_id", "id");
	}
}

>>>>>>>