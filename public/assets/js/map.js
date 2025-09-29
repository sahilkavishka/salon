// public/assets/js/map.js
markers.forEach(m => m.setMap(null));
markers = [];



async function performSearch(params){
const query = new URLSearchParams(params).toString();
const res = await fetch('search.php?' + query);
const data = await res.json();
return data;
}


document.getElementById('searchForm').addEventListener('submit', async function(e){
e.preventDefault();
const location = document.getElementById('locationInput').value.trim();
const type = document.getElementById('typeSelect').value;
const radius = document.getElementById('radiusInput').value;


let params = { radius };
if (location) params.location = location;
if (type) params.type = type;


const results = await performSearch(params);


clearMarkers();
document.getElementById('results').innerHTML = '';


results.forEach(r => {
const pos = { lat: parseFloat(r.latitude), lng: parseFloat(r.longitude) };
const marker = new google.maps.Marker({ position: pos, map: map, title: r.name });
markers.push(marker);


const row = document.createElement('div');
row.innerText = `${r.name} — ${r.address} (${parseFloat(r.distance).toFixed(2)} km)`;
document.getElementById('results').appendChild(row);
});


if (results[0]) {
map.setCenter({lat: parseFloat(results[0].latitude), lng: parseFloat(results[0].longitude)});
}
});


// Use browser geolocation
document.getElementById('useLocation').addEventListener('click', () => {
if (!navigator.geolocation) return alert('Geolocation not supported');
navigator.geolocation.getCurrentPosition(async pos => {
const lat = pos.coords.latitude;
const lng = pos.coords.longitude;
const radius = document.getElementById('radiusInput').value;
const type = document.getElementById('typeSelect').value;


const results = await performSearch({ lat, lng, radius, type });
// reuse rendering logic: clear and add markers (duplicate or refactor in production)
clearMarkers();
document.getElementById('results').innerHTML = '';
results.forEach(r => {
const marker = new google.maps.Marker({ position: {lat: parseFloat(r.latitude), lng: parseFloat(r.longitude)}, map: map, title: r.name });
markers.push(marker);
const row = document.createElement('div');
row.innerText = `${r.name} — ${r.address} (${parseFloat(r.distance).toFixed(2)} km)`;
document.getElementById('results').appendChild(row);
});
if (results[0]) map.setCenter({lat: parseFloat(results[0].latitude), lng: parseFloat(results[0].longitude)});
}, err => alert('Could not get your location: ' + err.message));
});