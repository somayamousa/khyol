<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$date = $_GET['date'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if (!$date || !$id || !in_array($type, ['photoshoot', 'clinic'])) {
    echo json_encode([]);
    exit;
}

if ($type === 'photoshoot') {
    // جلب الساعات المحجوزة لهذا المصور في هذا التاريخ
    $stmt = $conn->prepare("
        SELECT TIME_FORMAT(pb.session_time, '%H:%i') AS slot
        FROM photoshoot_bookings pb
        JOIN photoshoot_packages pkg ON pkg.id = pb.package_id
        WHERE pkg.photographer_id = ?
          AND pb.session_date = ?
          AND pb.status != 'cancelled'
    ");
    $stmt->execute([$id, $date]);
} else {
    // جلب الساعات المحجوزة لهذه العيادة في هذا التاريخ
    $stmt = $conn->prepare("
        SELECT TIME_FORMAT(appointment_time, '%H:%i') AS slot
        FROM clinic_appointments
        WHERE clinic_id = ?
          AND appointment_date = ?
          AND status != 'cancelled'
    ");
    $stmt->execute([$id, $date]);
}

$booked = array_column($stmt->fetchAll(), 'slot');
echo json_encode($booked);
