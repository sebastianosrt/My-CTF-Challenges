<?php

namespace Herbarium\Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: 'db';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('DB_NAME') ?: 'herbarium';
            $user = getenv('DB_USER') ?: 'herbarium';
            $pass = getenv('DB_PASS') ?: 'herbarium';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $user, $pass);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec("SET NAMES utf8mb4");
        }
        return self::$pdo;
    }

    public static function init(): void
    {
        $db = self::connect();

        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                display_name VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'user',
                avatar VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS specimens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                common_name VARCHAR(255) NOT NULL,
                species VARCHAR(255),
                family VARCHAR(255),
                genus VARCHAR(255),
                location_found VARCHAR(255),
                habitat VARCHAR(255),
                collected_date VARCHAR(50),
                collector VARCHAR(255),
                description TEXT,
                preservation_method VARCHAR(255),
                imported_by INT,
                source VARCHAR(100) DEFAULT 'manual',
                imported_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS import_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                source_type VARCHAR(100) NOT NULL,
                source_detail TEXT,
                records_imported INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'success',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(255) NOT NULL,
                user_id INT,
                detail TEXT,
                ip_address VARCHAR(45),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS cache (
                `key` VARCHAR(255) PRIMARY KEY,
                value TEXT NOT NULL,
                expires_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS annotations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                specimen_id INT NOT NULL,
                user_id INT NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (specimen_id) REFERENCES specimens(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS collections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                user_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS collection_specimens (
                collection_id INT NOT NULL,
                specimen_id INT NOT NULL,
                added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (collection_id, specimen_id),
                FOREIGN KEY (collection_id) REFERENCES collections(id),
                FOREIGN KEY (specimen_id) REFERENCES specimens(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                body TEXT,
                status VARCHAR(50) NOT NULL DEFAULT 'draft',
                author_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                published_at DATETIME DEFAULT NULL,
                FOREIGN KEY (author_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                slug VARCHAR(255) NOT NULL UNIQUE,
                description VARCHAR(1000) DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS taggables (
                tag_id INT NOT NULL,
                taggable_id INT NOT NULL,
                taggable_type VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (tag_id, taggable_id, taggable_type),
                FOREIGN KEY (tag_id) REFERENCES tags(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS revisions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(100) NOT NULL,
                entity_id INT NOT NULL,
                user_id INT NOT NULL,
                title VARCHAR(255),
                body TEXT,
                diff_summary VARCHAR(500) DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $alterColumns = [
            "ALTER TABLE specimens ADD COLUMN slug VARCHAR(255) DEFAULT NULL",
            "ALTER TABLE specimens ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'published'",
            "ALTER TABLE specimens ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP",
        ];
        foreach ($alterColumns as $sql) {
            try {
                $db->exec($sql);
            } catch (\PDOException $e) {
            }
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS media (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_size INT NOT NULL,
                alt_text VARCHAR(500) DEFAULT '',
                caption VARCHAR(1000) DEFAULT '',
                uploaded_by INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (uploaded_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                `key` VARCHAR(255) PRIMARY KEY,
                value TEXT NOT NULL,
                description VARCHAR(500) DEFAULT '',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_by INT,
                FOREIGN KEY (updated_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                key_hash VARCHAR(64) NOT NULL UNIQUE,
                key_prefix VARCHAR(20) NOT NULL,
                permissions VARCHAR(100) NOT NULL DEFAULT 'read',
                created_by INT NOT NULL,
                last_used_at DATETIME DEFAULT NULL,
                expires_at DATETIME DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS webhooks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                url VARCHAR(2048) NOT NULL,
                secret VARCHAR(255) NOT NULL,
                events VARCHAR(500) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS webhook_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                webhook_id INT NOT NULL,
                event VARCHAR(100) NOT NULL,
                payload TEXT NOT NULL,
                response_status INT,
                response_body TEXT,
                success TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (webhook_id) REFERENCES webhooks(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS scheduled_actions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(100) NOT NULL,
                entity_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                scheduled_for DATETIME NOT NULL,
                executed_at DATETIME DEFAULT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                created_by INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        self::seed();
    }

    private static function seed(): void
    {
        $db = self::connect();

        $count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $adminPass = getenv('ADMIN_PASSWORD') ?: 'admin';
        $adminHash = password_hash($adminPass, PASSWORD_BCRYPT);

        $stmt = $db->prepare("INSERT INTO users (username, password, display_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $adminHash, 'Administrator', 'admin']);

        $stmt = $db->prepare(
            "INSERT INTO specimens (common_name, species, family, genus, location_found, habitat, collected_date, collector, description, preservation_method, imported_by, source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['Dog Rose', 'Rosa canina', 'Rosaceae', 'Rosa', 'Kent, England', 'Hedgerows and woodland edges', '2024-03-15', 'C. Linnaeus Jr.', 'Deciduous shrub with hooked thorns and pink-white flowers. Hips used in traditional medicine.', 'Pressed and dried', 1, 'manual']);
        $stmt->execute(['Common Foxglove', 'Digitalis purpurea', 'Plantaginaceae', 'Digitalis', 'Welsh Borders', 'Woodland clearings, acidic soils', '2024-05-22', 'C. Linnaeus Jr.', 'Biennial herb producing tall spikes of purple tubular flowers. Source of cardiac glycosides.', 'Pressed and dried', 1, 'manual']);

        $tagCount = $db->query("SELECT COUNT(*) FROM tags")->fetchColumn();
        if ((int) $tagCount === 0) {
            $db->exec("INSERT INTO tags (name, slug, description) VALUES ('Flora', 'flora', 'General plant specimens')");
            $db->exec("INSERT INTO tags (name, slug, description) VALUES ('Pressed', 'pressed', 'Pressed and dried specimens')");
            $db->exec("INSERT INTO tags (name, slug, description) VALUES ('Field Collection', 'field-collection', 'Specimens collected in the field')");
        }

        $pageCount = $db->query("SELECT COUNT(*) FROM pages")->fetchColumn();
        if ((int) $pageCount === 0) {
            $db->exec("
                INSERT INTO pages (title, slug, body, status, author_id)
                VALUES (
                    'About the Herbarium',
                    'about-the-herbarium',
                    'Welcome to the Herbarium digital archive. This collection preserves botanical specimens for research and education.',
                    'draft',
                    1
                )
            ");
        }

        $settingsCount = $db->query("SELECT COUNT(*) FROM settings")->fetchColumn();
        if ((int) $settingsCount === 0) {
            $defaults = [
                ['site_name', 'Herbarium', 'The name of the site'],
                ['site_description', 'Digital botanical archive and specimen management system', 'A short description of the site'],
                ['items_per_page', '20', 'Number of items shown per page in listings'],
                ['allow_public_api', '1', 'Whether the public API endpoints are enabled (1=yes, 0=no)'],
                ['maintenance_mode', '0', 'Whether the site is in maintenance mode (1=yes, 0=no)'],
            ];
            $stmt = $db->prepare("INSERT INTO settings (`key`, value, description) VALUES (?, ?, ?)");
            foreach ($defaults as $row) {
                $stmt->execute($row);
            }
        }
    }

    public static function lastInsertId(): string
    {
        return self::connect()->lastInsertId();
    }

    public static function escape(string $value): string
    {
        return self::connect()->quote($value);
    }
    
    public static function escapeVal(string $value): string {
        return strtr($value, ["\\" => "\\\\", "'"  => "\\'", '"'  => '\\"' ]);
    }

    private static function bindAndExecute(\PDOStatement $stmt, array $params): void
    {
        foreach (array_values($params) as $i => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($i + 1, $value, $type);
        }
        $stmt->execute();
    }

    public static function prepared(string $sql, array $params = []): array
    {
        $db   = self::connect();
        $stmt = $db->prepare($sql);
        self::bindAndExecute($stmt, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function preparedExec(string $sql, array $params = []): int
    {
        $db   = self::connect();
        $stmt = $db->prepare($sql);
        self::bindAndExecute($stmt, $params);
        return $stmt->rowCount();
    }

    public static function preparedScalar(string $sql, array $params = [])
    {
        $db   = self::connect();
        $stmt = $db->prepare($sql);
        self::bindAndExecute($stmt, $params);
        $val  = $stmt->fetchColumn();
        return $val !== false ? $val : null;
    }

    public static function scalar(string $sql)
    {
        $db   = self::connect();
        $stmt = $db->query($sql);
        $val  = $stmt->fetchColumn();
        return $val !== false ? $val : null;
    }

    public static function tableExists(string $table): bool
    {
        $row = self::prepared(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$table]
        );
        return !empty($row);
    }

    private static array $allowedTables = [
        'users', 'specimens', 'import_logs', 'audit_log', 'cache',
        'annotations', 'collections', 'collection_specimens', 'pages',
        'tags', 'taggables', 'revisions', 'media', 'settings',
        'api_keys', 'webhooks', 'webhook_logs', 'scheduled_actions',
        'password_reset_tokens',
    ];

    public static function assertValidTable(string $table): void
    {
        if (!in_array($table, self::$allowedTables, true)) {
            throw new \InvalidArgumentException("Invalid table name: {$table}");
        }
    }

    public static function countRows(string $table, string $where = '', array $params = []): int
    {
        self::assertValidTable($table);
        $sql = "SELECT COUNT(*) FROM {$table}";
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        return (int) self::preparedScalar($sql, $params);
    }

    public static function transaction(callable $fn)
    {
        $db = self::connect();
        $db->beginTransaction();
        try {
            $result = $fn($db);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function preparedFirst(string $sql, array $params = []): ?array
    {
        $rows = self::prepared($sql, $params);
        return $rows[0] ?? null;
    }

    public static function preparedExists(string $sql, array $params = []): bool
    {
        return !empty(self::prepared($sql, $params));
    }

    public function __wakeup()
    {
        $this->pdo = null; 
        throw new \BadMethodCallException('Cannot unserialize ' . __CLASS__);
    }
}
