// assets/js/map.js

// Initialize Map
var map = L.map('map', { zoomControl: false }).setView([6.9271, 79.8612], 13);
L.control.zoom({ position: 'topright' }).addTo(map);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Custom icon
var customIcon = L.divIcon({
    className: 'custom-marker',
    html: '<i class="fas fa-map-marker-alt fa-2x text-danger"></i>',
    iconSize: [30, 42],
    iconAnchor: [15, 42],
    popupAnchor: [0, -40]
});

let markers = [];

function clearMarkers() {
    markers.forEach(m => map.removeLayer(m));
    markers = [];
}

// Search Form - show markers only
document.getElementById("searchForm").addEventListener("submit", function(e) {
    e.preventDefault();
    let query = document.getElementById("searchInput").value.trim();
    if (!query) return;

    fetch(`search_api.php?query=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            clearMarkers();

            if (data.length === 0) {
                alert("No salons found");
                return;
            }

            let bounds = [];

            data.forEach(salon => {
                // Make sure lat and lng exist and are numbers
                const lat = parseFloat(salon.lat);
                const lng = parseFloat(salon.lng);
                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
                    markers.push(marker);
                    bounds.push([lat, lng]);
                }
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        })
        .catch(err => console.error(err));
});

// Locate Me
document.getElementById('locateMe').addEventListener('click', function() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            map.setView([pos.coords.latitude, pos.coords.longitude], 15);
            L.marker([pos.coords.latitude, pos.coords.longitude]).addTo(map)
                .bindPopup('You are here!').openPopup();
        });
    }
});

// Fullscreen
document.getElementById('fullscreen').addEventListener('click', function() {
    const mapContainer = document.querySelector('.map-container');
    if (!document.fullscreenElement) {
        mapContainer.requestFullscreen();
        this.innerHTML = '<i class="fas fa-compress"></i>';
    } else {
        document.exitFullscreen();
        this.innerHTML = '<i class="fas fa-expand"></i>';
    }
});
