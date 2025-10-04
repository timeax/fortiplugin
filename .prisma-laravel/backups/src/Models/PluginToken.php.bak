<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_placeholder_id
 * @property string $token_hash
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $last_used
 * @property bool $revoked
 * @property int|null $author_id
 * @property \Carbon\Carbon $created_at
 * @property PluginPlaceholder::class $placeholder
 * @property Author::class $author
 */
class PluginToken extends Model
{
	protected $table = "scpl_plugin_tokens";

	protected $fillable = [
		"plugin_placeholder_id",
		"token_hash",
		"expires_at",
		"last_used",
		"revoked",
		"author_id",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"expires_at" => "datetime",
		"last_used" => "datetime",
		"created_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"plugin_placeholder_id",
			"id",
		);
	}

	public function author()
	{
		return $this->belongsTo(Author::class, "author_id", "id");
	}
}
