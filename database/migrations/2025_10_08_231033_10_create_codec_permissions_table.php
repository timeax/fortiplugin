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
		Schema::create("scpl_codec_permissions", function (Blueprint $table) {
			$table->id();
			$table
				->string("natural_key")
				->comment(
					"Deterministic natural key (e.g., hash of allowed+access)",
				);
			$table->string("module")->default("codec");
			$table->json("allowed")->nullable();
			$table->boolean("access")->default(false);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_codec_permissions");
	}
};
