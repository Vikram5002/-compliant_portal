<?php

declare(strict_types=1);

/*
 * Export MySQL tables to DynamoDB BatchWrite JSON files.
 *
 * Example:
 * php aws_migration/scripts/export_mysql_to_dynamodb.php \
 *   --host=localhost --user=root --password= --database=cprtl \
 *   --output=aws_migration/out --prefix=cprtl
 */

$options = getopt('', [
    'host::',
    'port::',
    'user::',
    'password::',
    'database:',
    'output::',
    'prefix::',
]);

$host = $options['host'] ?? 'localhost';
$port = isset($options['port']) ? (int)$options['port'] : 3306;
$user = $options['user'] ?? 'root';
$password = $options['password'] ?? '';
$database = $options['database'] ?? null;
$output = $options['output'] ?? (__DIR__ . '/../out');
$prefix = $options['prefix'] ?? 'cprtl';

if ($database === null || $database === '') {
    fwrite(STDERR, "Missing required --database argument.\n");
    exit(1);
}

if (!is_dir($output) && !mkdir($output, 0777, true) && !is_dir($output)) {
    fwrite(STDERR, "Unable to create output directory: {$output}\n");
    exit(1);
}

$mysqli = @new mysqli($host, $user, $password, $database, $port);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "MySQL connection failed: {$mysqli->connect_error}\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$tables = [
    [
        'source' => 'allowed_roles',
        'target' => "{$prefix}_allowed_roles",
        'numeric' => ['role_id'],
    ],
    [
        'source' => 'users',
        'target' => "{$prefix}_users",
        'numeric' => ['user_id', 'parent_staff_id', 'is_sub_staff'],
    ],
    [
        'source' => 'attachments',
        'target' => "{$prefix}_attachments",
        'numeric' => ['attachment_id', 'file_size'],
    ],
    [
        'source' => 'tickets',
        'target' => "{$prefix}_tickets",
        'numeric' => ['ticket_id', 'user_id', 'attachment_id', 'assigned_to', 'reassigned_to'],
    ],
    [
        'source' => 'substaffapprovals',
        'target' => "{$prefix}_substaffapprovals",
        'numeric' => ['approval_id', 'ticket_id', 'sub_staff_id', 'parent_staff_id'],
    ],
    [
        'source' => 'statushistory',
        'target' => "{$prefix}_statushistory",
        'numeric' => ['history_id', 'ticket_id'],
    ],
    [
        'source' => 'notifications',
        'target' => "{$prefix}_notifications",
        'numeric' => ['notification_id', 'user_id', 'ticket_id', 'is_read'],
        'transform' => static function (array $row): array {
            $userId = $row['user_id'] ?? 0;
            $isRead = $row['is_read'] ?? 0;
            $row['user_read_key'] = "USER#{$userId}#READ#{$isRead}";
            return $row;
        },
    ],
    [
        'source' => 'feedback',
        'target' => "{$prefix}_feedback",
        'numeric' => ['feedback_id', 'ticket_id', 'user_id', 'rating'],
    ],
];

$manifest = [
    'generated_at' => gmdate('c'),
    'source_database' => $database,
    'table_prefix' => $prefix,
    'tables' => [],
];

foreach ($tables as $tableSpec) {
    $source = $tableSpec['source'];
    $target = $tableSpec['target'];
    $numericColumns = array_flip($tableSpec['numeric']);
    $transform = $tableSpec['transform'] ?? null;

    $result = $mysqli->query("SELECT * FROM `{$source}`");
    if ($result === false) {
        fwrite(STDERR, "Skipping {$source}: query failed ({$mysqli->error})\n");
        continue;
    }

    $requests = [];
    $rowCount = 0;

    while ($row = $result->fetch_assoc()) {
        $rowCount++;
        $normalized = normalizeRow($row, $numericColumns);

        if (is_callable($transform)) {
            $normalized = $transform($normalized);
        }

        $requests[] = [
            'PutRequest' => [
                'Item' => marshalDynamoItem($normalized),
            ],
        ];
    }

    $result->free();

    $batchFiles = [];
    $chunks = array_chunk($requests, 25);

    foreach ($chunks as $index => $chunk) {
        $batchNo = str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT);
        $fileName = "{$target}_batch_{$batchNo}.json";
        $filePath = rtrim($output, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        $payload = [
            $target => $chunk,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            fwrite(STDERR, "Failed to JSON encode batch for {$target}.\n");
            continue;
        }

        file_put_contents($filePath, $json . PHP_EOL);
        $batchFiles[] = $fileName;
    }

    $manifest['tables'][] = [
        'source' => $source,
        'target' => $target,
        'row_count' => $rowCount,
        'batch_files' => $batchFiles,
    ];

    $batchCount = count($batchFiles);
    echo "Exported {$source} -> {$target}: {$rowCount} rows, {$batchCount} batch file(s)." . PHP_EOL;
}

$manifestPath = rtrim($output, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'manifest.json';
file_put_contents(
    $manifestPath,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
);

echo "Manifest written: {$manifestPath}" . PHP_EOL;
$mysqli->close();

/**
 * @param array<string, mixed> $row
 * @param array<string, int> $numericColumns
 * @return array<string, mixed>
 */
function normalizeRow(array $row, array $numericColumns): array
{
    $normalized = [];

    foreach ($row as $column => $value) {
        if ($value === null) {
            $normalized[$column] = null;
            continue;
        }

        if (isset($numericColumns[$column])) {
            if ($value === '' || $value === 'NULL') {
                $normalized[$column] = null;
                continue;
            }
            $normalized[$column] = (int)$value;
            continue;
        }

        $normalized[$column] = (string)$value;
    }

    return $normalized;
}

/**
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function marshalDynamoItem(array $item): array
{
    $marshaled = [];

    foreach ($item as $key => $value) {
        $marshaled[$key] = marshalDynamoValue($value);
    }

    return $marshaled;
}

/**
 * @param mixed $value
 * @return array<string, mixed>
 */
function marshalDynamoValue($value): array
{
    if ($value === null) {
        return ['NULL' => true];
    }

    if (is_int($value) || is_float($value)) {
        return ['N' => (string)$value];
    }

    if (is_bool($value)) {
        return ['BOOL' => $value];
    }

    if (is_array($value)) {
        $list = [];
        foreach ($value as $entry) {
            $list[] = marshalDynamoValue($entry);
        }
        return ['L' => $list];
    }

    return ['S' => (string)$value];
}
