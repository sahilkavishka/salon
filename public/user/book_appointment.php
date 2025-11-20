<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth();

if (!isset($_GET['salon_id']) || !is_numeric($_GET['salon_id'])) {
    $_SESSION['error_message'] = 'Salon not specified.';
    header('Location: salon_view.php');
    exit;
}

$salon_id = intval($_GET['salon_id']);
$user_id = $_SESSION['id'];

// Fetch salon details
$stmt = $pdo->prepare("SELECT id, name, opening_time, closing_time, slot_duration FROM salons WHERE id=?");
$stmt->execute([$salon_id]);
$salon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salon) {
    $_SESSION['error_message'] = 'Salon not found.';
    header('Location: salon_view.php');
    exit;
}

// Fetch services
$serviceStmt = $pdo->prepare("SELECT id, name, price, duration FROM services WHERE salon_id=? ORDER BY name ASC");
$serviceStmt->execute([$salon_id]);
$services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

// Submit booking
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = intval($_POST['service_id']);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';

    if ($service_id <= 0) $errors[] = "Select a service.";
    if ($appointment_date == "") $errors[] = "Select a date.";
    if ($appointment_time == "") $errors[] = "Select a time.";

    if ($appointment_date < date("Y-m-d")) $errors[] = "Cannot book past date.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO appointments (salon_id, user_id, service_id, appointment_date, appointment_time, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $salon_id,
            $user_id,
            $service_id,
            $appointment_date,
            $appointment_time
        ]);

        $_SESSION['success_message'] = "Appointment booked successfully!";
        header("Location: my_appointments.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Book Appointment - <?= htmlspecialchars($salon['name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

<style>
.slot-box {
    padding: 10px 15px;
    border-radius: 10px;
    border: 2px solid #007bff;
    cursor: pointer;
    margin: 5px;
    display: inline-block;
    transition: 0.25s;
    font-weight: 500;
}
.slot-box:hover {
    background: #007bff;
    color: white;
}
.slot-box.selected {
    background: #007bff;
    color: #fff;
    border-color: #0056b3;
}
#slotsArea {
    display: flex;
    flex-wrap: wrap;
}
#loadingSlots {
    display: none;
    text-align: center;
    font-style: italic;
}
</style>
</head>
<body>

<div class="container mt-4" style="max-width:550px;">
    <div class="card shadow">
        <div class="card-header bg-dark text-white">
            <h4 class="mb-0">Book Appointment</h4>
            <small>Salon: <?= htmlspecialchars($salon['name']) ?></small>
        </div>

        <div class="card-body">

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e) echo $e . "<br>"; ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="form-label">Service</label>
                    <select name="service_id" class="form-select" required>
                        <option value="">Select service</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['name']) ?> - Rs <?= number_format($s['price'],2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Appointment Date</label>
                    <input type="date" name="appointment_date" id="datePicker"
                           class="form-control" min="<?= date("Y-m-d") ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Available Time Slots</label>

                    <div id="loadingSlots">Loading available slots...</div>

                    <div id="slotsArea">
                        <p class="text-muted">Select a date to load slots</p>
                    </div>

                    <input type="hidden" name="appointment_time" id="selectedTime" required>
                </div>

                <button class="btn btn-primary w-100">Book Appointment</button>

            </form>

        </div>
    </div>
</div>


<script>
let selectedSlot = null;

document.getElementById('datePicker').addEventListener('change', function () {

    let date = this.value;
    let slotArea = document.getElementById('slotsArea');
    let loading = document.getElementById('loadingSlots');
    let hiddenInput = document.getElementById('selectedTime');

    slotArea.innerHTML = "";
    hiddenInput.value = "";

    if (!date) return;

    loading.style.display = "block";

    fetch(`fetch_slots.php?salon_id=<?= $salon_id ?>&date=${date}`)
        .then(res => res.json())
        .then(data => {
            loading.style.display = "none";
            slotArea.innerHTML = "";

            if (data.length === 0) {
                slotArea.innerHTML = "<p class='text-danger'>No available slots for this date.</p>";
                return;
            }

            data.forEach(t => {
                let div = document.createElement("div");
                div.className = "slot-box";
                div.textContent = t;

                div.addEventListener("click", function () {
                    document.querySelectorAll(".slot-box").forEach(x => x.classList.remove("selected"));
                    this.classList.add("selected");
                    hiddenInput.value = t;
                });

                slotArea.appendChild(div);
            });
        });
});
</script>

</body>
</html>
