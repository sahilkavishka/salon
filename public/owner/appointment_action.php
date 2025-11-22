<?php
// public/owner/appointment_action.php
session_start();
require_once __DIR__.'/../../config.php';
require_once __DIR__.'/../auth_check.php';
checkAuth();
header('Content-Type: application/json');

if(!isset($_SESSION['role']) || $_SESSION['role']!=='owner'){ echo json_encode(['error'=>'Unauthorized']); exit; }

$csrf=$_POST['csrf_token']??'';
$appt_id=$_POST['appointment_id']??'';
$action=$_POST['action']??'';

if(!$csrf || $csrf!==$_SESSION['csrf_token']){ echo json_encode(['error'=>'Invalid CSRF']); exit; }
if(!$appt_id || !is_numeric($appt_id)){ echo json_encode(['error'=>'Invalid appointment ID']); exit; }

$appt_id=(int)$appt_id;
$owner_id=$_SESSION['id'];

// Verify appointment belongs to owner's salon
$stmt=$pdo->prepare("SELECT * FROM appointments WHERE id=? AND salon_id IN (SELECT id FROM salons WHERE owner_id=?)");
$stmt->execute([$appt_id,$owner_id]);
$appt=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$appt){ echo json_encode(['error'=>'Appointment not found']); exit; }

try{
    if($action==='confirm') $stmt=$pdo->prepare("UPDATE appointments SET status='confirmed', updated_at=NOW() WHERE id=?");
    elseif($action==='reject') $stmt=$pdo->prepare("UPDATE appointments SET status='cancelled', updated_at=NOW() WHERE id=?");
    elseif($action==='complete') $stmt=$pdo->prepare("UPDATE appointments SET status='completed', updated_at=NOW() WHERE id=?");
    else{ echo json_encode(['error'=>'Invalid action']); exit; }
    $stmt->execute([$appt_id]);
    echo json_encode(['success'=>true]);
}catch(PDOException $e){
    echo json_encode(['error'=>'Database error: '.$e->getMessage()]);
}
