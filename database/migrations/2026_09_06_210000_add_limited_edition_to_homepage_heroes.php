<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->boolean('limited_edition_managed')->default(false);
            $table->string('limited_edition_eyebrow', 120)->default('Exclusive');
            $table->string('limited_edition_heading', 160)->default('LIMITED EDITION');
            $table->string('limited_edition_cta_label', 80)->default('View All Limited Editions');
            foreach ([1, 2, 3] as $position) {
                $table->ulid("limited_edition_campaign_{$position}_id")->nullable();
                $table->foreign("limited_edition_campaign_{$position}_id", "home_limited_campaign_{$position}_fk")->references('id')->on('campaigns')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            foreach ([1, 2, 3] as $position) {
                $table->dropForeign("home_limited_campaign_{$position}_fk");
            }
            $table->dropColumn(['limited_edition_managed', 'limited_edition_eyebrow', 'limited_edition_heading', 'limited_edition_cta_label', 'limited_edition_campaign_1_id', 'limited_edition_campaign_2_id', 'limited_edition_campaign_3_id']);
        });
    }
};
