<!-- public/index.php -->
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Salon Finder</title>
<link rel="stylesheet" href="assets/css/style.css">
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


<div id="map" style="height:450px;"></div>
<div id="results"></div>


<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_API_KEY&callback=initMap" async defer></script>
<script src="assets/js/map.js"></script>
</body>
</html>