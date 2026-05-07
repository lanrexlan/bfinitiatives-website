<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<h2>Database Tables Structure</h2>";

    // Get all tables in the public schema
    $tableQuery = "SELECT table_name 
                   FROM information_schema.tables 
                   WHERE table_schema = 'public'";
    $tables = $conn->query($tableQuery);

    while ($table = $tables->fetch(PDO::FETCH_ASSOC)) {
        $tableName = $table['table_name'];
        echo "<h3>Table: " . htmlspecialchars($tableName) . "</h3>";

        // Get columns for each table
        $columnQuery = "SELECT column_name, data_type, character_maximum_length, is_nullable 
                       FROM information_schema.columns 
                       WHERE table_name = '$tableName' 
                       ORDER BY ordinal_position";
        $columns = $conn->query($columnQuery);

        echo "<table border='1'>
              <tr>
                <th>Column Name</th>
                <th>Data Type</th>
                <th>Max Length</th>
                <th>Nullable</th>
              </tr>";

        while ($column = $columns->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['column_name']) . "</td>";
            echo "<td>" . htmlspecialchars($column['data_type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['character_maximum_length'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($column['is_nullable']) . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";

        // Show sample data (first 5 rows)
        echo "<h4>Sample Data (up to 5 rows):</h4>";
        $dataQuery = "SELECT * FROM " . $tableName . " LIMIT 5";
        $data = $conn->query($dataQuery);
        
        if ($data->rowCount() > 0) {
            $firstRow = true;
            echo "<table border='1'>";
            
            while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
                if ($firstRow) {
                    echo "<tr>";
                    foreach ($row as $key => $value) {
                        echo "<th>" . htmlspecialchars($key) . "</th>";
                    }
                    echo "</tr>";
                    $firstRow = false;
                }
                
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table><br><br>";
        } else {
            echo "No data in table<br><br>";
        }
    }

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
?>