
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Salon</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    form { max-width: 400px; margin: auto; }
    input, select { width: 100%; padding: 8px; margin: 6px 0; }
    button { padding: 10px; background: #28a745; color: #fff; border: none; cursor: pointer; }
    button:hover { background: #218838; }
    .msg { margin: 10px 0; color: green; font-weight: bold; }
  </style>
</head>
<body>

<h2>Add a New Salon</h2>

<?php if (!empty($success)) echo "<div class='msg'>$success</div>"; ?>

<form method="POST" action="">
  <label for="name">Salon Name:</label>
  <input type="text" name="name" required>

  <label for="address">Address:</label>
  <input type="text" name="address" required>

  <label for="latitude">Latitude:</label>
  <input type="text" name="latitude" required>

  <label for="longitude">Longitude:</label>
  <input type="text" name="longitude" required>

  <label for="type">Type:</label>
  <select name="type">
    <option value="beauty">Beauty</option>
    <option value="barber">Barber</option>
    <option value="spa">Spa</option>
  </select>

  <label for="rating">Rating (0–5):</label>
  <input type="number" name="rating" step="0.1" min="0" max="5" required>

  <button type="submit">Add Salon</button>
</form>

</body>
</html>
