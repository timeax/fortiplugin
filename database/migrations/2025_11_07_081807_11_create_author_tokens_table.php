<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create("author_tokens", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("author_id")
				->constrained("authors", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("token_hash")->unique();
			$table->timestamp("expires_at");
			$table->timestamp("last_used")->nullable();
			$table->boolean("revoked")->default(false);
			$table
				->json("meta")
				->nullable()
				->comment(
					"e.g. { \"scopes\": [\"forti-packager-fetch-policy\"] }",
				);
			$table->timestamps();
			$table->index("author_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("author_tokens");
	}
};
