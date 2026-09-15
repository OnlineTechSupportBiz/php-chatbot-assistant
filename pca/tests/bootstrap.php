<?php

/**
 * Test bootstrap — sets up autoloading, test environment,
 * and global function mocks (getDb, env) so models and
 * services can be tested without a real database.
 */

declare(strict_types=1);

// ── Load Composer autoloader ────────────────────────────────────────────────
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Composer autoloader not found. Run 'composer install' first.\n");
    exit(1);
}
require $autoload;

// ── Set test environment ────────────────────────────────────────────────────
$_ENV['APP_ENV'] = 'testing';

// Prevent session headers from polluting test output
if (session_status() === PHP_SESSION_NONE) {
    session_id('test-session');
    @session_start();
}

// ── Global env() helper (same signature as config/config.php) ───────────────
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

// ── Global getDb() — returns a TestDb-managed connection ──────────────────
if (!function_exists('getDb')) {
    function getDb(): PDO
    {
        return TestDb::getConnection();
    }
}

// ── Test helper classes ─────────────────────────────────────────────────────

/**
 * Manages a test PDO connection.
 *
 * - Unit tests: TestDb::setPdo(mock) to inject a configured mock.
 * - Integration tests: TestDb::connect() for a real PostgreSQL DB.
 *
 * Each test class should reset/set its own PDO in setUp().
 */
class TestDb
{
    private static ?PDO $pdo = null;
    private static bool $initialized = false;

    /**
     * Connect to a real test database.
     */
    public static function connect(
        string $host = '127.0.0.1',
        int    $port = 5432,
        string $name = 'chatbot_assistant_test',
        string $user = 'chatbot_user',
        string $pass = '',
        string $schema = 'chatbot_schema'
    ): PDO {
        $dsn = "pgsql:host={$host};port={$port};dbname={$name};options='--search_path={$schema}'";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        self::setPdo($pdo);
        return $pdo;
    }

    public static function getConnection(): PDO
    {
        if (!self::$initialized) {
            throw new \RuntimeException(
                'TestDb not configured. Call TestDb::setPdo() or TestDb::connect() in your test setUp().'
            );
        }
        return self::$pdo;
    }

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$initialized = true;
    }

    public static function reset(): void
    {
        self::$initialized = false;
        self::$pdo = null;
    }

    public static function isInitialized(): bool
    {
        return self::$initialized;
    }
}

/**
 * Helper trait for tests that need to manipulate $_SESSION.
 *
 * Usage in your test:
 *   use SessionHelper;
 *   $this->loginSession();
 *   $this->clearSession();
 */
trait SessionHelper
{
    protected function loginSession(array $overrides = []): array
    {
        $user = array_merge([
            'id'           => 1,
            'admin_id'     => 1,
            'role'         => 'user',
            'name'         => 'Test User',
            'email'        => 'test@example.com',
            'brand_name'   => 'Test Brand',
            'company_name' => 'Test Company',
            'timezone'     => 'America/New_York',
        ], $overrides);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['admin_id']   = $user['admin_id'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['brand_name'] = $user['brand_name'];
        $_SESSION['company_name'] = $user['company_name'];
        $_SESSION['timezone']   = $user['timezone'];

        return $user;
    }

    /**
     * Start the session if not already active (for test isolation).
     */
    protected function startTestSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id('test-' . uniqid());
        @session_start();
    }

    protected function clearSession(): void
    {
        $_SESSION = [];
    }
}

/**
 * Helper trait for tests that need to manipulate $_SERVER superglobal.
 */
trait ServerHelper
{
    protected function setServer(array $values): void
    {
        foreach ($values as $key => $val) {
            $_SERVER[$key] = $val;
        }
    }

    protected function clearServer(array $keys): void
    {
        foreach ($keys as $key) {
            unset($_SERVER[$key]);
        }
    }
}

/**
 * Helper trait for creating mock PDO statements quickly.
 */
trait PdoMocker
{
    /**
     * Create a mock PDO with a mock statement that returns given results.
     *
     * All prepare() calls return the same mock statement.
     *
     * @param array $queryResult  The result to return from fetch()/fetchAll()
     *        Key 'fetch'    => single row array    (for find/findByEmail etc.)
     *        Key 'fetchAll' => array of rows       (for findAll/findByChatbot etc.)
     *        Key 'column'   => scalar              (for count etc.)
     *        Key 'id'       => string              (for lastInsertId)
     *        If omitted, fetch returns false (not found).
     * @return \PHPUnit\Framework\MockObject\MockObject&PDO
     */
    protected function createMockPdo(array $queryResult = []): PDO
    {
        $pdo = $this->createStub(\PDO::class);

        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('bindParam')->willReturn(true);

        if (isset($queryResult['fetch'])) {
            $stmt->method('fetch')->willReturn($queryResult['fetch']);
        } else {
            $stmt->method('fetch')->willReturn(false);
        }

        if (isset($queryResult['fetchAll'])) {
            $stmt->method('fetchAll')->willReturn($queryResult['fetchAll']);
        } else {
            $stmt->method('fetchAll')->willReturn([]);
        }

        if (isset($queryResult['column'])) {
            $stmt->method('fetchColumn')->willReturn($queryResult['column']);
        } else {
            $stmt->method('fetchColumn')->willReturn(false);
        }

        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('lastInsertId')->willReturn($queryResult['id'] ?? '1');
        $pdo->method('exec')->willReturn(0);

        return $pdo;
    }
}
