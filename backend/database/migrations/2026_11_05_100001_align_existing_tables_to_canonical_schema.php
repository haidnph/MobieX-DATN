<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align existing tables with the MobieX Unified Canonical Database Schema v3.1.
     */
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // 1. Roles table alignment
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('description');
            }
        });

        // 2. Permissions table alignment
        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
        });

        // 3. User Addresses table alignment
        if ($isMysql) {
            // Drop ON DELETE CASCADE foreign key first as MySQL rejects stored generated columns on cascade parent keys
            $this->safelyExecuteSql('ALTER TABLE `user_addresses` DROP FOREIGN KEY `user_addresses_user_id_foreign`');
        }

        Schema::table('user_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('user_addresses', 'province_code')) {
                $table->string('province_code', 20)->default('')->after('phone');
            }
            if (! Schema::hasColumn('user_addresses', 'province_name')) {
                $table->string('province_name', 100)->default('')->after('province_code');
            }
            if (! Schema::hasColumn('user_addresses', 'ward_code')) {
                $table->string('ward_code', 20)->default('')->after('province_name');
            }
            if (! Schema::hasColumn('user_addresses', 'ward_name')) {
                $table->string('ward_name', 100)->default('')->after('ward_code');
            }
            if (Schema::hasColumn('user_addresses', 'city')) {
                $table->string('city', 100)->nullable()->change();
            }
            if (! Schema::hasColumn('user_addresses', 'default_user_id')) {
                $table->unsignedBigInteger('default_user_id')
                    ->storedAs('CASE WHEN is_default = 1 THEN user_id ELSE NULL END')
                    ->nullable()
                    ->after('is_default');
            }
        });

        if ($isMysql) {
            $this->safelyExecuteSql('ALTER TABLE `user_addresses` ADD CONSTRAINT `fk_user_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)');
        }

        // 4. Categories table alignment
        if ($isMysql) {
            // Drop ON DELETE SET NULL FK first so stored generated column can reference parent_id
            $this->safelyExecuteSql('ALTER TABLE `categories` DROP FOREIGN KEY `categories_parent_id_foreign`');
        }

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'image_path')) {
                $table->string('image_path', 255)->nullable()->after('description');
            }
            if (! Schema::hasColumn('categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
            if (! Schema::hasColumn('categories', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('categories', 'featured_sort_order')) {
                $table->unsignedInteger('featured_sort_order')->default(0)->after('is_featured');
            }
            if (! Schema::hasColumn('categories', 'deleted_at')) {
                $table->softDeletes()->after('featured_sort_order');
            }
            if (! Schema::hasColumn('categories', 'parent_scope_id')) {
                $table->unsignedBigInteger('parent_scope_id')
                    ->storedAs('COALESCE(parent_id, 0)')
                    ->after('deleted_at');
            }
        });

        if ($isMysql) {
            $this->safelyExecuteSql('ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`)');
        }

        // 5. Brands table alignment
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'logo_path')) {
                $table->string('logo_path', 255)->nullable()->after('slug');
            }
            if (! Schema::hasColumn('brands', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
            if (! Schema::hasColumn('brands', 'deleted_at')) {
                $table->softDeletes()->after('is_active');
            }
        });

        // 6. Attributes table alignment
        Schema::table('attributes', function (Blueprint $table) {
            if (! Schema::hasColumn('attributes', 'display_type')) {
                $table->string('display_type', 20)->default('select')->after('code');
            }
            if (! Schema::hasColumn('attributes', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });

        // 7. Attribute Values table alignment
        Schema::table('attribute_values', function (Blueprint $table) {
            if (! Schema::hasColumn('attribute_values', 'code')) {
                $table->string('code', 100)->default('')->after('attribute_id');
            }
            if (! Schema::hasColumn('attribute_values', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });

        // 8. Attribute Category pivot alignment
        Schema::table('attribute_category', function (Blueprint $table) {
            if (! Schema::hasColumn('attribute_category', 'is_required')) {
                $table->boolean('is_required')->default(false)->after('category_id');
            }
            if (! Schema::hasColumn('attribute_category', 'is_variant_axis')) {
                $table->boolean('is_variant_axis')->default(false)->after('is_required');
            }
            if (! Schema::hasColumn('attribute_category', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_variant_axis');
            }
        });

        // Add MySQL-specific unique constraints safely
        if ($isMysql) {
            $this->safelyExecuteSql('ALTER TABLE `roles` ADD CONSTRAINT `uq_roles_name` UNIQUE (`name`)');
            $this->safelyExecuteSql('ALTER TABLE `user_addresses` ADD CONSTRAINT `uq_user_addresses_default_user` UNIQUE (`default_user_id`)');
            $this->safelyExecuteSql('ALTER TABLE `categories` ADD CONSTRAINT `uq_categories_parent_scope_name` UNIQUE (`parent_scope_id`, `name`)');
            $this->safelyExecuteSql('ALTER TABLE `attribute_values` ADD CONSTRAINT `uq_attribute_values_id_attribute` UNIQUE (`id`, `attribute_id`)');
            $this->safelyExecuteSql('ALTER TABLE `attribute_values` ADD CONSTRAINT `uq_attribute_values_attribute_code` UNIQUE (`attribute_id`, `code`)');
        }
    }

    public function down(): void
    {
        // Non-destructive rollback where applicable
    }

    private function safelyExecuteSql(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (Throwable) {
            // Constraint may already exist or cannot be applied on current engine
        }
    }
};
