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
		Schema::create("scpl_audit_logs", function (Blueprint $table) {
			$table->id();
			$table->string("actor")->nullable();
			$table
				->foreignId("actor_author_id")
				->nullable()
				->constrained("authors", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("action");
			$table->json("context")->nullable();
			$table->timestamp("created_at")->useCurrent();
			$table->index("actor_author_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_audit_logs");
	}
};
