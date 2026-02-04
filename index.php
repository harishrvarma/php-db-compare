<?php
/**
 * Database Schema Comparison Script (MySQLi Version)
 */

/* ==========================
 | CONFIGURATION
 |========================== */

$config = [
    'old' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'dbname' => 'shopware',
    ],
    'new' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'dbname' => 'shopware_6762',
    ],
];

/* ==========================
 | DATABASE CLASS (MySQLi)
 |========================== */

class Database
{
    private mysqli $conn;
    private string $dbName;

    public function __construct(array $config)
    {
        $this->dbName = $config['dbname'];

        $this->conn = new mysqli(
            $config['host'],
            $config['user'],
            $config['password'],
            $config['dbname']
        );

        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function getTables(): array
    {
        $tables = [];
        $result = $this->conn->query("SHOW TABLES");

        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }

        return $tables;
    }

    public function getColumns(string $table): array
    {
        $columns = [];
        $result = $this->conn->query("DESCRIBE `$table`");

        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        return $columns;
    }
}

/* ==========================
 | SCHEMA COMPARATOR
 |========================== */

class SchemaComparator
{
    private Database $old;
    private Database $new;

    public function __construct(Database $old, Database $new)
    {
        $this->old = $old;
        $this->new = $new;
    }

    public function getTableReport(): array
    {
        $tablesOld = $this->old->getTables();
        $tablesNew = $this->new->getTables();

        $allTables = array_unique(array_merge($tablesOld, $tablesNew));
        sort($allTables);

        $report = [];

        foreach ($allTables as $table) {

            $existsOld = in_array($table, $tablesOld, true);
            $existsNew = in_array($table, $tablesNew, true);

            $oldCols = $existsOld ? $this->old->getColumns($table) : [];
            $newCols = $existsNew ? $this->new->getColumns($table) : [];

            $extraOld = array_diff($oldCols, $newCols);
            $extraNew = array_diff($newCols, $oldCols);

            $report[] = [
                'table_name' => $table,
                'old_exists' => $existsOld ? 'Yes' : 'No',
                'new_exists' => $existsNew ? 'Yes' : 'No',

                'old_total_columns' => count($oldCols),
                'new_total_columns' => count($newCols),

                'old_extra_count' => count($extraOld),
                'new_extra_count' => count($extraNew),

                // 🔥 NEW COLUMNS (names)
                'old_extra_names' => implode('<br>', $extraOld),
                'new_extra_names' => implode('<br>', $extraNew),
            ];
        }

        return $report;
    }
}

/* ==========================
 | RUN SCRIPT
 |========================== */

try {
    $oldDb = new Database($config['old']);
    $newDb = new Database($config['new']);

    $comparator = new SchemaComparator($oldDb, $newDb);
    $report = $comparator->getTableReport();

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

/* ==========================
 | OUTPUT
 |========================== */

echo "<h2>Database Schema Comparison Report</h2>";

echo "<table border='1' cellpadding='8' cellspacing='0'>
<tr style='background:#f2f2f2'>
    <th>Table Name</th>
    <th>OLD Exists</th>
    <th>NEW Exists</th>
    <th>OLD Total Columns</th>
    <th>NEW Total Columns</th>
    <th>OLD Extra Count</th>
    <th>NEW Extra Count</th>
    <th>OLD Extra Columns</th>
    <th>NEW Extra Columns</th>
</tr>";

foreach ($report as $row) {
    echo "<tr>
        <td>{$row['table_name']}</td>
        <td>{$row['old_exists']}</td>
        <td>{$row['new_exists']}</td>
        <td>{$row['old_total_columns']}</td>
        <td>{$row['new_total_columns']}</td>
        <td>{$row['old_extra_count']}</td>
        <td>{$row['new_extra_count']}</td>
        <td>{$row['old_extra_names']}</td>
        <td>{$row['new_extra_names']}</td>
    </tr>";
}

echo "</table>";
