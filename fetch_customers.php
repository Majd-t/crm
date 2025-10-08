<?php
session_start();
include "db.php";

$staff_id = $_SESSION['staff_id'];
$filters = [];
$params = [];
$types = '';

$sql = "SELECT c.id, c.name, c.email, c.phone, c.address, c.status, cs.name AS status_name, cs.color 
        FROM customers c 
        LEFT JOIN customer_statuses cs ON c.status_id = cs.id 
        WHERE c.assigned_staff_id = ?";
$filters[] = $sql;
$params[] = $staff_id;
$types .= 'i';

// Status filter
if (!empty($_POST['status']) && is_array($_POST['status'])) {
    $placeholders = implode(',', array_fill(0, count($_POST['status']), '?'));
    $filters[] = "c.status_id IN ($placeholders)";
    $params = array_merge($params, $_POST['status']);
    $types .= str_repeat('i', count($_POST['status']));
}

// Creation date filter
if (!empty($_POST['created_at'])) {
    list($start, $end) = explode(' to ', $_POST['created_at']);
    $filters[] = "c.created_at BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;
    $types .= 'ss';
}

// Last updated filter
if (!empty($_POST['last_updated'])) {
    list($start, $end) = explode(' to ', $_POST['last_updated']);
    $filters[] = "c.id IN (SELECT customer_id FROM customer_status_log WHERE created_at BETWEEN ? AND ?)";
    $params[] = $start;
    $params[] = $end;
    $types .= 'ss';
}

// Search filter
if (!empty($_POST['search'])) {
    $filters[] = "(c.name LIKE ? OR c.email LIKE ?)";
    $params[] = '%' . $_POST['search'] . '%';
    $params[] = '%' . $_POST['search'] . '%';
    $types .= 'ss';
}

// Phone filter
if (!empty($_POST['phone'])) {
    $filters[] = "c.phone LIKE ?";
    $params[] = '%' . $_POST['phone'] . '%';
    $types .= 's';
}

// Address filter
if (!empty($_POST['address'])) {
    $filters[] = "c.address LIKE ?";
    $params[] = '%' . $_POST['address'] . '%';
    $types .= 's';
}

// Activity type filter
if (!empty($_POST['activity_type'])) {
    $filters[] = "c.id IN (SELECT customer_id FROM customer_status_log WHERE type = ?)";
    $params[] = $_POST['activity_type'];
    $types .= 's';
}

// Activity count filter
if (!empty($_POST['activity_count_min']) || !empty($_POST['activity_count_max'])) {
    $filters[] = "c.id IN (SELECT customer_id FROM customer_status_log GROUP BY customer_id HAVING COUNT(*) BETWEEN ? AND ?)";
    $params[] = $_POST['activity_count_min'] ?: 0;
    $params[] = $_POST['activity_count_max'] ?: 999999;
    $types .= 'ii';
}

// Custom filters
if (!empty($_POST['custom_field'])) {
    $custom_conditions = [];
    foreach ($_POST['custom_field'] as $i => $field) {
        $operator = $_POST['custom_operator'][$i];
        $value = $_POST['custom_value'][$i];
        $logic = isset($_POST['custom_logic'][$i]) ? $_POST['custom_logic'][$i] : 'AND';
        
        if ($field == 'status_id') {
            $custom_conditions[] = "c.status_id = ? $logic";
            $params[] = $value;
            $types .= 'i';
        } elseif (in_array($field, ['name', 'email', 'phone', 'address', 'status'])) {
            $custom_conditions[] = "c.$field LIKE ? $logic";
            $params[] = '%' . $value . '%';
            $types .= 's';
        } elseif ($field == 'created_at' || $field == 'last_updated') {
            $custom_conditions[] = ($field == 'last_updated' ? 
                "c.id IN (SELECT customer_id FROM customer_status_log WHERE created_at $operator ?)" : 
                "c.$field $operator ?") . " $logic";
            $params[] = $value;
            $types .= 's';
        } elseif ($field == 'activity_count') {
            $custom_conditions[] = "c.id IN (SELECT customer_id FROM customer_status_log GROUP BY customer_id HAVING COUNT(*) $operator ?) $logic";
            $params[] = $value;
            $types .= 'i';
        } elseif ($field == 'activity_type') {
            $custom_conditions[] = "c.id IN (SELECT customer_id FROM customer_status_log WHERE type = ?) $logic";
            $params[] = $value;
            $types .= 's';
        }
    }
    $filters[] = '(' . rtrim(implode(' ', $custom_conditions), ' AND OR') . ')';
}

$sql = implode(' AND ', $filters);
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Query preparation failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$list = '';
$kanban = [];
$count = 0;

while ($row = $result->fetch_assoc()) {
    $count++;
    // List View
    $list .= "<tr class='clickable' onclick=\"window.location='client_profile.php?id={$row['id']}';\">
        <td class='px-4 py-2'>" . htmlspecialchars($row['name']) . "</td>
        <td class='px-4 py-2'>" . htmlspecialchars($row['email']) . "</td>
        <td class='px-4 py-2'>" . htmlspecialchars($row['phone']) . "</td>
        <td class='px-4 py-2'>" . htmlspecialchars($row['address']) . "</td>
        <td class='px-4 py-2'><span style='background-color: {$row['color']}; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px;'>" . htmlspecialchars($row['status_name']) . "</span></td>
        <td class='px-4 py-2'><a href='client_profile.php?id={$row['id']}' class='text-blue-600 hover:underline'>Profili Görüntüle</a></td>
    </tr>";

    // Kanban View
    if (!isset($kanban[$row['status_id']])) {
        $kanban[$row['status_id']] = '';
    }
    $kanban[$row['status_id']] .= "<div class='kanban-card card' data-customer-id='{$row['id']}'>
        <p class='font-bold'>" . htmlspecialchars($row['name']) . "</p>
        <p class='text-sm text-gray-500'>" . htmlspecialchars($row['email']) . "</p>
        <a href='client_profile.php?id={$row['id']}' class='text-blue-600 hover:underline'>Profili Görüntüle</a>
    </div>";
}

echo json_encode(['list' => $list, 'kanban' => $kanban, 'count' => $count]);
$stmt->close();
$conn->close();
?>