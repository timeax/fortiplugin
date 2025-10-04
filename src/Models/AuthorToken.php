<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $author_id
 * @property string $token_hash
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $last_used
 * @property bool $revoked
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Author::class $author
 */
class AuthorToken extends Model
{
	protected $table = "scpl_author_tokens";

	protected $fillable = [
		"author_id",
		"token_hash",
		"expires_at",
		"last_used",
		"revoked",
		"meta",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"expires_at" => "datetime",
		"last_used" => "datetime",
		"meta" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function author()
	{
		return $this->belongsTo(Author::class, "author_id", "id");
	}
}
