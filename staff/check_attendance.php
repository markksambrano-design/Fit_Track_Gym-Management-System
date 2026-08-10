<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if logged in as staff
if (!isset($_SESSION['staff_logged_in']) || !$_SESSION['staff_logged_in']) {
    die("Not logged in as staff");
}

include '../includes/db.php';

echo "<h2>Attendance Check - Debug Mode</h2>";

// Get current staff ID
$currentStaffId = $_SESSION['staff_id'] ?? null;
echo "<p><strong>Your Staff ID:</strong> " . $currentStaffId . "</p>";

// Get staff database ID
$stmt = $conn->prepare("SELECT id, staff_id, first_name, last_name FROM staff WHERE staff_id = ?");
$stmt->bind_param("s", $currentStaffId);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();

if ($staff) {
    echo "<p><strong>Staff Found:</strong> " . $staff['first_name'] . " " . $staff['last_name'] . " (DB ID: " . $staff['id'] . ")</p>";
    
    // Check all possible tables
    $tables = ['staff_attendance', 'attendance', 'member_attendance'];
    
    foreach ($tables as $table) {
        echo "<h3>Checking table: $table</h3>";
        
        $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck->num_rows > 0) {
            echo "<p style='color: green;'>✓ Table $table EXISTS</p>";
            
            // Get table structure
            $structure = $conn->query("DESCRIBE $table");
            echo "<p><strong>Columns:</strong></p>";
            echo "<ul>";
            while ($row = $structure->fetch_assoc()) {
                echo "<li>" . $row['Field'] . " (" . $row['Type'] . ")</li>";
            }
            echo "</ul>";
            
            // Try to find records with different column combinations
            $queries = [
                "SELECT COUNT(*) as count FROM $table WHERE staff_id = ?",
                "SELECT COUNT(*) as count FROM $table WHERE member_id = ?",
                "SELECT COUNT(*) as count FROM $table WHERE id = ?"
            ];
            
            foreach ($queries as $i => $query) {
                try {
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $staff['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result->fetch_assoc()['count'];
                    
                    echo "<p><strong>Query " . ($i + 1) . ":</strong> $count records found</p>";
                    
                    if ($count > 0) {
                        echo "<p style='color: green;'>✓ FOUND RECORDS!</p>";
                        
                        // Show the actual records
                        $showQuery = str_replace("COUNT(*) as count", "*", $query) . " ORDER BY id DESC LIMIT 5";
                        $stmt = $conn->prepare($showQuery);
                        $stmt->bind_param("i", $staff['id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                        $first = true;
                        while ($row = $result->fetch_assoc()) {
                            if ($first) {
                                echo "<tr>";
                                foreach (array_keys($row) as $key) {
                                    echo "<th>" . $key . "</th>";
                                }
                                echo "</tr>";
                                $first = false;
                            }
                            echo "<tr>";
                            foreach ($row as $value) {
                                echo "<td>" . ($value ?: 'NULL') . "</td>";
                            }
                            echo "</tr>";
                        }
                        echo "</table>";
                        break 2; // Found data, exit both loops
                    }
                } catch (Exception $e) {
                    echo "<p style='color: red;'>Query failed: " . $e->getMessage() . "</p>";
                }
            }
            
        } else {
            echo "<p style='color: red;'>✗ Table $table DOES NOT EXIST</p>";
        }
        echo "<hr>";
    }
    
} else {
    echo "<p style='color: red;'>Staff not found in database!</p>";
}

// Also check if there are any records at all
echo "<h3>All Attendance Records (Last 10)</h3>";
$tables = ['staff_attendance', 'attendance', 'member_attendance'];

foreach ($tables as $table) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
    if ($tableCheck->num_rows > 0) {
        try {
            $result = $conn->query("SELECT * FROM $table ORDER BY id DESC LIMIT 10");
            if ($result && $result->num_rows > 0) {
                echo "<h4>Table: $table</h4>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                $first = true;
                while ($row = $result->fetch_assoc()) {
                    if ($first) {
                        echo "<tr>";
                        foreach (array_keys($row) as $key) {
                            echo "<th>" . $key . "</th>";
                        }
                        echo "</tr>";
                        $first = false;
                    }
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . ($value ?: 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            }
        } catch (Exception $e) {
            echo "<p>Error reading $table: " . $e->getMessage() . "</p>";
        }
    }
}

?>
