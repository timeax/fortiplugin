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
		Schema::create("scpl_plugin_notification_permissions", function (
			Blueprint $table,
		) {
			$table->id();
			$table
				->foreignId("tag_id")
				->constrained("tags", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("channel");
			$table->boolean("send")->default(false);
			$table->boolean("receive")->default(false);
			$table->boolean("limited")->default(false);
			$table->string("limit_type")->nullable();
			$table->string("limit_value")->nullable();
			$table->timestamps();
			$table->index("tag_id");
			$table->unique(["tag_id", "channel"]);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_notification_permissions");
	}
};
