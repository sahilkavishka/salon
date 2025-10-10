<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "owner") {
    die("Access denied.");
}

$owner_id = $_SESSION['user_id'];

// Handle Delete request
if (isset($_GET['delete'])) {
    $salon_id = (int)$_GET['delete'];

    // Delete salon images from uploads folder
    $stmt = $pdo->prepare("SELECT logo, images FROM salons WHERE id = ? AND owner_id = ?");
    $stmt->execute([$salon_id, $owner_id]);
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($salon) {
        if ($salon['logo'] && file_exists("../uploads/".$salon['logo'])) {
            unlink("../uploads/".$salon['logo']);
        }
        $images = json_decode($salon['images'], true);
        if ($images) {
            foreach ($images as $img) {
                if (file_exists("../uploads/".$img)) unlink("../uploads/".$img);
            }
        }

        // Delete salon from DB
        $del = $pdo->prepare("DELETE FROM salons WHERE id = ? AND owner_id = ?");
        $del->execute([$salon_id, $owner_id]);
        header("Location: manage_salons.php");
        exit;
    }
}

// Fetch salons for this owner
$stmt = $pdo->prepare("SELECT * FROM salons WHERE owner_id = ? ORDER BY id DESC");
$stmt->execute([$owner_id]);
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Salons</title>
<link rel="stylesheet" href="assets/css/manage_salons.css">
</head>
<body>

<h2>Manage Your Salons</h2>
<div class="container">
<?php if ($salons): ?>
    <?php foreach ($salons as $salon): ?>
        <div class="card">
            <?php if ($salon['logo']): ?>
                <img src="../uploads/<?= htmlspecialchars($salon['logo']) ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <h3><?= htmlspecialchars($salon['name']) ?></h3>
            <p><?= htmlspecialchars($salon['address']) ?></p>
            <span class="type"><?= htmlspecialchars($salon['type']) ?></span>

            <div class="images">
                <?php
                $images = json_decode($salon['images'], true);
                if ($images):
                    foreach ($images as $img):
                        if($img):
                ?>
                    <img src="../uploads/<?= htmlspecialchars($img) ?>" alt="Salon Image">
                <?php
                        endif;
                    endforeach;
                endif;
                ?>
            </div>

            <a href="edit_salon.php?id=<?= $salon['id'] ?>" class="edit">Edit</a>
            <a href="manage_salons.php?delete=<?= $salon['id'] ?>" class="delete" onclick="return confirm('Are you sure you want to delete this salon?');">Delete</a>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p style="text-align:center; grid-column:1/-1;">You have not added any salons yet.</p>
<?php endif; ?>
</div>

</body>
</html>
