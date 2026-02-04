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
 | DATABASE CLASS
 |========================== */

class Database
{
    private mysqli $conn;

    public function __construct(array $config)
    {
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

    public function getColumnTypes(string $table): array
    {
        $columns = [];
        $result = $this->conn->query("DESCRIBE `$table`");

        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row['Type'];
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

            $oldCols = $existsOld ? $this->old->getColumnTypes($table) : [];
            $newCols = $existsNew ? $this->new->getColumnTypes($table) : [];

            $oldNames = array_keys($oldCols);
            $newNames = array_keys($newCols);

            $extraOld = array_diff($oldNames, $newNames);
            $extraNew = array_diff($newNames, $oldNames);

            // datatype comparison
            $datatypeChanged = [];
            $commonCols = array_intersect($oldNames, $newNames);

            foreach ($commonCols as $col) {
                if ($oldCols[$col] !== $newCols[$col]) {
                    $datatypeChanged[] = $col;
                }
            }

            $report[] = [
                'table_name' => $table,
                'old_exists' => $existsOld ? 'Yes' : 'No',
                'new_exists' => $existsNew ? 'Yes' : 'No',

                'old_total_columns' => count($oldCols),
                'new_total_columns' => count($newCols),

                'old_extra_count' => count($extraOld),
                'new_extra_count' => count($extraNew),

                'old_extra_names' => implode(', ', $extraOld),
                'new_extra_names' => implode(', ', $extraNew),

                'datatype_changed' => empty($datatypeChanged) ? 'No' : 'Yes',
                'datatype_changed_cols' => implode(', ', $datatypeChanged),
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
 | SUMMARY COUNTS
 |========================== */

$oldExistsCount = 0;
$newExistsCount = 0;
$oldExtraTotal = 0;
$newExtraTotal = 0;

foreach ($report as $r) {
    if ($r['old_exists'] === 'Yes') $oldExistsCount++;
    if ($r['new_exists'] === 'Yes') $newExistsCount++;

    $oldExtraTotal += $r['old_extra_count'];
    $newExtraTotal += $r['new_extra_count'];
}

/* ==========================
 | OUTPUT
 |========================== */

echo "<h2>Database Schema Comparison Report</h2>";

echo "<table border='1' cellpadding='6' cellspacing='0'>
<tr style='background:#f2f2f2'>
    <th>Table</th>
    <th>OLD Exists ($oldExistsCount)</th>
    <th>NEW Exists ($newExistsCount)</th>
    <th>OLD Total</th>
    <th>NEW Total</th>
    <th>OLD Extra ($oldExtraTotal)</th>
    <th>NEW Extra ($newExtraTotal)</th>
    <th>OLD Extra Columns</th>
    <th>NEW Extra Columns</th>
    <th>Datatype Changed?</th>
    <th>Datatype Changed Columns</th>
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
        <td>{$row['datatype_changed']}</td>
        <td>{$row['datatype_changed_cols']}</td>
    </tr>";
}

echo "</table>";
