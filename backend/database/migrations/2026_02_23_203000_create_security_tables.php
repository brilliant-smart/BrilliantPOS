<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two-Factor Authentication
        Schema::create('two_factor_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('enabled')->default(false);
            $table->string('secret')->nullable();
            $table->json('recovery_codes')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
            
            $table->unique('user_id');
        });

        // Login attempts tracking
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->ipAddress('ip_address');
            $table->string('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['email', 'attempted_at']);
            $table->index('ip_address');
        });

        // IP whitelist
        Schema::create('ip_whitelist', function (Blueprint $table) {
            $table->id();
            $table->ipAddress('ip_address');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique('ip_address');
        });

        // Security settings
        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('ip_whitelist_enabled')->default(false);
            $table->boolean('two_fa_required')->default(false);
            $table->integer('password_min_length')->default(8);
            $table->boolean('password_require_uppercase')->default(true);
            $table->boolean('password_require_numbers')->default(true);
            $table->boolean('password_require_symbols')->default(false);
            $table->integer('password_expiry_days')->default(90);
            $table->integer('max_login_attempts')->default(5);
            $table->integer('lockout_duration_minutes')->default(15);
            $table->integer('session_timeout_minutes')->default(120);
            $table->timestamps();
        });

        DB::table('security_settings')->insert([
            'ip_whitelist_enabled' => false,
            'two_fa_required' => false,
            'password_min_length' => 8,
            'password_require_uppercase' => true,
            'password_require_numbers' => true,
            'password_require_symbols' => false,
            'password_expiry_days' => 90,
            'max_login_attempts' => 5,
            'lockout_duration_minutes' => 15,
            'session_timeout_minutes' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add password_changed_at to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'password_changed_at')) {
                $table->dropColumn('password_changed_at');
            }
            if (Schema::hasColumn('users', 'locked_until')) {
                $table->dropColumn('locked_until');
            }
        });
        
        Schema::dropIfExists('security_settings');
        Schema::dropIfExists('ip_whitelist');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('two_factor_auth');
    }
};
