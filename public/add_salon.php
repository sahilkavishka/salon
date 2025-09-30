<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "owner") {
    die("Access denied.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $address = $_POST["address"];
    $lat = $_POST["latitude"];
    $lng = $_POST["longitude"];
    $type = $_POST["type"];
    $rating = $_POST["rating"];

    $stmt = $pdo->prepare("INSERT INTO salons (name, address, latitude, longitude, type, rating) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$name, $address, $lat, $lng, $type, $rating])) {
        echo "Salon added successfully.";
    } else {
        echo "Error adding salon.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Add Salon</title></head>
<body>
<h2>Add Salon</h2>
<form method="post">
  <input type="text" name="name" placeholder="Salon Name" required><br>
  <input type="text" name="address" placeholder="Address" required><br>
  <input type="text" name="latitude" placeholder="Latitude" required><br>
  <input type="text" name="longitude" placeholder="Longitude" required><br>
  <select name="type">
    <option value="beauty">Beauty</option>
    <option value="barber">Barber</option>
    <option value="spa">Spa</option>
  </select><br>
  <input type="number" step="0.1" name="rating" placeholder="Rating (0-5)"><br>
  <button type="submit">Add Salon</button>
</form>
</body>
</html>
