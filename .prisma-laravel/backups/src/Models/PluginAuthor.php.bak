<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\AuthorRole;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $plugin_id
 * @property int $author_id
 * @property AuthorRole::class $role
 * @property \Carbon\Carbon $created_at
 * @property Plugin::class $plugin
 * @property Author::class $author
 */
class PluginAuthor extends Model
{
	protected $table = "scpl_plugin_author";

	protected $fillable = ["plugin_id", "author_id", "role"];

	protected $guarded = ["plugin_id", "author_id", "plugin_id", "author_id"];

	protected $casts = [
		"role" => AuthorRole::class,
		"created_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}

	public function author()
	{
		return $this->belongsTo(Author::class, "author_id", "id");
	}
}
