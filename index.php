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
    private Database $db1;
    private Database $db2;

    public function __construct(Database $db1, Database $db2)
    {
        $this->db1 = $db1;
        $this->db2 = $db2;
    }

    public function getTableReport(): array
    {
        $tablesDb1 = $this->db1->getTables();
        $tablesDb2 = $this->db2->getTables();

        $allTables = array_unique(array_merge($tablesDb1, $tablesDb2));
        sort($allTables);

        $report = [];

        foreach ($allTables as $table) {

            $existsInDb1 = in_array($table, $tablesDb1, true);
            $existsInDb2 = in_array($table, $tablesDb2, true);

            $db1Columns = $existsInDb1 ? $this->db1->getColumns($table) : [];
            $db2Columns = $existsInDb2 ? $this->db2->getColumns($table) : [];

            $extraInDb1 = array_diff($db1Columns, $db2Columns);
            $extraInDb2 = array_diff($db2Columns, $db1Columns);

            $report[] = [
                'table_name'        => $table,
                'db1_exists'        => $existsInDb1 ? 'Yes' : 'No',
                'db2_exists'        => $existsInDb2 ? 'Yes' : 'No',
                'db1_total_columns' => count($db1Columns),
                'db2_total_columns' => count($db2Columns),
                'db1_extra_columns' => count($extraInDb1),
                'db2_extra_columns' => count($extraInDb2),
            ];
        }

        return $report;
    }
}

/* ==========================
 | RUN SCRIPT
 |========================== */

try {
    $db1 = new Database($config['old']);
    $db2 = new Database($config['new']);

    $comparator = new SchemaComparator($db1, $db2);
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
    <th>OLD Extra Columns</th>
    <th>NEW Extra Columns</th>
</tr>";

foreach ($report as $row) {
    echo "<tr>
        <td>{$row['table_name']}</td>
        <td>{$row['db1_exists']}</td>
        <td>{$row['db2_exists']}</td>
        <td>{$row['db1_total_columns']}</td>
        <td>{$row['db2_total_columns']}</td>
        <td>{$row['db1_extra_columns']}</td>
        <td>{$row['db2_extra_columns']}</td>
    </tr>";
}

echo "</table>";
