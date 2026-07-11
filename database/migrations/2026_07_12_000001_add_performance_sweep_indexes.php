<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance sweep (Phase 3): indexes for query shapes confirmed hot by a
 * grounded code audit but never indexed (see PREMIUM_GRADE_MASTER_WORKFLOW.md
 * §5s). Every add is guarded with hasIndex() so a resumed/re-run migration
 * never throws (rule #42).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hit on every car-model page + up to 7x per filtered OEM search
        // (SearchService's whereHas('carModels', ...) queries) — the pivot
        // only has a PK leading with product_id, not car_model_id.
        if (! Schema::hasIndex('product_car_models', ['car_model_id', 'product_id'])) {
            Schema::table('product_car_models', function (Blueprint $table) {
                $table->index(['car_model_id', 'product_id']);
            });
        }

        // Hit on every live Airwallex webhook delivery.
        if (! Schema::hasIndex('payments', ['transaction_id'])) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index('transaction_id');
            });
        }

        // Hit on every public car-model SEO page (CarModelController::show()).
        if (! Schema::hasIndex('car_models', ['slug'])) {
            Schema::table('car_models', function (Blueprint $table) {
                $table->index('slug');
            });
        }

        // Hit on every homepage cache-miss (SectionRendererService::getSections()).
        if (! Schema::hasIndex('sections', ['location'])) {
            Schema::table('sections', function (Blueprint $table) {
                $table->index('location');
            });
        }

        // Admin audit-trail queries filter+sort on both columns together —
        // single-column indexes can't serve a filter+sort pair.
        if (! Schema::hasIndex('activity_logs', ['admin_id', 'created_at'])) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->index(['admin_id', 'created_at']);
            });
        }

        // Admin order list date-range + status filters, combined.
        if (! Schema::hasIndex('orders', ['status', 'created_at'])) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['status', 'created_at']);
            });
        }

        // The actual OTP verification lookup shape (email+purpose+expires_at
        // together); each column is currently indexed only separately.
        if (! Schema::hasIndex('otps', ['email', 'purpose', 'expires_at'])) {
            Schema::table('otps', function (Blueprint $table) {
                $table->index(['email', 'purpose', 'expires_at']);
            });
        }

        // Abandoned-cart recovery cron filters both columns together.
        if (! Schema::hasIndex('abandoned_carts', ['recovery_email_sent', 'last_active_at'])) {
            Schema::table('abandoned_carts', function (Blueprint $table) {
                $table->index(['recovery_email_sent', 'last_active_at']);
            });
        }

        // Import/inventory history listings sort by created_at as they grow.
        if (! Schema::hasIndex('bulk_update_logs', ['created_at'])) {
            Schema::table('bulk_update_logs', function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        if (! Schema::hasIndex('inventory_logs', ['created_at'])) {
            Schema::table('inventory_logs', function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        // Morph-style linkage, pre-emptive ahead of "emails for order X" lookups.
        if (! Schema::hasIndex('email_logs', ['related_id', 'related_type'])) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->index(['related_id', 'related_type']);
            });
        }

        // Filtered on every manufacturer-list cache-miss.
        if (! Schema::hasIndex('manufacturers', ['is_active'])) {
            Schema::table('manufacturers', function (Blueprint $table) {
                $table->index('is_active');
            });
        }

        // Homepage blog_preview section filters+sorts by both on every load.
        if (! Schema::hasIndex('blog_posts', ['status', 'published_at'])) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $table->index(['status', 'published_at']);
            });
        }

        // Inconsistent with the table's other columns (status/email/ip_address
        // are all indexed) — user_id was left as a plain unsignedBigInteger.
        if (! Schema::hasIndex('login_logs', ['user_id'])) {
            Schema::table('login_logs', function (Blueprint $table) {
                $table->index('user_id');
            });
        }

        // search_logs already indexes the identical column; failed_search_logs
        // didn't get the same treatment.
        if (! Schema::hasIndex('failed_search_logs', ['normalized_query'])) {
            Schema::table('failed_search_logs', function (Blueprint $table) {
                $table->index('normalized_query');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('product_car_models', ['car_model_id', 'product_id'])) {
            // MySQL requires SOME leftmost index on car_model_id to enforce its
            // FK constraint, and adopted this composite one for that role (the
            // pivot's only other index is the (product_id, car_model_id) PK,
            // which doesn't cover a car_model_id-leading lookup). Restore a
            // plain single-column index first so the drop below doesn't fail
            // with "Cannot drop index ... needed in a foreign key constraint".
            if (! Schema::hasIndex('product_car_models', ['car_model_id'])) {
                Schema::table('product_car_models', fn (Blueprint $table) => $table->index('car_model_id'));
            }
            Schema::table('product_car_models', fn (Blueprint $table) => $table->dropIndex(['car_model_id', 'product_id']));
        }
        if (Schema::hasIndex('payments', ['transaction_id'])) {
            Schema::table('payments', fn (Blueprint $table) => $table->dropIndex(['transaction_id']));
        }
        if (Schema::hasIndex('car_models', ['slug'])) {
            Schema::table('car_models', fn (Blueprint $table) => $table->dropIndex(['slug']));
        }
        if (Schema::hasIndex('sections', ['location'])) {
            Schema::table('sections', fn (Blueprint $table) => $table->dropIndex(['location']));
        }
        if (Schema::hasIndex('activity_logs', ['admin_id', 'created_at'])) {
            // Same FK-index dependency as product_car_models above: admin_id
            // is FK-constrained and had no other leftmost index before this
            // migration, so MySQL adopted this composite for the constraint.
            if (! Schema::hasIndex('activity_logs', ['admin_id'])) {
                Schema::table('activity_logs', fn (Blueprint $table) => $table->index('admin_id'));
            }
            Schema::table('activity_logs', fn (Blueprint $table) => $table->dropIndex(['admin_id', 'created_at']));
        }
        if (Schema::hasIndex('orders', ['status', 'created_at'])) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropIndex(['status', 'created_at']));
        }
        if (Schema::hasIndex('otps', ['email', 'purpose', 'expires_at'])) {
            Schema::table('otps', fn (Blueprint $table) => $table->dropIndex(['email', 'purpose', 'expires_at']));
        }
        if (Schema::hasIndex('abandoned_carts', ['recovery_email_sent', 'last_active_at'])) {
            Schema::table('abandoned_carts', fn (Blueprint $table) => $table->dropIndex(['recovery_email_sent', 'last_active_at']));
        }
        if (Schema::hasIndex('bulk_update_logs', ['created_at'])) {
            Schema::table('bulk_update_logs', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        }
        if (Schema::hasIndex('inventory_logs', ['created_at'])) {
            Schema::table('inventory_logs', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        }
        if (Schema::hasIndex('email_logs', ['related_id', 'related_type'])) {
            Schema::table('email_logs', fn (Blueprint $table) => $table->dropIndex(['related_id', 'related_type']));
        }
        if (Schema::hasIndex('manufacturers', ['is_active'])) {
            Schema::table('manufacturers', fn (Blueprint $table) => $table->dropIndex(['is_active']));
        }
        if (Schema::hasIndex('blog_posts', ['status', 'published_at'])) {
            Schema::table('blog_posts', fn (Blueprint $table) => $table->dropIndex(['status', 'published_at']));
        }
        if (Schema::hasIndex('login_logs', ['user_id'])) {
            Schema::table('login_logs', fn (Blueprint $table) => $table->dropIndex(['user_id']));
        }
        if (Schema::hasIndex('failed_search_logs', ['normalized_query'])) {
            Schema::table('failed_search_logs', fn (Blueprint $table) => $table->dropIndex(['normalized_query']));
        }
    }
};
