<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->date('estimated_delivery_date')->nullable()->after('ends_at');
            $table->index(['campaign_type', 'estimated_delivery_date'], 'camp_type_delivery_idx');
        });

        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->boolean('future_style_managed')->default(false);
            $table->string('future_style_eyebrow', 120)->default('Exclusive Access');
            $table->string('future_style_heading', 160)->default('The Future of Style');
            $table->string('future_style_intro', 320)->default('Reserve exclusive pieces before they launch. Limited quantities. Reserve yours today.');
            $table->string('future_style_cta_label', 80)->default('View All Pre-Orders');
            $table->ulid('future_style_campaign_1_id')->nullable();
            $table->ulid('future_style_campaign_2_id')->nullable();
            $table->foreign('future_style_campaign_1_id', 'home_future_campaign_1_fk')->references('id')->on('campaigns')->nullOnDelete();
            $table->foreign('future_style_campaign_2_id', 'home_future_campaign_2_fk')->references('id')->on('campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->dropForeign('home_future_campaign_1_fk');
            $table->dropForeign('home_future_campaign_2_fk');
            $table->dropColumn(['future_style_managed', 'future_style_eyebrow', 'future_style_heading', 'future_style_intro', 'future_style_cta_label', 'future_style_campaign_1_id', 'future_style_campaign_2_id']);
        });
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropIndex('camp_type_delivery_idx');
            $table->dropColumn('estimated_delivery_date');
        });
    }
};
