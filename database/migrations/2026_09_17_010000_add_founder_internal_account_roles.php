<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->after('role')->index();
            });
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('founder', 'admin', 'staff', 'employee', 'partner') NOT NULL DEFAULT 'admin'");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');
            DB::statement(<<<'SQL'
                CREATE TABLE users_founder_roles (
                    id integer primary key autoincrement not null,
                    partner_id integer null,
                    name varchar not null,
                    email varchar not null,
                    role varchar check ("role" in ('founder', 'admin', 'staff', 'employee', 'partner')) not null default 'admin',
                    is_active tinyint(1) not null default '1',
                    email_verified_at datetime null,
                    password varchar not null,
                    remember_token varchar null,
                    first_login_at datetime null,
                    reset_expires_at datetime null,
                    created_at datetime null,
                    updated_at datetime null,
                    foreign key(partner_id) references partners(id) on delete set null
                )
            SQL);
            DB::statement(<<<'SQL'
                INSERT INTO users_founder_roles (
                    id,
                    partner_id,
                    name,
                    email,
                    role,
                    is_active,
                    email_verified_at,
                    password,
                    remember_token,
                    first_login_at,
                    reset_expires_at,
                    created_at,
                    updated_at
                )
                SELECT
                    id,
                    partner_id,
                    name,
                    email,
                    role,
                    COALESCE(is_active, 1),
                    email_verified_at,
                    password,
                    remember_token,
                    first_login_at,
                    reset_expires_at,
                    created_at,
                    updated_at
                FROM users
            SQL);
            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_founder_roles RENAME TO users');
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email)');
            DB::statement('CREATE INDEX users_partner_id_index ON users (partner_id)');
            DB::statement('CREATE INDEX users_is_active_index ON users (is_active)');
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    public function down(): void
    {
        // Intentionally no-op: narrowing internal role values could invalidate real accounts.
    }
};
