<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->unsignedInteger('navigation_order')->default(1000)->index();
        });
        foreach (DB::table('collections')->orderByDesc('updated_at')->orderBy('slug')->pluck('id') as $position => $id) {
            DB::table('collections')->where('id', $id)->update(['navigation_order' => $position]);
        }
    }

    public function down(): void
    {
        Schema::table('collections', fn (Blueprint $table) => $table->dropColumn('navigation_order'));
    }
};
