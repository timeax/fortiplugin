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
		Schema::create("scpl_plugin_issue_messages", static function (
			Blueprint $table,
		) {
			$table->id();
			$table
				->foreignId("issue_id")
				->constrained("scpl_plugin_issues", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table
				->foreignId("author_id")
				->constrained("scpl_authors", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->text("message");
			$table->timestamps();
			$table->index("issue_id");
			$table->index("author_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_issue_messages");
	}
};
