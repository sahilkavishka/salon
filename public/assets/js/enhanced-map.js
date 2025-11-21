/**
 * Enhanced Map & Search Integration
 * Advanced salon search with real-time map updates
 */

// Global variables
let map;
let markersLayer;
let markerClusterGroup;
let allSalons = [];
let filteredSalons = [];
let currentSearchResults = [];
let userLocation = null;
const DEBOUNCE_DELAY = 300;
let searchTimeout;
let activeMarkerId = null;

// DOM Elements
const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');
const searchForm = document.getElementById('searchForm');
const clearSearchBtn = document.getElementById('clearSearch');
const locateMeBtn = document.getElementById('locateMe');
const fullscreenBtn = document.getElementById('fullscreen');
const filterHighRated = document.getElementById('filterHighRated');
const filterOpenNow = document.getElementById('filterOpenNow');
const filterNearby = document.getElementById('filterNearby');
const searchStatsDisplay = document.getElementById('searchStatsDisplay');
const resultCount = document.getElementById('resultCount');

// Initialize map
function initMap() {
    // Default center (will be updated with user location)
    map = L.map('map').setView([7.2906, 80.6337], 13);

    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // Initialize marker cluster group
    markerClusterGroup = L.markerClusterGroup({
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true
    });

    map.addLayer(markerClusterGroup);

    // Try to get user location
    getUserLocation();

    // Load all salons
    loadAllSalons();
}

// Get user's current location
function getUserLocation() {
    if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                map.setView([userLocation.lat, userLocation.lng], 13);
                
                // Add user location marker
                L.marker([userLocation.lat, userLocation.lng], {
                    icon: L.divIcon({
                        className: 'user-location-marker',
                        html: '<div style="background: #3b82f6; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></div>',
                        iconSize: [20, 20]
                    })
                }).addTo(map).bindPopup('You are here');
                
                // Reload salons with distance
                if (allSalons.length > 0) {
                    calculateDistances();
                    applyFilters();
                }
            },
            (error) => {
                console.warn('Location access denied:', error);
            }
        );
    }
}

// Load all salons from database
async function loadAllSalons() {
    try {
        const response = await fetch('get_all_salons.php');
        const data = await response.json();
        allSalons = data;
        calculateDistances();
        applyFilters();
    } catch (error) {
        console.error('Failed to load salons:', error);
    }
}

// Calculate distances from user location
function calculateDistances() {
    if (!userLocation) return;
    
    allSalons.forEach(salon => {
        salon.distance = calculateDistance(
            userLocation.lat,
            userLocation.lng,
            salon.lat,
            salon.lng
        );
    });
    
    // Sort by distance
    allSalons.sort((a, b) => (a.distance || 999) - (b.distance || 999));
}

// Calculate distance between two coordinates (Haversine formula)
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

function toRad(degrees) {
    return degrees * (Math.PI / 180);
}

// Format distance for display
function formatDistance(km) {
    if (!km) return '';
    if (km < 1) return `${Math.round(km * 1000)}m away`;
    return `${km.toFixed(1)}km away`;
}

// Apply filters and update map
function applyFilters() {
    filteredSalons = allSalons.filter(salon => {
        // High rated filter
        if (filterHighRated.checked && salon.rating < 4.0) {
            return false;
        }
        
        // Nearby filter (within 10km)
        if (filterNearby.checked && userLocation && salon.distance > 10) {
            return false;
        }
        
        // Open now filter
        if (filterOpenNow.checked && !isOpenNow(salon.opening_hours)) {
            return false;
        }
        
        return true;
    });
    
    updateMapMarkers(filteredSalons);
}

// Check if salon is open now
function isOpenNow(openingHours) {
    if (!openingHours) return true; // Assume open if no hours specified
    
    const now = new Date();
    const currentDay = now.getDay();
    const currentTime = now.getHours() * 60 + now.getMinutes();
    
    // Parse opening hours (simple format: "Mon-Fri: 9:00-18:00")
    // This is a simplified check - implement based on your data format
    return true;
}

// Update map markers
function updateMapMarkers(salons) {
    markerClusterGroup.clearLayers();
    
    // Initialize markers object if not exists
    if (!window.salonMarkers) {
        window.salonMarkers = {};
    }
    
    // Clear existing markers
    window.salonMarkers = {};
    
    salons.forEach(salon => {
        const marker = createMarker(salon);
        markerClusterGroup.addLayer(marker);
        
        // Store marker reference by salon ID
        window.salonMarkers[salon.id] = marker;
    });
    
    console.log(`Updated map with ${salons.length} markers`);
}

// Create custom marker based on rating
function createMarker(salon) {
    const color = getMarkerColor(salon.rating);
    
    const icon = L.divIcon({
        className: 'custom-marker',
        html: `
            <div style="
                background: ${color};
                width: 32px;
                height: 32px;
                border-radius: 50% 50% 50% 0;
                border: 3px solid white;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                transform: rotate(-45deg);
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
            ">
                <i class="fas fa-cut" style="
                    color: white;
                    font-size: 12px;
                    transform: rotate(45deg);
                "></i>
            </div>
        `,
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32]
    });
    
    const marker = L.marker([salon.lat, salon.lng], { icon });
    
    // Create custom popup
    const popupContent = createPopupContent(salon);
    marker.bindPopup(popupContent, {
        className: 'custom-popup',
        maxWidth: 300
    });
    
    // Highlight marker on hover
    marker.on('mouseover', function() {
        this.getElement().style.transform = 'scale(1.2)';
        this.getElement().style.zIndex = 1000;
    });
    
    marker.on('mouseout', function() {
        this.getElement().style.transform = 'scale(1)';
        this.getElement().style.zIndex = 'auto';
    });
    
    return marker;
}

// Get marker color based on rating
function getMarkerColor(rating) {
    if (!rating) return '#6b7280';
    if (rating >= 4.5) return '#10b981';
    if (rating >= 4.0) return '#3b82f6';
    if (rating >= 3.0) return '#f59e0b';
    return '#ef4444';
}

// Create popup content
function createPopupContent(salon) {
    const imageUrl = salon.image_url || 'assets/images/default-salon.jpg';
    const rating = salon.rating ? salon.rating.toFixed(1) : 'N/A';
    const distance = salon.distance ? formatDistance(salon.distance) : '';
    const salonId = parseInt(salon.id);
    
    // Correct path to your salon details page
    const detailPageUrl = `user/salon_details.php?id=${salonId}`;
    
    return `
        <div class="popup-content">
            <div class="popup-header">
                <img src="${imageUrl}" alt="${escapeHtml(salon.name)}" class="popup-image" onerror="this.src='assets/images/default-salon.jpg'">
                <div>
                    <div class="popup-title">${escapeHtml(salon.name)}</div>
                    <div class="popup-rating">
                        <i class="fas fa-star"></i>
                        ${rating} (${salon.review_count || 0} reviews)
                    </div>
                </div>
            </div>
            <div class="popup-details">
                <div style="margin-bottom: 8px;">
                    <i class="fas fa-map-marker-alt" style="color: #6366f1; width: 16px;"></i>
                    ${escapeHtml(salon.address)}
                </div>
                ${distance ? `
                    <div style="margin-bottom: 8px; color: #059669; font-weight: 600;">
                        <i class="fas fa-route" style="width: 16px;"></i>
                        ${distance}
                    </div>
                ` : ''}
                ${salon.phone ? `
                    <div style="margin-bottom: 8px;">
                        <i class="fas fa-phone" style="color: #6366f1; width: 16px;"></i>
                        ${escapeHtml(salon.phone)}
                    </div>
                ` : ''}
                ${salon.email ? `
                    <div>
                        <i class="fas fa-envelope" style="color: #6366f1; width: 16px;"></i>
                        ${escapeHtml(salon.email)}
                    </div>
                ` : ''}
            </div>
            <div class="popup-actions">
                <a href="${detailPageUrl}" class="popup-btn popup-btn-primary">
                    <i class="fas fa-info-circle me-1"></i>
                    View Details
                </a>
                ${salon.phone ? `
                    <a href="tel:${escapeHtml(salon.phone)}" class="popup-btn popup-btn-secondary" title="Call ${escapeHtml(salon.name)}">
                        <i class="fas fa-phone"></i>
                    </a>
                ` : ''}
            </div>
        </div>
    `;
}

// Search functionality
function debounceSearch(query) {
    clearTimeout(searchTimeout);
    
    if (query.length < 2) {
        searchResults.classList.remove('show');
        clearSearchBtn.style.display = 'none';
        searchStatsDisplay.style.display = 'none';
        return;
    }
    
    searchResults.innerHTML = '<div class="no-results"><div class="loading-spinner"></div></div>';
    searchResults.classList.add('show');
    clearSearchBtn.style.display = 'block';
    
    searchTimeout = setTimeout(() => {
        performSearch(query);
    }, DEBOUNCE_DELAY);
}

// Perform search via API
async function performSearch(query) {
    try {
        console.log('Searching for:', query);
        const response = await fetch(`search_api.php?query=${encodeURIComponent(query)}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const results = await response.json();
        console.log('Search results:', results);
        
        if (results.error) {
            searchResults.innerHTML = `<div class="no-results">${results.error}</div>`;
            return;
        }
        
        if (!Array.isArray(results)) {
            console.error('Invalid response format:', results);
            searchResults.innerHTML = '<div class="no-results">Invalid response from server</div>';
            return;
        }
        
        currentSearchResults = results;
        displaySearchResults(results);
        updateMapWithSearchResults(results);
        
        // Update stats
        searchStatsDisplay.style.display = 'block';
        resultCount.textContent = results.length;
        
    } catch (error) {
        console.error('Search error:', error);
        searchResults.innerHTML = '<div class="no-results">Search failed. Please try again.</div>';
    }
}

// Display search results in dropdown
function displaySearchResults(results) {
    if (results.length === 0) {
        searchResults.innerHTML = `
            <div class="no-results">
                <i class="fas fa-search"></i>
                <div>No salons found</div>
                <small style="color: #a0aec0; margin-top: 8px;">Try different keywords</small>
            </div>
        `;
        return;
    }
    
    // Calculate distances if user location is available
    if (userLocation) {
        results.forEach(salon => {
            if (!salon.distance) {
                salon.distance = calculateDistance(
                    userLocation.lat,
                    userLocation.lng,
                    salon.lat,
                    salon.lng
                );
            }
        });
    }
    
    const html = results.map(salon => {
        const distance = salon.distance ? formatDistance(salon.distance) : '';
        const rating = salon.rating ? salon.rating.toFixed(1) : 'N/A';
        const services = salon.services || [];
        
        return `
            <div class="search-result-item" data-salon-id="${salon.id}" data-lat="${salon.lat}" data-lng="${salon.lng}">
                <div class="search-result-icon">
                    <i class="fas fa-cut"></i>
                </div>
                <div class="search-result-content">
                    <div class="search-result-name">
                        ${escapeHtml(salon.name)}
                        ${salon.rating ? `
                            <span class="search-result-rating">
                                <i class="fas fa-star"></i>
                                ${rating}
                            </span>
                        ` : ''}
                    </div>
                    <div class="search-result-address">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        ${escapeHtml(salon.address)}
                    </div>
                    ${distance ? `
                        <div class="search-result-distance">
                            <i class="fas fa-route"></i>
                            ${distance}
                        </div>
                    ` : ''}
                    ${services.length > 0 ? `
                        <div class="search-result-services">
                            ${services.slice(0, 3).map(s => `
                                <span class="service-badge">${escapeHtml(s)}</span>
                            `).join('')}
                            ${services.length > 3 ? `<span class="service-badge">+${services.length - 3} more</span>` : ''}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
    
    searchResults.innerHTML = html;
    
    // Add click handlers
    document.querySelectorAll('.search-result-item').forEach(item => {
        item.addEventListener('click', function() {
            handleSearchResultClick(this);
        });
    });
    
    console.log(`Displayed ${results.length} search results`);
}

// Handle search result click
function handleSearchResultClick(element) {
    const lat = parseFloat(element.dataset.lat);
    const lng = parseFloat(element.dataset.lng);
    const salonId = parseInt(element.dataset.salonId);
    
    console.log('Clicked salon:', salonId, 'at', lat, lng);
    console.log('Available markers:', Object.keys(window.salonMarkers));
    
    // Remove active class from all items
    document.querySelectorAll('.search-result-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to clicked item
    element.classList.add('active');
    activeMarkerId = salonId;
    
    // Update search input
    const salonName = element.querySelector('.search-result-name').textContent.trim().split('\n')[0];
    searchInput.value = salonName;
    
    // Close dropdown
    searchResults.classList.remove('show');
    
    // Pan and zoom to salon location
    map.setView([lat, lng], 17, {
        animate: true,
        duration: 1.5
    });
    
    // Find and open marker popup with delay
    setTimeout(() => {
        if (window.salonMarkers && window.salonMarkers[salonId]) {
            console.log('Opening popup for salon:', salonId);
            window.salonMarkers[salonId].openPopup();
            
            // Highlight the marker
            const markerElement = window.salonMarkers[salonId].getElement();
            if (markerElement) {
                markerElement.style.transform = 'scale(1.3)';
                markerElement.style.zIndex = '10000';
                setTimeout(() => {
                    markerElement.style.transform = 'scale(1)';
                }, 2000);
            }
        } else {
            console.warn('Marker not found for salon:', salonId);
        }
    }, 800);
    
    // Scroll to map
    setTimeout(() => {
        document.getElementById('map').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
    }, 200);
}

// Update map with search results
function updateMapWithSearchResults(results) {
    if (results.length === 0) return;
    
    console.log('Updating map with search results:', results.length);
    
    // Calculate distances for search results
    if (userLocation) {
        results.forEach(salon => {
            if (!salon.distance) {
                salon.distance = calculateDistance(
                    userLocation.lat,
                    userLocation.lng,
                    salon.lat,
                    salon.lng
                );
            }
        });
    }
    
    // Clear existing markers and update with search results
    markerClusterGroup.clearLayers();
    window.salonMarkers = {};
    
    // Add markers for search results
    results.forEach(salon => {
        const marker = createMarker(salon);
        markerClusterGroup.addLayer(marker);
        window.salonMarkers[salon.id] = marker;
    });
    
    console.log('Added markers to map:', Object.keys(window.salonMarkers).length);
    
    // Fit bounds to show all results
    if (results.length > 0) {
        const bounds = L.latLngBounds(results.map(s => [s.lat, s.lng]));
        
        // Use setTimeout to ensure markers are rendered
        setTimeout(() => {
            map.fitBounds(bounds, { 
                padding: [50, 50], 
                maxZoom: 15,
                animate: true 
            });
        }, 100);
    }
}

// Clear search
function clearSearch() {
    searchInput.value = '';
    searchResults.classList.remove('show');
    clearSearchBtn.style.display = 'none';
    searchStatsDisplay.style.display = 'none';
    currentSearchResults = [];
    activeMarkerId = null;
    
    // Reset to all salons
    applyFilters();
    
    // Reset map view
    if (userLocation) {
        map.setView([userLocation.lat, userLocation.lng], 13);
    }
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event Listeners
searchInput.addEventListener('input', (e) => {
    debounceSearch(e.target.value.trim());
});

searchForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const query = searchInput.value.trim();
    if (query) {
        performSearch(query);
    }
});

clearSearchBtn.addEventListener('click', clearSearch);

locateMeBtn.addEventListener('click', () => {
    if (userLocation) {
        map.setView([userLocation.lat, userLocation.lng], 14, {
            animate: true
        });
    } else {
        getUserLocation();
    }
});

fullscreenBtn.addEventListener('click', () => {
    const mapElement = document.getElementById('map');
    if (!document.fullscreenElement) {
        mapElement.requestFullscreen();
        fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
    } else {
        document.exitFullscreen();
        fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
    }
});

// Filter change handlers
[filterHighRated, filterOpenNow, filterNearby].forEach(filter => {
    filter.addEventListener('change', () => {
        if (currentSearchResults.length > 0) {
            // If search is active, reapply search
            performSearch(searchInput.value.trim());
        } else {
            // Otherwise apply filters to all salons
            applyFilters();
        }
    });
});

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (!searchForm.contains(e.target)) {
        searchResults.classList.remove('show');
    }
});

// Close dropdown on escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        searchResults.classList.remove('show');
    }
});

// Initialize map when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMap);
} else {
    initMap();
}

// Export for use in map.js if needed
window.map = map;