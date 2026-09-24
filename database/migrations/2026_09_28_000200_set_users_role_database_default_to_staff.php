<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * SQLite only honours PRAGMA foreign_keys outside a transaction; with enforcement on, dropping the
     * users table during the rebuild could cascade into child rows.
     */
    public $withinTransaction = false;

    /**
     * P13.1 hardening (D-25): users.role defaults to 'staff' at the database level.
     *
     * Fresh installs already get this from the corrected column definitions; this migration corrects
     * databases created before the change. Only the default changes: the allowed values are unchanged
     * ('admin' remains representable as a dormant post-V1 value), and no row is rewritten.
     * Idempotent: does nothing when the default is already 'staff'.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role') || $this->currentDefault() === 'staff') {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('founder', 'admin', 'staff', 'employee', 'partner') NOT NULL DEFAULT 'staff'");

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteUsersTable();
        }
    }

    public function down(): void
    {
        // Intentionally no-op: the Admin default is not restored.
    }

    private function currentDefault(): ?string
    {
        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'role');

        return $column === null ? null : trim((string) $column['default'], "'\" ");
    }

    /**
     * SQLite cannot alter a column default in place. Rebuild the table from its own current definition
     * (every column, check constraint and foreign key kept), changing only the role default, then
     * restore its indexes.
     */
    private function rebuildSqliteUsersTable(): void
    {
        $create = (string) DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->value('sql');
        $indexes = DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'users')->whereNotNull('sql')->pluck('sql')->all();
        $columns = collect(DB::select('PRAGMA table_info(users)'))->pluck('name')
            ->map(fn (string $name) => '"'.str_replace('"', '""', $name).'"')
            ->implode(', ');

        // Matches both `default 'admin'` and Laravel's own rebuild format `default ('admin')`.
        $updated = preg_replace('/("?role"?\s[^,]*?default\s+\(?)\'admin\'/i', "$1'staff'", $create, 1, $count);
        if ($count !== 1) {
            throw new RuntimeException('users.role default could not be located in the SQLite table definition.');
        }
        $updated = preg_replace('/^CREATE TABLE\s+"?users"?/i', 'CREATE TABLE "users_role_default_staff"', $updated, 1);

        DB::statement('PRAGMA foreign_keys=OFF');
        try {
            DB::statement($updated);
            DB::statement("INSERT INTO users_role_default_staff ({$columns}) SELECT {$columns} FROM users");
            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_role_default_staff RENAME TO users');
            foreach ($indexes as $index) {
                DB::statement($index);
            }
        } finally {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }
};
