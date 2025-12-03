<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Product;
use App\Models\Transaction;

class SystemController extends Controller
{
    /**
     * Get system information
     */
    public function getSystemInfo()
    {
        try {
            // Get Laravel and PHP versions
            $laravelVersion = app()->version();
            $phpVersion = PHP_VERSION;
            $phpExtensions = get_loaded_extensions();

            // Auto-detect database type and version
            $databaseInfo = $this->getDatabaseInfo();

            // Get real server info
            $serverInfo = $this->getServerInfo();

            // Get database statistics
            $totalUsers = User::count();
            $totalProducts = Product::count();
            $totalTransactions = Transaction::count();

            // Get database size
            $databaseSize = $this->getDatabaseSize();

            // Get last backup info
            $lastBackup = $this->getLastBackupInfo();

            // Get system resources
            $systemResources = $this->getSystemResources();

            $appInfo = [
                'name' => config('app.name', 'Kasir POS System'),
                'version' => $this->getAppVersion(),
                'environment' => config('app.env'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'debug_mode' => (bool) config('app.debug'),
                'laravel_version' => $laravelVersion,
                'php_version' => $phpVersion,
                'php_extensions' => count($phpExtensions),
            ];

            $databaseSummary = [
                'type' => $databaseInfo['type'],
                'version' => $databaseInfo['version'],
                'name' => $databaseInfo['name'],
                'size' => $databaseSize,
                'host' => $databaseInfo['host'] ?? 'localhost',
                'port' => $databaseInfo['port'] ?? '0',
                'connections' => $databaseInfo['all_databases'] ?? [],
                'total_connections' => $databaseInfo['total_connections'] ?? 1,
            ];

            $serverSummary = [
                'os' => $serverInfo['os'],
                'software' => $serverInfo['software'],
                'cpu_cores' => $serverInfo['cpu_cores'],
                'uptime' => $serverInfo['uptime'],
                'memory' => $serverInfo['memory'],
                'memory_usage' => $serverInfo['memory_usage'],
            ];

            $storageSummary = [
                'disk_total' => $serverInfo['disk_total'],
                'disk_free' => $serverInfo['disk_free'],
                'disk_used' => $serverInfo['disk_used'],
                'last_backup' => $lastBackup,
            ];

            $statistics = [
                'total_users' => $totalUsers,
                'total_products' => $totalProducts,
                'total_transactions' => $totalTransactions,
            ];

            $environmentSummary = [
                'cache_driver' => config('cache.default'),
                'session_driver' => config('session.driver'),
                'queue_driver' => config('queue.default'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'app' => $appInfo,
                    'database' => $databaseSummary,
                    'server' => $serverSummary,
                    'storage' => $storageSummary,
                    'statistics' => $statistics,
                    'environment' => $environmentSummary,
                    'resources' => $systemResources,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get backup history
     */
    public function getBackupHistory()
    {
        try {
            $backupPath = storage_path('app/backups');
            $backups = [];

            Log::info('Getting backup history from: ' . $backupPath);

            if (!is_dir($backupPath)) {
                Log::info('Backup directory does not exist, creating it');
                mkdir($backupPath, 0755, true);
            }

            // Get all backup files with different extensions
            $extensions = ['sql', 'sqlite', 'bak', 'dmp', 'archive', 'rdb', 'backup'];
            $allFiles = [];

            foreach ($extensions as $ext) {
                $files = glob($backupPath . '/*.' . $ext);
                $allFiles = array_merge($allFiles, $files);
            }

            Log::info('Found backup files: ' . count($allFiles));

            foreach ($allFiles as $file) {
                if (is_file($file)) {
                    $filename = basename($file);
                    $size = filesize($file);
                    $createdAt = date('Y-m-d H:i:s', filemtime($file));

                    // Determine type from filename
                    $type = strpos($filename, 'auto_') === 0 ? 'auto' : 'manual';

                    // Determine database type from extension
                    $extension = pathinfo($filename, PATHINFO_EXTENSION);
                    $dbType = $this->getDbTypeFromExtension($extension);

                    $backups[] = [
                        'id' => md5($filename),
                        'filename' => $filename,
                        'size' => (string)$size,
                        'created_at' => $createdAt,
                        'type' => $type,
                        'status' => 'completed',
                        'database_type' => $dbType,
                        'extension' => $extension,
                        'file_path' => $file
                    ];

                    Log::info('Added backup: ' . $filename . ' (' . $this->formatBytes($size) . ')');
                }
            }

            // Sort by creation date (newest first)
            usort($backups, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            Log::info('Returning ' . count($backups) . ' backup files');

            return response()->json([
                'success' => true,
                'data' => $backups,
                'backup_path' => $backupPath,
                'total_files' => count($backups)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get backup history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get backup history: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getDbTypeFromExtension($extension)
    {
        $types = [
            'sql' => 'MySQL/PostgreSQL',
            'sqlite' => 'SQLite',
            'bak' => 'SQL Server',
            'dmp' => 'Oracle',
            'archive' => 'MongoDB',
            'rdb' => 'Redis',
            'backup' => 'Generic'
        ];

        return $types[$extension] ?? 'Unknown';
    }

    /**
     * Create database backup
     */
    public function createBackup(Request $request)
    {
        try {
            $type = $request->input('type', 'manual');
            $timestamp = date('Y-m-d_H-i-s');
            $prefix = $type === 'auto' ? 'auto_' : '';

            // Auto-detect database info
            $databaseInfo = $this->getDatabaseInfo();
            $driver = $databaseInfo['driver'];
            $dbName = $databaseInfo['name'];

            Log::info('Creating backup for database: ' . $driver . ' (' . $dbName . ')');

            // Set file extension based on database type
            $extension = $this->getBackupExtension($driver);
            $filename = $prefix . 'backup_' . $timestamp . '.' . $extension;

            $backupPath = storage_path('app/backups');

            // Create backup directory if not exists with proper permissions
            if (!is_dir($backupPath)) {
                if (!mkdir($backupPath, 0755, true)) {
                    Log::error('Failed to create backup directory: ' . $backupPath);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to create backup directory. Check permissions.'
                    ], 500);
                }
            }

            // Check if directory is writable
            if (!is_writable($backupPath)) {
                Log::error('Backup directory is not writable: ' . $backupPath);
                return response()->json([
                    'success' => false,
                    'message' => 'Backup directory is not writable. Check permissions.'
                ], 500);
            }

            $fullPath = $backupPath . '/' . $filename;

            // Create backup based on database type
            $success = $this->createDatabaseBackup($driver, $fullPath);

            if ($success && file_exists($fullPath)) {
                $fileSize = filesize($fullPath);
                $createdAt = date('Y-m-d H:i:s');

                Log::info('Backup created successfully: ' . $filename . ' (' . $this->formatBytes($fileSize) . ')');

                // Update last backup info in cache or database
                $this->updateLastBackupInfo($filename, $createdAt);

                return response()->json([
                    'success' => true,
                    'message' => 'Backup created successfully',
                    'data' => [
                        'filename' => $filename,
                        'size' => $fileSize,
                        'created_at' => $createdAt,
                        'database_type' => $databaseInfo['type'],
                        'file_path' => $fullPath
                    ]
                ]);
            } else {
                Log::error('Failed to create backup: ' . $fullPath . ' does not exist or backup command failed');
                Log::error('Database info: ' . json_encode($databaseInfo));
                Log::error('Backup path: ' . $backupPath);
                Log::error('Full path: ' . $fullPath);
                Log::error('Directory exists: ' . (is_dir($backupPath) ? 'YES' : 'NO'));
                Log::error('Directory writable: ' . (is_writable($backupPath) ? 'YES' : 'NO'));

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create backup for ' . $databaseInfo['type'] . ' database. Check logs for details.',
                    'debug' => [
                        'database_type' => $databaseInfo['type'],
                        'backup_path' => $backupPath,
                        'full_path' => $fullPath,
                        'directory_exists' => is_dir($backupPath),
                        'directory_writable' => is_writable($backupPath)
                    ]
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create backup: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getBackupExtension($driver)
    {
        $extensions = [
            'mysql' => 'sql',
            'pgsql' => 'sql',
            'sqlite' => 'sqlite',
            'sqlsrv' => 'bak',
            'oracle' => 'dmp',
            'mongodb' => 'archive',
            'redis' => 'rdb',
            'mariadb' => 'sql',
            'firebird' => 'fbk'
        ];

        return $extensions[$driver] ?? 'backup';
    }

    private function createDatabaseBackup($driver, $fullPath)
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                return $this->createMySQLBackup($config, $fullPath);
            case 'pgsql':
                return $this->createPostgreSQLBackup($config, $fullPath);
            case 'sqlite':
                return $this->createSQLiteBackup($config, $fullPath);
            case 'sqlsrv':
                return $this->createSQLServerBackup($config, $fullPath);
            case 'oracle':
                return $this->createOracleBackup($config, $fullPath);
            case 'mongodb':
                return $this->createMongoDBBackup($fullPath);
            case 'redis':
                return $this->createRedisBackup($fullPath);
            default:
                throw new \Exception("Unsupported database driver: {$driver}");
        }
    }

    private function createMySQLBackup($config, $fullPath)
    {
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? 3306),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database']),
            escapeshellarg($fullPath)
        );

        exec($command, $output, $returnCode);
        return $returnCode === 0;
    }

    private function createPostgreSQLBackup($config, $fullPath)
    {
        $command = sprintf(
            'pg_dump --host=%s --port=%s --username=%s --dbname=%s --file=%s',
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? 5432),
            escapeshellarg($config['username']),
            escapeshellarg($config['database']),
            escapeshellarg($fullPath)
        );

        // Set PGPASSWORD environment variable
        putenv("PGPASSWORD={$config['password']}");
        exec($command, $output, $returnCode);
        putenv("PGPASSWORD");

        return $returnCode === 0;
    }

    private function createSQLiteBackup($config, $fullPath)
    {
        try {
            $sourcePath = $config['database'];

            // Handle both absolute and relative paths
            if (!file_exists($sourcePath)) {
                // Try with database_path helper
                $sourcePath = database_path('database.sqlite');
            }

            if (!file_exists($sourcePath)) {
                // Try with base_path
                $sourcePath = base_path('database/database.sqlite');
            }

            Log::info('SQLite backup - Source path: ' . $sourcePath);
            Log::info('SQLite backup - Target path: ' . $fullPath);
            Log::info('SQLite backup - Source exists: ' . (file_exists($sourcePath) ? 'YES' : 'NO'));

            if (file_exists($sourcePath)) {
                // Ensure target directory exists
                $targetDir = dirname($fullPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                // Copy with error handling
                $result = copy($sourcePath, $fullPath);
                Log::info('SQLite backup - Copy result: ' . ($result ? 'SUCCESS' : 'FAILED'));

                if ($result && file_exists($fullPath)) {
                    Log::info('SQLite backup - File size: ' . filesize($fullPath) . ' bytes');
                    return true;
                }
            }

            Log::error('SQLite backup failed - Source file not found: ' . $sourcePath);
            return false;

        } catch (\Exception $e) {
            Log::error('SQLite backup error: ' . $e->getMessage());
            return false;
        }
    }

    private function createSQLServerBackup($config, $fullPath)
    {
        // SQL Server backup would require different approach
        // This is a simplified version
        $command = sprintf(
            'sqlcmd -S %s -d %s -U %s -P %s -Q "BACKUP DATABASE [%s] TO DISK = \'%s\'"',
            escapeshellarg($config['host']),
            escapeshellarg($config['database']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database']),
            escapeshellarg($fullPath)
        );

        exec($command, $output, $returnCode);
        return $returnCode === 0;
    }

    private function createOracleBackup($config, $fullPath)
    {
        // Oracle Data Pump export
        $command = sprintf(
            'expdp %s/%s@%s:%s/%s DIRECTORY=backup_dir DUMPFILE=%s FULL=Y',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? 1521),
            escapeshellarg($config['database']),
            escapeshellarg(basename($fullPath))
        );

        exec($command, $output, $returnCode);
        return $returnCode === 0;
    }

    private function createMongoDBBackup($fullPath)
    {
        try {
            $mongoUri = env('MONGO_URI', 'mongodb://localhost:27017');
            $mongoDb = env('MONGO_DATABASE', 'test');

            // Use mongodump command
            $command = sprintf(
                'mongodump --uri=%s --db=%s --archive=%s --gzip',
                escapeshellarg($mongoUri),
                escapeshellarg($mongoDb),
                escapeshellarg($fullPath)
            );

            exec($command, $output, $returnCode);
            return $returnCode === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function createRedisBackup($fullPath)
    {
        try {
            $redisHost = env('REDIS_HOST', 'localhost');
            $redisPort = env('REDIS_PORT', 6379);

            // Use redis-cli to create backup
            $command = sprintf(
                'redis-cli -h %s -p %s --rdb %s',
                escapeshellarg($redisHost),
                escapeshellarg($redisPort),
                escapeshellarg($fullPath)
            );

            exec($command, $output, $returnCode);
            return $returnCode === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Download backup file
     */
    public function downloadBackup($backupId)
    {
        try {
            $backupPath = storage_path('app/backups');

            // Get all backup files with different extensions
            $extensions = ['sql', 'sqlite', 'bak', 'dmp', 'archive', 'rdb', 'backup'];
            $allFiles = [];

            foreach ($extensions as $ext) {
                $files = glob($backupPath . '/*.' . $ext);
                $allFiles = array_merge($allFiles, $files);
            }

            $targetFile = null;
            foreach ($allFiles as $file) {
                if (md5(basename($file)) === $backupId) {
                    $targetFile = $file;
                    break;
                }
            }

            Log::info('Download backup request for ID: ' . $backupId);
            Log::info('Found target file: ' . ($targetFile ?? 'null'));

            if (!$targetFile || !file_exists($targetFile)) {
                Log::error('Backup file not found: ' . $backupId);
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            Log::info('Downloading backup file: ' . $targetFile);
            return response()->download($targetFile);

        } catch (\Exception $e) {
            Log::error('Failed to download backup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to download backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get backup settings
     */
    public function getBackupSettings()
    {
        // This would typically come from a settings table
        // For now, return default settings
        return response()->json([
            'success' => true,
            'data' => [
                'auto_backup_enabled' => false,
                'backup_schedule' => 'daily',
                'retention_days' => 30
            ]
        ]);
    }

    /**
     * Update backup settings
     */
    public function updateBackupSettings(Request $request)
    {
        // This would typically update a settings table
        // For now, just return success
        return response()->json([
            'success' => true,
            'message' => 'Backup settings updated successfully'
        ]);
    }

    /**
     * Helper methods
     */
    private function getAppVersion()
    {
        // Try to get version from composer.json or package.json
        $composerPath = base_path('composer.json');
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            if (isset($composer['version'])) {
                return $composer['version'];
            }
        }
        return '1.0.0';
    }

    private function getDatabaseInfo()
    {
        $databases = [];

        // Check all configured database connections
        $connections = config('database.connections', []);
        $defaultConnection = config('database.default');

        foreach ($connections as $connectionName => $config) {
            $dbInfo = $this->detectDatabaseType($connectionName, $config);
            $dbInfo['is_default'] = ($connectionName === $defaultConnection);
            $databases[$connectionName] = $dbInfo;
        }

        // Check for MongoDB if available
        $mongoInfo = $this->detectMongoDB();
        if ($mongoInfo) {
            $databases['mongodb'] = $mongoInfo;
        }

        // Check for Redis if available
        $redisInfo = $this->detectRedis();
        if ($redisInfo) {
            $databases['redis'] = $redisInfo;
        }

        // Return primary database info for main display
        $primaryDb = $databases[$defaultConnection] ?? reset($databases);

        // Ensure type is always a string
        $dbType = $primaryDb['type'] ?? 'Unknown';
        if (is_array($dbType)) {
            $dbType = is_string($dbType[0] ?? null) ? $dbType[0] : 'Unknown';
        }
        if (!is_string($dbType)) {
            $dbType = (string) $dbType;
        }

        // Ensure version is always a string
        $dbVersion = $primaryDb['version'] ?? 'Unknown';
        if (is_array($dbVersion)) {
            $dbVersion = is_string($dbVersion[0] ?? null) ? $dbVersion[0] : 'Unknown';
        }
        if (!is_string($dbVersion)) {
            $dbVersion = (string) $dbVersion;
        }

        // Ensure name is always a string
        $dbName = $primaryDb['name'] ?? 'Unknown';
        if (is_array($dbName)) {
            $dbName = is_string($dbName[0] ?? null) ? $dbName[0] : 'Unknown';
        }
        if (!is_string($dbName)) {
            $dbName = (string) $dbName;
        }

        // Ensure host and port are strings
        $dbHost = is_array($primaryDb['host'] ?? null) ? (string) ($primaryDb['host'][0] ?? 'Unknown') : (string) ($primaryDb['host'] ?? 'Unknown');
        $dbPort = is_array($primaryDb['port'] ?? null) ? (string) ($primaryDb['port'][0] ?? 'Unknown') : (string) ($primaryDb['port'] ?? 'Unknown');
        $dbDriver = is_array($primaryDb['driver'] ?? null) ? (string) ($primaryDb['driver'][0] ?? 'unknown') : (string) ($primaryDb['driver'] ?? 'unknown');

        return [
            'type' => $dbType,
            'version' => $dbVersion,
            'name' => $dbName,
            'driver' => $dbDriver,
            'host' => $dbHost,
            'port' => $dbPort,
            'all_databases' => $databases,
            'total_connections' => count($databases)
        ];
    }

    private function detectDatabaseType($connectionName, $config)
    {
        try {
            $driver = $config['driver'] ?? 'unknown';
            $database = $config['database'] ?? 'Unknown';
            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? 'default';

            $type = $this->getReadableDatabaseType($driver);
            $version = 'Unknown';
            $status = 'disconnected';

            // Try to connect and get version
            try {
                switch ($driver) {
                    case 'mysql':
                        $result = DB::connection($connectionName)->select('SELECT VERSION() as version');
                        $version = $result[0]->version ?? 'Unknown';
                        $status = 'connected';
                        break;

                    case 'pgsql':
                        $result = DB::connection($connectionName)->select('SELECT version() as version');
                        $versionString = $result[0]->version ?? 'Unknown';
                        // Extract version number from PostgreSQL version string
                        preg_match('/PostgreSQL (\d+\.\d+)/', $versionString, $matches);
                        $version = $matches[1] ?? $versionString;
                        $status = 'connected';
                        break;

                    case 'sqlite':
                        $result = DB::connection($connectionName)->select('SELECT sqlite_version() as version');
                        $version = $result[0]->version ?? 'Unknown';
                        $status = 'connected';
                        // For SQLite, database is the file path
                        $database = basename($database);

                        // Check if SQLite file exists and is accessible
                        $sqlitePath = $config['database'];
                        if (!file_exists($sqlitePath)) {
                            // Try alternative paths
                            $alternativePaths = [
                                database_path('database.sqlite'),
                                base_path('database/database.sqlite'),
                                storage_path('database.sqlite')
                            ];

                            foreach ($alternativePaths as $altPath) {
                                if (file_exists($altPath)) {
                                    $sqlitePath = $altPath;
                                    break;
                                }
                            }
                        }

                        $database = [
                            'name' => basename($sqlitePath),
                            'path' => $sqlitePath,
                            'exists' => file_exists($sqlitePath),
                            'readable' => is_readable($sqlitePath),
                            'size' => file_exists($sqlitePath) ? filesize($sqlitePath) : 0
                        ];
                        break;

                    case 'sqlsrv':
                        $result = DB::connection($connectionName)->select('SELECT @@VERSION as version');
                        $versionString = $result[0]->version ?? 'Unknown';
                        // Extract version from SQL Server version string
                        preg_match('/Microsoft SQL Server (\d+)/', $versionString, $matches);
                        $version = $matches[1] ?? $versionString;
                        $status = 'connected';
                        break;

                    case 'oracle':
                        $result = DB::connection($connectionName)->select('SELECT * FROM v$version WHERE banner LIKE \'Oracle%\'');
                        $version = $result[0]->banner ?? 'Unknown';
                        $status = 'connected';
                        break;
                }
            } catch (\Exception $e) {
                $status = 'error: ' . $e->getMessage();
            }

            return [
                'type' => $type,
                'version' => $version,
                'name' => $database,
                'driver' => $driver,
                'host' => $host,
                'port' => $port,
                'status' => $status,
                'connection_name' => $connectionName
            ];

        } catch (\Exception $e) {
            return [
                'type' => 'Unknown',
                'version' => 'Unknown',
                'name' => 'Unknown',
                'driver' => 'unknown',
                'host' => 'Unknown',
                'port' => 'Unknown',
                'status' => 'error',
                'connection_name' => $connectionName
            ];
        }
    }

    private function detectMongoDB()
    {
        try {
            // Check if MongoDB extension is loaded
            if (!extension_loaded('mongodb')) {
                return null;
            }

            // Try to connect to MongoDB
            $mongoUri = env('MONGO_URI', 'mongodb://localhost:27017');
            $mongoDb = env('MONGO_DATABASE', 'test');

            if (class_exists('\MongoDB\Client')) {
                $mongoClientClass = '\MongoDB\Client';
                /** @var object $client */
                $client = new $mongoClientClass($mongoUri);
                $admin = $client->selectDatabase('admin');
                $result = $admin->command(['buildInfo' => 1]);
                $buildInfo = $result->toArray()[0];

                return [
                    'type' => 'MongoDB',
                    'version' => $buildInfo['version'] ?? 'Unknown',
                    'name' => $mongoDb,
                    'driver' => 'mongodb',
                    'host' => parse_url($mongoUri, PHP_URL_HOST) ?? 'localhost',
                    'port' => parse_url($mongoUri, PHP_URL_PORT) ?? 27017,
                    'status' => 'connected',
                    'connection_name' => 'mongodb'
                ];
            }
        } catch (\Exception $e) {
            return [
                'type' => 'MongoDB',
                'version' => 'Unknown',
                'name' => 'Unknown',
                'driver' => 'mongodb',
                'host' => 'localhost',
                'port' => 27017,
                'status' => 'error: ' . $e->getMessage(),
                'connection_name' => 'mongodb'
            ];
        }

        return null;
    }

    private function detectRedis()
    {
        try {
            // Check if Redis extension is loaded
            if (!extension_loaded('redis') && !class_exists('Predis\Client')) {
                return null;
            }

            $redisHost = env('REDIS_HOST', 'localhost');
            $redisPort = env('REDIS_PORT', 6379);
            $redisDb = env('REDIS_DB', 0);

            if (extension_loaded('redis')) {
                $redisClass = '\Redis';
                /** @var object $redis */
                $redis = new $redisClass();
                $redis->connect($redisHost, $redisPort);
                $info = $redis->info();
                $version = $info['redis_version'] ?? 'Unknown';
                $redis->close();

                return [
                    'type' => 'Redis',
                    'version' => $version,
                    'name' => "DB {$redisDb}",
                    'driver' => 'redis',
                    'host' => $redisHost,
                    'port' => $redisPort,
                    'status' => 'connected',
                    'connection_name' => 'redis'
                ];
            }
        } catch (\Exception $e) {
            return [
                'type' => 'Redis',
                'version' => 'Unknown',
                'name' => 'Unknown',
                'driver' => 'redis',
                'host' => $redisHost ?? 'localhost',
                'port' => $redisPort ?? 6379,
                'status' => 'error: ' . $e->getMessage(),
                'connection_name' => 'redis'
            ];
        }

        return null;
    }

    private function getReadableDatabaseType($driver)
    {
        $types = [
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            'sqlite' => 'SQLite',
            'sqlsrv' => 'SQL Server',
            'oracle' => 'Oracle',
            'mongodb' => 'MongoDB',
            'redis' => 'Redis',
            'mariadb' => 'MariaDB',
            'firebird' => 'Firebird',
            'ibm' => 'IBM DB2',
            'informix' => 'Informix',
            'odbc' => 'ODBC'
        ];

        return $types[$driver] ?? ucfirst($driver);
    }

    private function getServerInfo()
    {
        try {
            // OS Information
            $os = php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')';

            // Web Server
            $software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';

            // Memory info
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = ini_get('memory_limit');

            // Disk space
            $diskTotal = disk_total_space('/');
            $diskFree = disk_free_space('/');
            $diskUsed = $diskTotal - $diskFree;

            // CPU cores
            $cpuCores = $this->getCpuCores();

            // Uptime
            $uptime = $this->getServerUptime();

            return [
                'os' => $os,
                'software' => $software,
                'memory' => $this->formatBytes($memoryPeak),
                'memory_usage' => $this->formatBytes($memoryUsage),
                'memory_limit' => $memoryLimit,
                'disk_total' => $this->formatBytes($diskTotal),
                'disk_free' => $this->formatBytes($diskFree),
                'disk_used' => $this->formatBytes($diskUsed),
                'cpu_cores' => $cpuCores,
                'uptime' => $uptime
            ];
        } catch (\Exception $e) {
            return [
                'os' => 'Unknown',
                'software' => 'Unknown',
                'memory' => 'Unknown',
                'memory_usage' => 'Unknown',
                'memory_limit' => 'Unknown',
                'disk_total' => 'Unknown',
                'disk_free' => 'Unknown',
                'disk_used' => 'Unknown',
                'cpu_cores' => 'Unknown',
                'uptime' => 'Unknown'
            ];
        }
    }

    private function getSystemResources()
    {
        return [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'date_timezone' => ini_get('date.timezone'),
        ];
    }

    private function getCpuCores()
    {
        try {
            if (function_exists('shell_exec')) {
                if (PHP_OS_FAMILY === 'Windows') {
                    $cores = shell_exec('echo %NUMBER_OF_PROCESSORS%');
                } else {
                    $cores = shell_exec('nproc');
                }
                return (int) trim($cores);
            }
            return 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes === false || $bytes === null) {
            return 'Unknown';
        }

        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function getServerUptime()
    {
        if (function_exists('sys_getloadavg')) {
            $uptime = shell_exec('uptime');
            if ($uptime) {
                return trim($uptime);
            }
        }
        return 'N/A';
    }

    private function getDatabaseSize()
    {
        try {
            $database = config('database.connections.mysql.database');
            $result = DB::select("
                SELECT
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$database]);

            return $result[0]->size_mb . ' MB';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function updateLastBackupInfo($filename, $createdAt)
    {
        try {
            // Store last backup info in cache
            Cache::put('last_backup_filename', $filename, 3600);
            Cache::put('last_backup_date', $createdAt, 3600);

            Log::info('Updated last backup info: ' . $filename . ' at ' . $createdAt);
        } catch (\Exception $e) {
            Log::error('Failed to update last backup info: ' . $e->getMessage());
        }
    }

    private function getLastBackupInfo()
    {
        try {
            // Try to get from cache first
            $cachedDate = Cache::get('last_backup_date');
            if ($cachedDate) {
                return $cachedDate;
            }

            // Fallback to file system scan
            $backupPath = storage_path('app/backups');
            if (!is_dir($backupPath)) {
                return null;
            }

            // Get all backup files with different extensions
            $extensions = ['sql', 'sqlite', 'bak', 'dmp', 'archive', 'rdb', 'backup'];
            $allFiles = [];

            foreach ($extensions as $ext) {
                $files = glob($backupPath . '/*.' . $ext);
                $allFiles = array_merge($allFiles, $files);
            }

            if (empty($allFiles)) {
                return null;
            }

            // Get the most recent backup
            $latestTime = 0;

            foreach ($allFiles as $file) {
                $time = filemtime($file);
                if ($time > $latestTime) {
                    $latestTime = $time;
                }
            }

            $lastBackupDate = date('Y-m-d H:i:s', $latestTime);

            // Cache the result
            Cache::put('last_backup_date', $lastBackupDate, 3600);

            return $lastBackupDate;
        } catch (\Exception $e) {
            Log::error('Failed to get last backup info: ' . $e->getMessage());
            return null;
        }
    }
}
