<?php
session_start();
require_once __DIR__ . '/../config.php';

$query = trim($_GET['query'] ?? '');
$results = [];

if ($query !== '') {
  $stmt = $pdo->prepare("
    SELECT id, name, address, lat, lng
    FROM salons
    WHERE name LIKE :query OR address LIKE :query
  ");
  $stmt->execute([':query' => "%$query%"]);
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Search Results - Salonora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php">💇‍♀️ Salonora</a>
    </div>
  </nav>

  <div class="container mt-5 pt-5">
    <h3 class="mb-4 text-center">Search Results for “<?= htmlspecialchars($query) ?>”</h3>

    <?php if (count($results) > 0): ?>
      <div class="row">
        <div class="col-md-6 mb-4">
          <ul class="list-group shadow-sm">
            <?php foreach ($results as $salon): ?>
              <li class="list-group-item">
                <strong><?= htmlspecialchars($salon['name']) ?></strong><br>
                <small><?= htmlspecialchars($salon['address']) ?></small>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="col-md-6">
          <div id="map" class="rounded"></div>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-warning text-center">No salons found.</div>
    <?php endif; ?>
  </div>

  <footer class="text-center text-light py-4 mt-5">
    <p class="mb-0">© <?= date("Y"); ?> Salonora</p>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet/dist/l
