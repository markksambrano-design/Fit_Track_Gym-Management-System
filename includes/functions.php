<?php
require_once 'db.php';
require_once 'cache.php';

// Ensure attendance table exists with correct structure
function ensureAttendanceTable() {
    global $conn;
    
    $createTable = "CREATE TABLE IF NOT EXISTS attendance (
        id INT(11) NOT NULL AUTO_INCREMENT,
        member_id INT(11) NOT NULL,
        member_code VARCHAR(50) NOT NULL,
        date DATE NOT NULL,
        time_in DATETIME DEFAULT NULL,
        time_out DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY member_id (member_id),
        KEY idx_member_date (member_id, date),
        KEY idx_date (date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($createTable)) {
        error_log("Error creating attendance table: " . $conn->error);
        return false;
    }
    
    return true;
}

// Get total members count
function getTotalMembers() {
    global $conn, $cache;
    
    // Try to get from cache first
    $cache_key = 'total_members';
    $cached = $cache->get($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    try {
        $sql = "SELECT COUNT(*) as total FROM members";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $total = $row['total'];
            
            // Cache for 10 minutes
            $cache->set($cache_key, $total, 600);
            return $total;
        }
    } catch (Exception $e) {
        error_log("Error getting total members: " . $e->getMessage());
    }
    return 0;
}

// Get total staff count
function getTotalStaff() {
    global $conn, $cache;
    
    // Try to get from cache first
    $cache_key = 'total_staff';
    $cached = $cache->get($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    try {
        $sql = "SELECT COUNT(*) as total FROM staff";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $total = $row['total'];
            
            // Cache for 10 minutes
            $cache->set($cache_key, $total, 600);
            return $total;
        }
    } catch (Exception $e) {
        error_log("Error getting total staff: " . $e->getMessage());
    }
    return 0;
}

// Get today's attendance count
function getTodayAttendance() {
    global $conn, $cache;
    
    $today = date('Y-m-d');
    $cache_key = 'today_attendance_' . $today;
    
    // Try to get from cache first
    $cached = $cache->get($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    try {
        // Check if separate tables exist, if not fallback to attendance table
        $memberTableExists = $conn->query("SHOW TABLES LIKE 'member_attendance'");
        $staffTableExists = $conn->query("SHOW TABLES LIKE 'staff_attendance'");
        
        if ($memberTableExists && $memberTableExists->num_rows > 0 && 
            $staffTableExists && $staffTableExists->num_rows > 0) {
            // Use separate tables
            $sql = "SELECT 
                        (SELECT COUNT(*) FROM member_attendance WHERE DATE(date) = ?) +
                        (SELECT COUNT(*) FROM staff_attendance WHERE DATE(date) = ?) as total";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Error preparing statement for getTodayAttendance: " . $conn->error);
                return 0;
            }
            $stmt->bind_param("ss", $today, $today);
        } else {
            // Fallback to old attendance table
            $sql = "SELECT COUNT(*) as total FROM attendance WHERE DATE(date) = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Error preparing statement for getTodayAttendance: " . $conn->error);
                // Fallback to direct query
                $result = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE DATE(date) = '$today'");
                if ($result && $result->num_rows > 0) {
                    $total = $result->fetch_assoc()['total'];
                    // Cache for 2 minutes (attendance changes frequently)
                    $cache->set($cache_key, $total, 120);
                    return $total;
                }
                return 0;
            }
            $stmt->bind_param("s", $today);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $total = $row['total'];
            $stmt->close();
            
            // Cache for 2 minutes (attendance changes frequently)
            $cache->set($cache_key, $total, 120);
            return $total;
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Error in getTodayAttendance: " . $e->getMessage());
    }
    return 0;
}

// Get active users (users who checked in today and haven't checked out)
function getActiveNow() {
    global $conn;
    
    // Get today's date from database to ensure consistency
    $dbDateResult = $conn->query("SELECT CURDATE() as today");
    if ($dbDateResult && $dbDateResult->num_rows > 0) {
        $today = $dbDateResult->fetch_assoc()['today'];
    } else {
        $today = date('Y-m-d');
    }
    
    // Add error handling and debugging
    try {
        // Check if member_attendance table exists, if not fallback to attendance table
        $tableExists = $conn->query("SHOW TABLES LIKE 'member_attendance'");
        
        if ($tableExists && $tableExists->num_rows > 0) {
            // Use the new member_attendance table
            $sql = "SELECT COUNT(*) as total FROM member_attendance a 
                    JOIN members m ON a.member_id = m.id 
                    WHERE DATE(a.date) = ? AND a.time_out IS NULL";
        } else {
            // Fallback to old attendance table for backward compatibility
            $sql = "SELECT COUNT(*) as total FROM attendance a 
                    JOIN members m ON a.member_id = m.id 
                    WHERE DATE(a.date) = ? AND a.time_out IS NULL";
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing statement for getActiveNow: " . $conn->error);
            // Fallback to direct query
            if ($tableExists && $tableExists->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as total FROM member_attendance a 
                                       JOIN members m ON a.member_id = m.id 
                                       WHERE DATE(a.date) = '$today' AND a.time_out IS NULL");
            } else {
                $result = $conn->query("SELECT COUNT(*) as total FROM attendance a 
                                       JOIN members m ON a.member_id = m.id 
                                       WHERE DATE(a.date) = '$today' AND a.time_out IS NULL");
            }
            if ($result && $result->num_rows > 0) {
                return $result->fetch_assoc()['total'];
            }
            return 0;
        }
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['total'];
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Error in getActiveNow: " . $e->getMessage());
    }
    return 0;
}

// Get active staff members (staff who checked in today and haven't checked out)
function getActiveStaffNow() {
    global $conn;
    
    // Get today's date from database to ensure consistency
    $dbDateResult = $conn->query("SELECT CURDATE() as today");
    if ($dbDateResult && $dbDateResult->num_rows > 0) {
        $today = $dbDateResult->fetch_assoc()['today'];
    } else {
        $today = date('Y-m-d');
    }
    
    // Add error handling and debugging
    try {
        // Check if staff_attendance table exists, if not fallback to attendance table
        $tableExists = $conn->query("SHOW TABLES LIKE 'staff_attendance'");
        
        if ($tableExists && $tableExists->num_rows > 0) {
            // Use the new staff_attendance table
            $sql = "SELECT COUNT(*) as total FROM staff_attendance a 
                    JOIN staff s ON a.staff_id = s.id 
                    WHERE DATE(a.date) = ? AND a.time_out IS NULL";
        } else {
            // Fallback to old attendance table for backward compatibility
            $sql = "SELECT COUNT(*) as total FROM attendance a 
                    JOIN staff s ON a.member_id = s.id 
                    WHERE DATE(a.date) = ? AND a.time_out IS NULL";
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing statement for getActiveStaffNow: " . $conn->error);
            // Fallback to direct query
            if ($tableExists && $tableExists->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as total FROM staff_attendance a 
                                       JOIN staff s ON a.staff_id = s.id 
                                       WHERE DATE(a.date) = '$today' AND a.time_out IS NULL");
            } else {
                $result = $conn->query("SELECT COUNT(*) as total FROM attendance a 
                                       JOIN staff s ON a.member_id = s.id 
                                       WHERE DATE(a.date) = '$today' AND a.time_out IS NULL");
            }
            if ($result && $result->num_rows > 0) {
                return $result->fetch_assoc()['total'];
            }
            return 0;
        }
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['total'];
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Error in getActiveStaffNow: " . $e->getMessage());
    }
    return 0;
}

// Get weekly attendance data
function getWeeklyAttendance() {
    global $conn;
    $data = [];
    
    // Get the last 7 days
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_name = date('D', strtotime($date));
        
        // Get member attendance count
        $memberTableExists = $conn->query("SHOW TABLES LIKE 'member_attendance'");
        if ($memberTableExists && $memberTableExists->num_rows > 0) {
            $memberSql = "SELECT COUNT(*) as total FROM member_attendance WHERE DATE(date) = ?";
        } else {
            $memberSql = "SELECT COUNT(*) as total FROM attendance WHERE DATE(date) = ?";
        }
        $memberStmt = $conn->prepare($memberSql);
        $memberStmt->bind_param("s", $date);
        $memberStmt->execute();
        $memberResult = $memberStmt->get_result();
        $memberCount = 0;
        if ($memberResult && $memberResult->num_rows > 0) {
            $row = $memberResult->fetch_assoc();
            $memberCount = $row['total'];
        }
        $memberStmt->close();
        
        // Get staff attendance count
        $staffCount = 0;
        $staffTableExists = $conn->query("SHOW TABLES LIKE 'staff_attendance'");
        if ($staffTableExists->num_rows > 0) {
            $staffSql = "SELECT COUNT(*) as total FROM staff_attendance WHERE DATE(date) = ?";
            $staffStmt = $conn->prepare($staffSql);
            $staffStmt->bind_param("s", $date);
            $staffStmt->execute();
            $staffResult = $staffStmt->get_result();
            if ($staffResult && $staffResult->num_rows > 0) {
                $row = $staffResult->fetch_assoc();
                $staffCount = $row['total'];
            }
            $staffStmt->close();
        }
        
        // Get walk-in count
        $walkinCount = 0;
        $walkinSql = "SELECT COUNT(*) as total FROM walk_in WHERE DATE(visit_date) = ?";
        $walkinStmt = $conn->prepare($walkinSql);
        $walkinStmt->bind_param("s", $date);
        $walkinStmt->execute();
        $walkinResult = $walkinStmt->get_result();
        if ($walkinResult && $walkinResult->num_rows > 0) {
            $row = $walkinResult->fetch_assoc();
            $walkinCount = $row['total'];
        }
        $walkinStmt->close();
        
        $data[$day_name] = [
            'members' => $memberCount,
            'staff' => $staffCount,
            'walkins' => $walkinCount,
            'total' => $memberCount + $staffCount + $walkinCount
        ];
    }
    
    return $data;
}

// Get membership types distribution
function getMembershipTypes() {
    global $conn;
    $sql = "SELECT membership_type, COUNT(*) as total FROM members GROUP BY membership_type";
    $result = $conn->query($sql);
    
    $data = [
        'regular' => 0,
        'student' => 0,
        'vip' => 0
    ];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $type = $row['membership_type'];
            if (isset($data[$type])) {
                $data[$type] = $row['total'];
            }
        }
    }
    
    return $data;
}

// Get recent check-ins
function getRecentCheckins($limit = 10) {
    global $conn;
    
    // Check if member_attendance table exists, if not fallback to attendance table
    $tableExists = $conn->query("SHOW TABLES LIKE 'member_attendance'");
    
    if ($tableExists && $tableExists->num_rows > 0) {
        // Use the new member_attendance table
        $sql = "SELECT a.id, a.member_id, a.date, a.time_in, a.time_out, 
                       m.first_name, m.last_name, m.photo, m.membership_type 
                FROM member_attendance a 
                JOIN members m ON a.member_id = m.id 
                ORDER BY a.time_in DESC 
                LIMIT ?";
    } else {
        // Fallback to old attendance table
        $sql = "SELECT a.id, a.member_id, a.date, a.time_in, a.time_out, 
                       m.first_name, m.last_name, m.photo, m.membership_type 
                FROM attendance a 
                JOIN members m ON a.member_id = m.id 
                ORDER BY a.time_in DESC 
                LIMIT ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $checkins = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $checkins[] = $row;
        }
    }
    
    return $checkins;
}

// Get recent staff check-ins
function getRecentStaffCheckins($limit = 10) {
    global $conn;
    
    // Check if staff_attendance table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'staff_attendance'");
    if ($tableExists->num_rows == 0) {
        return [];
    }
    
    $sql = "SELECT a.*, s.first_name, s.last_name, s.photo, 'Staff' as position 
            FROM staff_attendance a 
            JOIN staff s ON a.staff_id = s.id 
            ORDER BY a.time_in DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $checkins = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $checkins[] = $row;
        }
    }
    
    return $checkins;
}

// Format time for display
function formatTime($datetime) {
    if (!$datetime) return '-';
    return date('M j, Y g:i A', strtotime($datetime));
}

// Format time for recent check-ins (shorter format)
function formatRecentTime($datetime) {
    if (!$datetime) return '-';
    return date('M j, Y g:i A', strtotime($datetime));
}

// Get status badge for attendance
function getAttendanceStatus($timeOut) {
    if (!$timeOut) {
        return '<span class="badge bg-warning text-dark">Active</span>';
    }
    return '<span class="badge bg-success">Completed</span>';
}

// Get user photo or default avatar
function getUserPhoto($photo, $name) {
    if ($photo && file_exists("../uploads/member_photos/" . $photo)) {
        return "../uploads/member_photos/" . $photo;
    }
    return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&size=30";
}

// Get today's walk-ins count
function getTodayWalkIns() {
    global $conn;
    
    // Use PHP date to avoid timezone issues
    $today = date('Y-m-d');
    
    try {
        $sql = "SELECT COUNT(*) as total FROM walk_in WHERE DATE(visit_date) = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing statement for getTodayWalkIns: " . $conn->error);
            return 0;
        }
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['total'];
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Error in getTodayWalkIns: " . $e->getMessage());
    }
    return 0;
}

// Get active walk-ins (no time_out)
function getActiveWalkIns() {
    global $conn;
    
    // Use PHP date to avoid timezone issues
    $today = date('Y-m-d');
    
    try {
        $sql = "SELECT COUNT(*) as total FROM walk_in WHERE DATE(visit_date) = ? AND time_out IS NULL";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing statement for getActiveWalkIns: " . $conn->error);
            return 0;
        }
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['total'];
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Error in getActiveWalkIns: " . $e->getMessage());
    }
    return 0;
}

// Get recent walk-ins
function getRecentWalkIns($limit = 5) {
    global $conn;
    
    try {
        $sql = "SELECT * FROM walk_in ORDER BY visit_date DESC, time_in DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing statement for getRecentWalkIns: " . $conn->error);
            return [];
        }
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $walkIns = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $walkIns[] = $row;
            }
        }
        $stmt->close();
        return $walkIns;
        
    } catch (Exception $e) {
        error_log("Error in getRecentWalkIns: " . $e->getMessage());
        return [];
    }
}

// Get walk-in status badge
function getWalkInStatus($timeOut) {
    if (!$timeOut) {
        return '<span class="badge bg-warning text-dark">Active</span>';
    }
    return '<span class="badge bg-success">Completed</span>';
}

// Get walk-in purpose badge
function getWalkInPurposeBadge($purpose) {
    switch ($purpose) {
        case 'gym_visit':
            return '<span class="badge bg-primary">Gym Visit</span>';
        case 'trial':
            return '<span class="badge bg-info">Trial</span>';
        case 'consultation':
            return '<span class="badge bg-secondary">Consultation</span>';
        case 'equipment_use':
            return '<span class="badge bg-dark">Equipment</span>';
        default:
            return '<span class="badge bg-light text-dark">' . ucfirst($purpose) . '</span>';
    }
}

// Get today's walk-in revenue
function getTodayWalkInRevenue() {
    global $conn;
    
    // Use PHP date to avoid timezone issues
    $today = date('Y-m-d');
    
    try {
        $sql = "SELECT SUM(payment_amount) as total FROM walk_in WHERE DATE(visit_date) = ? AND payment_amount > 0";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing statement for getTodayWalkInRevenue: " . $conn->error);
            return 0;
        }
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['total'] ?: 0;
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Error in getTodayWalkInRevenue: " . $e->getMessage());
    }
    return 0;
}

// Get recent announcements for dashboard
function getRecentAnnouncements($limit = 5, $audience_filter = null) {
    global $conn;
    
    try {
        $where_conditions = ["a.status = 'active'"];
        $params = [];
        $types = "";
        
        // Add audience filter if specified
        if ($audience_filter) {
            $where_conditions[] = "(a.target_audience = ? OR a.target_audience = 'all')";
            $params[] = $audience_filter;
            $types .= "s";
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $sql = "SELECT a.*, 
                       COALESCE(adm.name, 'System') as created_by_name
                FROM announcements a
                LEFT JOIN admins adm ON a.created_by = adm.id
                WHERE $where_clause
                ORDER BY a.is_pinned DESC, a.priority DESC, a.created_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing statement for getRecentAnnouncements: " . $conn->error);
            return [];
        }
        
        // Add limit parameter
        $params[] = $limit;
        $types .= "i";
        
        // Bind parameters if any exist
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $announcements = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $announcements[] = $row;
            }
        }
        $stmt->close();
        return $announcements;
        
    } catch (Exception $e) {
        error_log("Error in getRecentAnnouncements: " . $e->getMessage());
        return [];
    }
}

// Get announcement priority badge
function getAnnouncementPriorityBadge($priority) {
    switch ($priority) {
        case 'urgent':
            return '<span class="badge bg-danger">Urgent</span>';
        case 'high':
            return '<span class="badge bg-warning text-dark">High</span>';
        case 'medium':
            return '<span class="badge bg-info">Medium</span>';
        case 'low':
            return '<span class="badge bg-secondary">Low</span>';
        default:
            return '<span class="badge bg-light text-dark">Normal</span>';
    }
}

// Get announcement audience badge
function getAnnouncementAudienceBadge($audience) {
    switch ($audience) {
        case 'all':
            return '<span class="badge bg-primary">Everyone</span>';
        case 'members':
            return '<span class="badge bg-success">Members</span>';
        case 'staff':
            return '<span class="badge bg-info">Staff</span>';
        case 'walk_in':
            return '<span class="badge bg-warning text-dark">Walk-ins</span>';
        default:
            return '<span class="badge bg-light text-dark">' . ucfirst($audience) . '</span>';
    }
}

/**
 * Calculate staff daily payroll amount using fixed rates
 * Half day (4-5.99 hours) = P250 (fixed)
 * Whole day (6+ hours) = P500 (fixed)
 * Less than 4 hours = hours × 62.50
 * 
 * @param float $hours Hours worked (capped at 8 hours per day)
 * @return float Calculated payroll amount
 */
function calculateStaffDailyPayroll($hours) {
    // Cap at 8 hours per day
    $capped_hours = min($hours, 8.0);
    
    // Fixed payroll rates (not hours × rate)
    if ($capped_hours >= 6.0) {
        return 500.00; // Fixed whole day rate: P500 (6+ hours)
    } elseif ($capped_hours >= 4.0) {
        return 250.00; // Fixed half day rate: P250 (4-5.99 hours)
    } else {
        // For less than 4 hours, calculate hourly
        $hourly_rate = 62.50;
        return round($capped_hours * $hourly_rate, 2);
    }
}

/**
 * Calculate staff total payroll for multiple days
 * 
 * @param array $daily_hours Array of hours per day [date => hours]
 * @return float Total calculated payroll amount
 */
function calculateStaffTotalPayroll($daily_hours) {
    $total_pay = 0.0;
    
    foreach ($daily_hours as $date => $day_hours) {
        $total_pay += calculateStaffDailyPayroll($day_hours);
    }
    
    return round($total_pay, 2);
}

?>
