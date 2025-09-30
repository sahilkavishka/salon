let map;
let markers = [];

function initMap() {
  map = new google.maps.Map(document.getElementById("map"), {
    center: { lat: 6.9271, lng: 79.8612 }, // Default Colombo
    zoom: 12,
  });
}

function clearMarkers() {
  markers.forEach(marker => marker.setMap(null));
  markers = [];
}

function showResultsOnMap(salons) {
  clearMarkers();
  let bounds = new google.maps.LatLngBounds();
  let resultsDiv = document.getElementById("results");
  resultsDiv.innerHTML = "";

  salons.forEach(salon => {
    let position = { lat: parseFloat(salon.latitude), lng: parseFloat(salon.longitude) };

    let marker = new google.maps.Marker({
      position,
      map,
      title: salon.name
    });

    let infoWindow = new google.maps.InfoWindow({
      content: `<strong>${salon.name}</strong><br>${salon.address}<br>Type: ${salon.type}<br>Rating: ${salon.rating}`
    });

    marker.addListener("click", () => infoWindow.open(map, marker));
    markers.push(marker);
    bounds.extend(position);

    // Add to results list
    resultsDiv.innerHTML += `
      <div class="salon">
        <strong>${salon.name}</strong><br>
        ${salon.address}<br>
        Type: ${salon.type}<br>
        Rating: ${salon.rating}
      </div>
    `;
  });

  if (salons.length > 0) {
    map.fitBounds(bounds);
  } else {
    resultsDiv.innerHTML = "<p>No salons found in this area.</p>";
  }
}

// Handle search form
document.getElementById("searchForm").addEventListener("submit", function(e) {
  e.preventDefault();
  let location = document.getElementById("locationInput").value;
  let type = document.getElementById("typeSelect").value;
  let radius = document.getElementById("radiusInput").value;

  fetch(`search.php?location=${encodeURIComponent(location)}&type=${type}&radius=${radius}`)
    .then(response => response.json())
    .then(data => {
      if (data.error) {
        alert(data.error);
      } else {
        showResultsOnMap(data);
      }
    });
});

// Use My Location
document.getElementById("useLocation").addEventListener("click", () => {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      let lat = pos.coords.latitude;
      let lng = pos.coords.longitude;
      let type = document.getElementById("typeSelect").value;
      let radius = document.getElementById("radiusInput").value;

      fetch(`search.php?lat=${lat}&lng=${lng}&type=${type}&radius=${radius}`)
        .then(response => response.json())
        .then(data => {
          if (data.error) {
            alert(data.error);
          } else {
            showResultsOnMap(data);
          }
        });
    });
  } else {
    alert("Geolocation not supported by your browser.");
  }
});
