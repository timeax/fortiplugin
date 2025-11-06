<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $issue_id
 * @property int $author_id
 * @property string $message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginIssue::class $issue
 * @property Author::class $author
 */
class PluginIssueMessage extends Model
{
	protected $table = "scpl_plugin_issue_messages";

	protected $fillable = ["issue_id", "author_id", "message"];

	protected $guarded = ["id", "id"];

	protected $casts = ["created_at" => "datetime", "updated_at" => "datetime"];

	public function issue()
	{
		return $this->belongsTo(PluginIssue::class, "issue_id", "id");
	}

	public function author()
	{
		return $this->belongsTo(Author::class, "author_id", "id");
	}
}
