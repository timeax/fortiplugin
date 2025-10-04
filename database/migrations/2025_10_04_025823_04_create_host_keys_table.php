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
		Schema::create("scpl_host_keys", function (Blueprint $table) {
			$table->id();
			$table->enum("purpose", ["packager_sign", "installer_verify"]);
			$table->text("public_pem");
			$table->text("private_pem")->nullable();
			$table->string("fingerprint");
			$table->timestamp("created_at")->useCurrent();
			$table->timestamp("rotated_at")->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_host_keys");
	}
};
