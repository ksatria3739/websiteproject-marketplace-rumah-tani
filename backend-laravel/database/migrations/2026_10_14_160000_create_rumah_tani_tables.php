<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Regions
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('province');
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->timestamps();
        });

        // 2. Modify or update users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('password');
            $table->enum('role', ['super_admin', 'admin_tengkulak', 'petani', 'pembeli'])->default('pembeli')->after('phone');
            $table->enum('buyer_type', ['b2b', 'b2c'])->nullable()->after('role');
            $table->string('company_name')->nullable()->after('buyer_type');
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete()->after('company_name');
            $table->text('address')->nullable()->after('region_id');
            $table->enum('kyc_status', ['unsubmitted', 'pending', 'approved', 'rejected'])->default('unsubmitted')->after('address');
            $table->text('kyc_note')->nullable()->after('kyc_status');
            $table->boolean('is_active')->default(true)->after('kyc_note');
            $table->integer('token_version')->default(0)->after('is_active');
        });

        // 3. KYC Documents
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('doc_type'); // ktp, npwp, nib, selfie, pendukung
            $table->string('file_path');
            $table->timestamps();
        });

        // 4. Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // 5. Commodities
        Schema::create('commodities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->enum('curation', ['draft', 'pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('curated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('curation_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['curation', 'is_active', 'region_id', 'category_id']);
        });

        // 6. Variants (SKU & Real-time Stock)
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commodity_id')->constrained('commodities')->cascadeOnDelete();
            $table->string('variant_name');
            $table->enum('grade', ['A', 'B', 'C']);
            $table->string('unit')->default('kg');
            $table->decimal('price', 14, 2);
            $table->decimal('stock_qty', 12, 2)->default(0);
            $table->decimal('reserved_qty', 12, 2)->default(0);
            $table->decimal('min_order', 12, 2)->default(1);
            $table->decimal('weight_per_unit_kg', 8, 3)->default(1.000);
            $table->integer('version')->default(0);
            $table->timestamps();

            $table->unique(['commodity_id', 'variant_name', 'grade']);
        });

        // 7. Stock Movements (Audit Ledger for real-time tracking)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('variants')->cascadeOnDelete();
            $table->decimal('delta', 12, 2);
            $table->string('reason'); // harvest, adjust, reserve, release, sale
            $table->string('ref_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['variant_id', 'created_at']);
        });

        // 8. Negotiations (B2B Price Bidding)
        Schema::create('negotiations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->cascadeOnDelete();
            $table->decimal('qty', 12, 2);
            $table->enum('status', ['open', 'accepted', 'rejected', 'expired', 'cancelled', 'ordered'])->default('open');
            $table->decimal('agreed_price', 14, 2)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('negotiation_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negotiation_id')->constrained('negotiations')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('unit_price', 14, 2);
            $table->text('message')->nullable();
            $table->timestamps();
        });

        // 9. Cart Items
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->cascadeOnDelete();
            $table->decimal('qty', 12, 2);
            $table->foreignId('negotiation_id')->nullable()->constrained('negotiations')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'variant_id']);
        });

        // 10. Payments & Multi-Vendor Orders
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('gateway_ref')->unique();
            $table->string('method'); // va_bca, va_bni, gopay, ovo, bank_transfer
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])->default('pending');
            $table->string('pay_code')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_webhook')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('dest_region_id')->constrained('regions')->cascadeOnDelete();
            $table->text('ship_address');
            $table->enum('status', ['pending_payment', 'paid', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'expired'])->default('pending_payment');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax', 14, 2);
            $table->decimal('admin_fee', 14, 2);
            $table->decimal('shipping_cost', 14, 2);
            $table->decimal('total', 14, 2);
            $table->decimal('total_weight_kg', 12, 2);
            $table->timestamps();

            $table->index(['buyer_id', 'created_at']);
            $table->index(['seller_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->cascadeOnDelete();
            $table->string('name_snapshot');
            $table->enum('grade', ['A', 'B', 'C']);
            $table->decimal('qty', 12, 2);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        // 11. Logistics & Armada Wilayah
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete(); // Admin / Tengkulak
            $table->string('plate')->unique();
            $table->string('type');
            $table->decimal('capacity_kg', 10, 2);
            $table->string('driver_name')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('origin_region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('dest_region_id')->constrained('regions')->cascadeOnDelete();
            $table->decimal('base_fee', 14, 2)->default(0);
            $table->decimal('fee_per_kg_km', 10, 4)->default(0);
            $table->timestamps();

            $table->unique(['owner_id', 'origin_region_id', 'dest_region_id']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
            $table->enum('status', ['preparing', 'picked_up', 'in_transit', 'arrived_hub', 'out_for_delivery', 'delivered'])->default('preparing');
            $table->string('tracking_code')->unique();
            $table->decimal('last_lat', 9, 6)->nullable();
            $table->decimal('last_lng', 9, 6)->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('status');
            $table->text('note')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->timestamps();
        });

        // 12. Articles (CMS/Berita)
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('cover_url')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // 13. System Settings & Audit Logs
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('entity')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('negotiation_offers');
        Schema::dropIfExists('negotiations');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('variants');
        Schema::dropIfExists('commodities');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('kyc_documents');
        Schema::dropIfExists('regions');
    }
};
