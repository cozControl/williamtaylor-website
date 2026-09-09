<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->boolean('delivery_managed')->default(false);
            $table->string('delivery_eyebrow', 120)->default('Dar es Salaam');
            $table->string('delivery_heading', 160)->default('Complimentary Delivery in Dar es Salaam');
            $table->string('delivery_cta_label', 80)->default('Shop with Confidence');
            $table->string('delivery_destination', 40)->default('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_heroes', fn (Blueprint $table) => $table->dropColumn(['delivery_managed', 'delivery_eyebrow', 'delivery_heading', 'delivery_cta_label', 'delivery_destination']));
    }
};
