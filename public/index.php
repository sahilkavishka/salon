<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Salon Finder</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body { font-family: Arial, sans-serif; text-align: center; margin: 20px; }
    form { margin-bottom: 20px; }
    input, select, button { padding: 8px; margin: 5px; }
    #map { height: 450px; margin-top: 20px; }
    #results { margin-top: 20px; text-align: left; max-width: 600px; margin-left: auto; margin-right: auto; }
    .salon { padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 5px; }
  </style>
</head>
<body>
  <h1>Salon Finder</h1>

  <form id="searchForm">
    <input id="locationInput" name="location" placeholder="Enter address (or use my location)">
    <select id="typeSelect" name="type">
      <option value="">All</option>
      <option value="beauty">Beauty</option>
      <option value="barber">Barber</option>
      <option value="spa">Spa</option>
    </select>
    <input type="number" id="radiusInput" name="radius" value="5" min="1"> km
    <button type="submit">Search</button>
    <button type="button" id="useLocation">Use my location</button>
  </form>

  <div id="map"></div>
  <div id="results"></div>

  <!-- Google Maps API (replace with your API key) -->
 <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_REAL_KEY&callback=initMap" async defer></script>

  <!-- Our JS -->
  <script src="assets/js/map.js"></script>
</body>
</html>
