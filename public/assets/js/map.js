// Initialize Map
let map = L.map('map').setView([7.2906, 80.6337], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let salonMarkers = [];

// Clear Markers
function clearMarkers() {
    salonMarkers.forEach(m => map.removeLayer(m));
    salonMarkers = [];
}

// Add Marker
function addMarker(lat, lng, name, address) {
    let marker = L.marker([lat, lng]).addTo(map)
        .bindPopup(`<b>${name}</b><br>${address}`);
    salonMarkers.push(marker);
}

// Handle Search
document.getElementById("searchForm").addEventListener("submit", function(e) {
    e.preventDefault();

    let query = document.getElementById("searchInput").value.trim();

    if (query === "") return;

    fetch(`search_api.php?query=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            clearMarkers();
            const list = document.getElementById("salonList");
            list.innerHTML = "";

            if (data.length === 0) {
                list.innerHTML = `<li class="list-group-item text-danger">No salons found.</li>`;
                return;
            }

            // Add markers + list items
            data.forEach(salon => {
                addMarker(salon.lat, salon.lng, salon.name, salon.address);

                list.innerHTML += `
                    <li class="list-group-item">
                        <b>${salon.name}</b><br>
                        <small>${salon.address}</small>
                    </li>`;
            });

            // Center map on first result
            map.setView([data[0].lat, data[0].lng], 14);
        })
        .catch(err => console.log(err));
});
