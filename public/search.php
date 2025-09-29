<?php
$host = "localhost";
$dbname = "salon_finder";
$username = "root";
$password = "";

header("Content-Type: application/json");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!isset($_GET['location']) || empty($_GET['location'])) {
        echo json_encode(["error" => "No location provided"]);
        exit;
    }

    $location = "%" . $_GET['location'] . "%"; // allow partial matches

    $stmt = $pdo->prepare("SELECT * FROM salons WHERE address LIKE ?");
    $stmt->execute([$location]);

    $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($salons) {
        echo json_encode($salons);
    } else {
        echo json_encode(["error" => "No salons found for this location"]);
    }

} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
