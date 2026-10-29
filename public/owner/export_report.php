<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');

$owner_id = $_SESSION['id'];

// Get all appointments belonging to salons owned by this owner
$stmt = $pdo->prepare("
    SELECT 
        a.id AS booking_id,
        sa.name AS salon_name,
        u.username AS customer_name,
        u.phone AS customer_phone,
        u.email AS customer_email,
        s.name AS service_name,
        COALESCE(st.name, 'Any Stylist') AS stylist_name,
        s.price,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.created_at
    FROM appointments a
    JOIN salons sa ON a.salon_id = sa.id
    JOIN users u ON a.user_id = u.id
    JOIN services s ON a.service_id = s.id
    LEFT JOIN salon_staff st ON a.staff_id = st.id
    WHERE sa.owner_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$owner_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output CSV headers
$filename = "salonora_sales_report_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header row
fputcsv($output, [
    'Booking ID',
    'Salon Name',
    'Customer Name',
    'Customer Phone',
    'Customer Email',
    'Service',
    'Stylist',
    'Price (LKR)',
    'Appointment Date',
    'Appointment Time',
    'Status',
    'Booked On'
]);

foreach ($rows as $r) {
    fputcsv($output, [
        '#' . $r['booking_id'],
        $r['salon_name'],
        $r['customer_name'],
        $r['customer_phone'] ?? 'N/A',
        $r['customer_email'],
        $r['service_name'],
        $r['stylist_name'],
        number_format($r['price'], 2, '.', ''),
        $r['appointment_date'],
        $r['appointment_time'],
        ucfirst($r['status']),
        $r['created_at']
    ]);
}

fclose($output);
exit;
