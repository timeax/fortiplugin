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
		Schema::create("placeholders", function (Blueprint $table) {
			$table->id();
			$table->string("slug")->unique();
			$table->string("name")->unique();
			$table->string("unique_key")->unique();
			$table->string("owner_ref")->nullable();
			$table->json("meta")->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("placeholders");
	}
};
