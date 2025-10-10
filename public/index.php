<?php
// public/index.php
require_once __DIR__ . '/../config.php';
session_start();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Salonora - Find Salons</title>
  <style> #map { height: 500px; width: 100%; } </style>
</head>
<body>
  <h1>Find Salons Nearby</h1>
  <input id="searchBox" placeholder="Search by name or address">
  <button id="searchBtn">Search</button>
  <div id="map"></div>

  <script>
    let map, markers = [];
    function initMap() {
      const defaultCenter = { lat: 6.9271, lng: 79.8612 }; // Colombo default
      map = new google.maps.Map(document.getElementById('map'), {
        center: defaultCenter,
        zoom: 13
      });

      // try geolocation
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
          const p = { lat: pos.coords.latitude, lng: pos.coords.longitude };
          map.setCenter(p);
          loadSalonsByLatLng(p.lat, p.lng);
        }, () => { loadSalonsDefault(); });
      } else {
        loadSalonsDefault();
      }
    }

    function clearMarkers(){
      markers.forEach(m => m.setMap(null));
      markers = [];
    }

    function addMarker(salon) {
      const pos = { lat: parseFloat(salon.latitude), lng: parseFloat(salon.longitude) };
      const m = new google.maps.Marker({ position: pos, map });
      const info = new google.maps.InfoWindow({
        content: `<strong>${salon.name}</strong><br>${salon.address}<br><a href="salon_view.php?id=${salon.salon_id}">View</a>`
      });
      m.addListener('click', () => info.open(map, m));
      markers.push(m);
    }

    function loadSalonsByLatLng(lat, lng){
      fetch(`search.php?lat=${lat}&lng=${lng}`)
        .then(r=>r.json()).then(data=>{
          clearMarkers();
          data.forEach(s=>addMarker(s));
        });
    }

    function loadSalonsDefault(){
      fetch('search.php')
        .then(r=>r.json()).then(data=>{
          clearMarkers();
          data.forEach(s=>addMarker(s));
        });
    }

    document.getElementById('searchBtn').addEventListener('click', ()=>{
      const q = document.getElementById('searchBox').value;
      fetch(`search.php?q=${encodeURIComponent(q)}`)
        .then(r=>r.json()).then(data=>{
          clearMarkers();
          data.forEach(s=>addMarker(s));
          if (data[0] && data[0].latitude) {
            map.setCenter({lat: parseFloat(data[0].latitude), lng: parseFloat(data[0].longitude)});
          }
        });
    });
  </script>

  <script src="https://maps.googleapis.com/maps/api/js?key=<?=GOOGLE_MAPS_API_KEY?>&callback=initMap" async defer></script>
</body>
</html>
