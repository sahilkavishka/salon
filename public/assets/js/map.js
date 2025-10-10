// Initialize map
let map = L.map('map').setView([7.2906, 80.6337], 13); // Default: Anuradhapura

// Add OpenStreetMap tiles
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
}).addTo(map);

// Array to hold salon markers
let salonMarkers = [];

// Default marker
let defaultMarker = L.marker([7.2906, 80.6337]).addTo(map)
    .bindPopup("Welcome to Salon Finder! Use the search form to find salons.")
    .openPopup();

// Function to add salon markers
function addSalonMarker(lat, lng, name, type, distance) {
    let marker = L.marker([lat, lng]).addTo(map)
        .bindPopup(`<b>${name}</b><br>Type: ${type}<br>Distance: ${distance} km`);
    salonMarkers.push(marker);
}

// Function to clear all salon markers
function clearSalonMarkers() {
    salonMarkers.forEach(marker => map.removeLayer(marker));
    salonMarkers = [];
}

// Handle search form submit
document.getElementById('searchForm').addEventListener('submit', function(e) {
    e.preventDefault();

    let location = document.getElementById('locationInput').value;
    let type = document.getElementById('typeSelect').value;
    let radius = document.getElementById('radiusInput').value;

    // Clear previous markers
    clearSalonMarkers();

    // Fetch salons from PHP
    fetch(`search_salon.php?location=${encodeURIComponent(location)}&type=${encodeURIComponent(type)}&radius=${radius}`)
    .then(response => response.json())
    .then(data => {
        if(data.length === 0){
            alert("No salons found in this area!");
            return;
        }

        // Add markers
        data.forEach(salon => {
            addSalonMarker(salon.lat, salon.lng, salon.name, salon.type, salon.distance);
        });

        // Center map to first salon
        map.setView([data[0].lat, data[0].lng], 14);
    })
    .catch(err => console.error(err));
});

// Handle "Use my location" button
document.getElementById('useLocation').addEventListener('click', function() {
    if(navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position){
            let lat = position.coords.latitude;
            let lng = position.coords.longitude;
            map.setView([lat, lng], 14);

            L.marker([lat, lng]).addTo(map)
                .bindPopup("You are here!").openPopup();
        });
    } else {
        alert("Geolocation is not supported by your browser.");
    }
});
