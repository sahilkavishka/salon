let map;
let markers = [];

function initMap() {
  // Default center (Colombo)
  map = new google.maps.Map(document.getElementById("map"), {
    center: { lat: 6.9271, lng: 79.8612 },
    zoom: 12,
  });
}

// Remove old markers
function clearMarkers() {
  markers.forEach(marker => marker.setMap(null));
  markers = [];
}

// Show results on map
function showSalonsOnMap(salons) {
  clearMarkers();
  let bounds = new google.maps.LatLngBounds();

  salons.forEach(salon => {
    let position = { lat: parseFloat(salon.latitude), lng: parseFloat(salon.longitude) };

    let marker = new google.maps.Marker({
      position: position,
      map: map,
      title: salon.name
    });

    let infoWindow = new google.maps.InfoWindow({
      content: `<strong>${salon.name}</strong><br>${salon.address}<br>Type: ${salon.type}<br>Rating: ${salon.rating}`
    });

    marker.addListener("click", () => {
      infoWindow.open(map, marker);
    });

    markers.push(marker);
    bounds.extend(position);
  });

  if (salons.length > 0) {
    map.fitBounds(bounds);
  }
}

// Handle form submit
document.getElementById("searchForm").addEventListener("submit", function(e) {
  e.preventDefault();
  let location = document.getElementById("locationInput").value;
  let type = document.getElementById("typeSelect").value;
  let radius = document.getElementById("radiusInput").value;

  fetch("search.php?location=" + encodeURIComponent(location) + "&type=" + type + "&radius=" + radius)
    .then(response => response.json())
    .then(data => {
      let resultsDiv = document.getElementById("results");
      resultsDiv.innerHTML = "";

      if (data.error) {
        resultsDiv.innerHTML = "<p style='color:red;'>" + data.error + "</p>";
        return;
      }

      data.forEach(salon => {
        resultsDiv.innerHTML += `
          <div class="salon">
            <strong>${salon.name}</strong><br>
            ${salon.address}<br>
            Type: ${salon.type}<br>
            Rating: ${salon.rating}
          </div>
        `;
      });

      showSalonsOnMap(data);
    })
    .catch(err => console.error("Fetch error:", err));
});

// Use browser location
document.getElementById("useLocation").addEventListener("click", function() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      let lat = pos.coords.latitude;
      let lng = pos.coords.longitude;

      fetch(`search.php?lat=${lat}&lng=${lng}&radius=${document.getElementById("radiusInput").value}&type=${document.getElementById("typeSelect").value}`)
        .then(response => response.json())
        .then(data => {
          let resultsDiv = document.getElementById("results");
          resultsDiv.innerHTML = "";

          if (data.error) {
            resultsDiv.innerHTML = "<p style='color:red;'>" + data.error + "</p>";
            return;
          }

          data.forEach(salon => {
            resultsDiv.innerHTML += `
              <div class="salon">
                <strong>${salon.name}</strong><br>
                ${salon.address}<br>
                Type: ${salon.type}<br>
                Rating: ${salon.rating}
              </div>
            `;
          });

          showSalonsOnMap(data);
        });
    });
  } else {
    alert("Geolocation is not supported by this browser.");
  }
});
