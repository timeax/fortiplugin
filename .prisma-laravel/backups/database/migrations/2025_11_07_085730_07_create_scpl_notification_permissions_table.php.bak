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
		Schema::create("scpl_notification_permissions", static function (
			Blueprint $table,
		) {
			$table->id();
			$table
				->string("natural_key")
				->unique()
				->comment(
					"Deterministic natural key (e.g., hash of channel/templates/recipients/action-set)",
				);
			$table->string("channel");
			$table->json("permissions");
			$table->json("templates_allowed")->nullable();
			$table->json("recipients_allowed")->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_notification_permissions");
	}
};
