<?php
// public/owner/fetch_appointments.php
session_start();
require_once __DIR__.'/../../config.php';
require_once __DIR__.'/../auth_check.php';
checkAuth();
header('Content-Type: application/json');

if(!isset($_SESSION['role']) || $_SESSION['role']!=='owner'){ echo json_encode(['error'=>'Unauthorized']); exit; }

$owner_id=$_SESSION['id'];

// Get all salon IDs for this owner
$stmt=$pdo->prepare("SELECT id FROM salons WHERE owner_id=?");
$stmt->execute([$owner_id]);
$salons=$stmt->fetchAll(PDO::FETCH_COLUMN);

if(!$salons) $salons=[0]; // avoid empty IN clause

$statuses=['pending','confirmed','completed','cancelled'];
$result=[];

foreach($statuses as $status){
    $in = implode(',',array_fill(0,count($salons),'?'));
    if($status==='confirmed'){
        $sql="SELECT a.*, u.username AS user_name, s.name AS service_name, s.price AS service_price, sal.name AS salon_name 
              FROM appointments a 
              JOIN users u ON u.id=a.user_id 
              JOIN services s ON s.id=a.service_id 
              JOIN salons sal ON sal.id=a.salon_id 
              WHERE a.status='confirmed' AND a.salon_id IN ($in) AND CONCAT(a.appointment_date,' ',a.appointment_time) >= NOW()
              ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    } else {
        $sql="SELECT a.*, u.username AS user_name, s.name AS service_name, s.price AS service_price, sal.name AS salon_name 
              FROM appointments a 
              JOIN users u ON u.id=a.user_id 
              JOIN services s ON s.id=a.service_id 
              JOIN salons sal ON sal.id=a.salon_id 
              WHERE a.status=? AND a.salon_id IN ($in)
              ORDER BY a.created_at DESC";
    }
    $stmt=$pdo->prepare($sql);
    if($status==='confirmed') $stmt->execute($salons);
    else $stmt->execute(array_merge([$status],$salons));
    $result[$status]=$stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($result);
