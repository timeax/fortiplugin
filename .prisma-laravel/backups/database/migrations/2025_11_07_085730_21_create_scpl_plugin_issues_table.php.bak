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
		Schema::create("scpl_plugin_issues", static function (
			Blueprint $table,
		) {
			$table->id();
			$table
				->foreignId("plugin_id")
				->constrained("scpl_plugins", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table
				->foreignId("reporter_id")
				->constrained("scpl_authors", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("type");
			$table->text("description");
			$table
				->enum("status", [
					"open",
					"triage",
					"in_progress",
					"resolved",
					"rejected",
					"closed",
				])
				->default("open");
			$table
				->string("severity")
				->nullable()
				->comment("optional (low|med|high|critical or free-form)");
			$table->json("meta")->nullable();
			$table->timestamps();
			$table->index("plugin_id");
			$table->index("reporter_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_issues");
	}
};
