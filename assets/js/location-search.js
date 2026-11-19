/**
 * KaamConnect - Intelligent Location Search & Interactive Map Picker
 * Features:
 *  1. Debounced Autocomplete with Nominatim OpenStreetMap Geocoding
 *  2. Keyboard Navigation (Arrow keys, Enter, Escape)
 *  3. Leaflet.js Interactive Map with Draggable Marker & Reverse Geocoding
 *  4. In-Map Location Search and Coordinate State Preservation
 */

(() => {
    'use strict';

    // State object for location search
    const locState = {
        lat: null,
        lon: null,
        city: '',
        locality: '',
        district: '',
        state: '',
        displayName: '',
        activeIndex: -1,
        suggestions: [],
        debounceTimer: null,
        map: null,
        marker: null,
        modalSelected: null,
        apiUrl: '/api/geocode.php'
    };

    document.addEventListener('DOMContentLoaded', () => {
        initLocationSearch();
    });

    function initLocationSearch() {
        const locationInput = document.getElementById('kcLocationInput');
        const dropdown = document.getElementById('kcLocationDropdown');
        const searchForm = document.getElementById('kcWorkerSearchForm');

        if (!locationInput || !dropdown) return;

        // Resolve API URL dynamically from form or path
        if (searchForm && searchForm.dataset.apiUrl) {
            locState.apiUrl = searchForm.dataset.apiUrl;
        } else {
            const path = window.location.pathname;
            const match = path.match(/^\/([^\/]+)/);
            if (match && match[1].toLowerCase() === 'kaamconnect') {
                locState.apiUrl = '/KAAMCONNECT/api/geocode.php';
            } else {
                locState.apiUrl = '/api/geocode.php';
            }
        }

        // Initialize state from existing hidden inputs if present (e.g. on reload or form back)
        const latInput = document.getElementById('kcLatInput');
        const lngInput = document.getElementById('kcLngInput');
        if (latInput && latInput.value && lngInput && lngInput.value) {
            locState.lat = parseFloat(latInput.value);
            locState.lon = parseFloat(lngInput.value);
            renderGeoBadge(locationInput.value || `${locState.lat.toFixed(3)}, ${locState.lon.toFixed(3)}`);
        }

        // ── 1. Autocomplete Event Listeners ──────────────────────────────
        locationInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(locState.debounceTimer);

            // If user types and changes text, clear exact coordinates unless they re-select
            if (locState.lat !== null) {
                clearGeoState(false);
            }

            if (query.length < 2) {
                hideDropdown();
                return;
            }

            showLoading();

            locState.debounceTimer = setTimeout(() => {
                fetchSuggestions(query);
            }, 450); // 450ms debounce
        });

        // Keyboard Navigation (ArrowDown, ArrowUp, Enter, Escape)
        locationInput.addEventListener('keydown', (e) => {
            const items = dropdown.querySelectorAll('.kc-autocomplete-item');
            if (!dropdown.classList.contains('show') || items.length === 0) {
                if (e.key === 'Escape') hideDropdown();
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                locState.activeIndex = (locState.activeIndex + 1) % items.length;
                updateActiveItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                locState.activeIndex = (locState.activeIndex - 1 + items.length) % items.length;
                updateActiveItem(items);
            } else if (e.key === 'Enter') {
                if (locState.activeIndex >= 0 && items[locState.activeIndex]) {
                    e.preventDefault();
                    selectSuggestion(locState.suggestions[locState.activeIndex]);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                hideDropdown();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!locationInput.contains(e.target) && !dropdown.contains(e.target)) {
                hideDropdown();
            }
        });

        // ── 2. Interactive Map Picker Modal ──────────────────────────────
        initMapPicker();

        // ── 3. Registration Page City Autocomplete Integration ────────────
        initRegistrationLocationAutocomplete();
    }

    function initRegistrationLocationAutocomplete() {
        const regCityInput = document.getElementById('city');
        const regDropdown = document.getElementById('regCityDropdown');
        const regLocalityInput = document.getElementById('locality');
        const regDistrictInput = document.getElementById('regDistrict');
        const regStateInput = document.getElementById('regState');
        const regLatInput = document.getElementById('regLat');
        const regLngInput = document.getElementById('regLng');

        if (!regCityInput || !regDropdown) return;

        let regDebounceTimer = null;
        let regSuggestions = [];
        let regActiveIndex = -1;

        regCityInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(regDebounceTimer);

            if (query.length < 2) {
                regDropdown.classList.remove('show');
                return;
            }

            regDropdown.innerHTML = `
                <div class="kc-autocomplete-loading">
                    <i class="fas fa-spinner fa-spin text-primary me-2"></i>Searching cities in India...
                </div>
            `;
            regDropdown.classList.add('show');

            regDebounceTimer = setTimeout(async () => {
                try {
                    const url = `${locState.apiUrl}?action=search&q=${encodeURIComponent(query)}`;
                    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    regSuggestions = data.results || [];
                    regActiveIndex = -1;
                    regDropdown.innerHTML = '';

                    if (regSuggestions.length === 0) {
                        regDropdown.innerHTML = '<div class="kc-autocomplete-empty"><i class="fas fa-info-circle me-1"></i>No locations found in India</div>';
                        return;
                    }

                    regSuggestions.forEach((item, idx) => {
                        const div = document.createElement('div');
                        div.className = 'kc-autocomplete-item';
                        const main = item.city || item.suburb || item.display_name.split(',')[0];
                        const sub = [item.district, item.state].filter(Boolean).join(', ');

                        div.innerHTML = `
                            <i class="fas fa-map-marker-alt kc-loc-icon"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div class="kc-loc-main text-truncate">${escapeHtml(main)}</div>
                                ${sub ? `<div class="kc-loc-sub text-truncate">${escapeHtml(sub)}</div>` : ''}
                            </div>
                        `;

                        div.addEventListener('click', () => {
                            regCityInput.value = item.city || main;
                            if (regLocalityInput && !regLocalityInput.value) {
                                regLocalityInput.value = item.suburb || main;
                            }
                            if (regDistrictInput) regDistrictInput.value = item.district || '';
                            if (regStateInput) regStateInput.value = item.state || '';
                            if (regLatInput) regLatInput.value = item.lat || '';
                            if (regLngInput) regLngInput.value = item.lon || '';
                            regDropdown.classList.remove('show');
                        });

                        regDropdown.appendChild(div);
                    });
                } catch (err) {
                    regDropdown.innerHTML = '<div class="kc-autocomplete-empty">Unable to fetch suggestions</div>';
                }
            }, 450);
        });

        // Close when clicking outside
        document.addEventListener('click', (e) => {
            if (!regCityInput.contains(e.target) && !regDropdown.contains(e.target)) {
                regDropdown.classList.remove('show');
            }
        });
    }

    // ── Fetch Autocomplete Suggestions ───────────────────────────────────
    async function fetchSuggestions(query) {
        const dropdown = document.getElementById('kcLocationDropdown');
        try {
            const url = `${locState.apiUrl}?action=search&q=${encodeURIComponent(query)}`;
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) {
                throw new Error(`HTTP error ${res.status}`);
            }

            const data = await res.json();
            locState.suggestions = data.results || [];
            locState.activeIndex = -1;

            renderSuggestions(locState.suggestions);
        } catch (err) {
            console.warn('Geocoding autocomplete request failed:', err);
            renderEmpty('Location suggestions temporarily unavailable. You can still type manually.');
        }
    }

    function renderSuggestions(results) {
        const dropdown = document.getElementById('kcLocationDropdown');
        dropdown.innerHTML = '';

        if (!results || results.length === 0) {
            renderEmpty('No matching locations found in India. Try another area.');
            return;
        }

        results.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'kc-autocomplete-item';
            div.dataset.index = index;

            // Formulate clean primary and secondary text
            const mainText = item.suburb || item.city || item.display_name.split(',')[0];
            const secondaryParts = [item.city, item.district, item.state].filter(p => p && p !== mainText);
            const subText = Array.from(new Set(secondaryParts)).join(', ');

            div.innerHTML = `
                <i class="fas fa-map-marker-alt kc-loc-icon"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="kc-loc-main text-truncate">${escapeHtml(mainText)}</div>
                    ${subText ? `<div class="kc-loc-sub text-truncate">${escapeHtml(subText)}</div>` : ''}
                </div>
            `;

            div.addEventListener('click', () => {
                selectSuggestion(item);
            });

            dropdown.appendChild(div);
        });

        dropdown.classList.add('show');
    }

    function updateActiveItem(items) {
        items.forEach((item, idx) => {
            if (idx === locState.activeIndex) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('active');
            }
        });
    }

    function showLoading() {
        const dropdown = document.getElementById('kcLocationDropdown');
        dropdown.innerHTML = `
            <div class="kc-autocomplete-loading">
                <i class="fas fa-spinner fa-spin text-primary me-2"></i>Searching locations...
            </div>
        `;
        dropdown.classList.add('show');
    }

    function renderEmpty(message) {
        const dropdown = document.getElementById('kcLocationDropdown');
        dropdown.innerHTML = `
            <div class="kc-autocomplete-empty">
                <i class="fas fa-info-circle me-1"></i>${escapeHtml(message)}
            </div>
        `;
        dropdown.classList.add('show');
    }

    function hideDropdown() {
        const dropdown = document.getElementById('kcLocationDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
            locState.activeIndex = -1;
        }
    }

    function selectSuggestion(item) {
        const locationInput = document.getElementById('kcLocationInput');
        if (!locationInput || !item) return;

        // Choose best display string: "Locality, City" or "City, District"
        const primary = item.suburb || item.city || item.display_name.split(',')[0];
        const secondary = item.city && item.city !== primary ? item.city : (item.district || item.state || '');
        const chosenText = secondary ? `${primary}, ${secondary}` : primary;

        locationInput.value = chosenText;

        // Set hidden state
        locState.lat = item.lat;
        locState.lon = item.lon;
        locState.city = item.city || primary;
        locState.locality = item.suburb || primary;
        locState.district = item.district || '';
        locState.state = item.state || '';
        locState.displayName = item.display_name;

        updateHiddenInputs();
        renderGeoBadge(chosenText);
        hideDropdown();
    }

    function updateHiddenInputs() {
        const latInput = document.getElementById('kcLatInput');
        const lngInput = document.getElementById('kcLngInput');
        const distInput = document.getElementById('kcDistrictInput');
        const stateInput = document.getElementById('kcStateInput');

        if (latInput) latInput.value = locState.lat !== null ? locState.lat : '';
        if (lngInput) lngInput.value = locState.lon !== null ? locState.lon : '';
        if (distInput) distInput.value = locState.district || '';
        if (stateInput) stateInput.value = locState.state || '';
    }

    function clearGeoState(clearInput = true) {
        locState.lat = null;
        locState.lon = null;
        locState.district = '';
        locState.state = '';
        updateHiddenInputs();

        const badge = document.getElementById('kcGeoBadge');
        if (badge) badge.remove();

        if (clearInput) {
            const locationInput = document.getElementById('kcLocationInput');
            if (locationInput) locationInput.value = '';
        }
    }

    function renderGeoBadge(label) {
        const wrapper = document.querySelector('.kc-location-wrapper');
        if (!wrapper) return;

        let badge = document.getElementById('kcGeoBadge');
        if (!badge) {
            badge = document.createElement('div');
            badge.id = 'kcGeoBadge';
            badge.className = 'kc-geo-badge';
            wrapper.appendChild(badge);
        }

        badge.innerHTML = `
            <i class="fas fa-check-circle text-success"></i>
            <span>GPS Pin Active (${locState.lat.toFixed(2)}, ${locState.lon.toFixed(2)})</span>
            <i class="fas fa-times-circle kc-geo-clear ms-1" title="Clear coordinate pin"></i>
        `;

        badge.querySelector('.kc-geo-clear').addEventListener('click', () => {
            clearGeoState(false);
        });
    }

    // ── 3. Interactive Map Picker Logic ──────────────────────────────────
    function initMapPicker() {
        const modalEl = document.getElementById('kcMapModal');
        const btnChoose = document.getElementById('kcBtnChooseOnMap');
        const mapContainer = document.getElementById('kcMapContainer');
        const btnConfirm = document.getElementById('kcBtnConfirmLocation');
        const mapSearchInput = document.getElementById('kcMapSearchInput');
        const btnMapSearch = document.getElementById('kcBtnMapSearch');

        if (!modalEl || !btnChoose || !mapContainer) return;

        // Leaflet default icon fix for bundler/CDN path issues
        if (typeof L !== 'undefined' && L.Icon && L.Icon.Default) {
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            });
        }

        // When modal opens
        modalEl.addEventListener('shown.bs.modal', () => {
            // Determine starting coordinates: existing selected or default to Delhi NCR [28.6139, 77.2090]
            const startLat = locState.lat || 28.5706;
            const startLon = locState.lon || 77.3272;
            const zoomLevel = locState.lat ? 14 : 11;

            if (!locState.map) {
                locState.map = L.map(mapContainer).setView([startLat, startLon], zoomLevel);

                // Add OpenStreetMap Tile Layer
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>'
                }).addTo(locState.map);

                // Add draggable marker
                locState.marker = L.marker([startLat, startLon], { draggable: true }).addTo(locState.map);

                // Marker drag event
                locState.marker.on('dragend', () => {
                    const pos = locState.marker.getLatLng();
                    reverseGeocode(pos.lat, pos.lng);
                });

                // Map click event
                locState.map.on('click', (e) => {
                    locState.marker.setLatLng(e.latlng);
                    reverseGeocode(e.latlng.lat, e.latlng.lng);
                });
            } else {
                locState.map.invalidateSize();
                locState.map.setView([startLat, startLon], zoomLevel);
                locState.marker.setLatLng([startLat, startLon]);
            }

            // Trigger initial reverse geocode for preview
            reverseGeocode(startLat, startLon);
        });

        // Search bar inside map modal
        if (btnMapSearch && mapSearchInput) {
            const doMapSearch = async () => {
                const query = mapSearchInput.value.trim();
                if (!query) return;

                btnMapSearch.disabled = true;
                btnMapSearch.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                try {
                    const url = `${locState.apiUrl}?action=search&q=${encodeURIComponent(query)}`;
                    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();

                    if (data.results && data.results.length > 0) {
                        const first = data.results[0];
                        locState.map.setView([first.lat, first.lon], 14);
                        locState.marker.setLatLng([first.lat, first.lon]);
                        reverseGeocode(first.lat, first.lon);
                    } else {
                        alert('Location not found on map. Try another search term.');
                    }
                } catch (e) {
                    console.error('Map search failed:', e);
                } finally {
                    btnMapSearch.disabled = false;
                    btnMapSearch.innerHTML = '<i class="fas fa-search"></i>';
                }
            };

            btnMapSearch.addEventListener('click', doMapSearch);
            mapSearchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    doMapSearch();
                }
            });
        }

        // Confirm Location Button
        if (btnConfirm) {
            btnConfirm.addEventListener('click', () => {
                if (!locState.modalSelected) return;

                const selected = locState.modalSelected;
                const locationInput = document.getElementById('kcLocationInput');
                const regCity = document.getElementById('city');
                const regLocality = document.getElementById('locality');
                const regDistrict = document.getElementById('regDistrict');
                const regState = document.getElementById('regState');
                const regLat = document.getElementById('regLat');
                const regLng = document.getElementById('regLng');

                const chosenText = selected.locality_label || selected.city || selected.display_name.split(',')[0];
                if (locationInput) {
                    locationInput.value = chosenText;
                }
                if (regCity) {
                    regCity.value = selected.city || chosenText;
                }
                if (regLocality) {
                    regLocality.value = selected.suburb || selected.locality_label || chosenText;
                }
                if (regDistrict) regDistrict.value = selected.district || '';
                if (regState) regState.value = selected.state || '';
                if (regLat) regLat.value = selected.lat || '';
                if (regLng) regLng.value = selected.lon || '';

                locState.lat = selected.lat;
                locState.lon = selected.lon;
                locState.city = selected.city || chosenText;
                locState.locality = selected.suburb || chosenText;
                locState.district = selected.district || '';
                locState.state = selected.state || '';
                locState.displayName = selected.display_name;

                updateHiddenInputs();
                renderGeoBadge(chosenText);

                // Close bootstrap modal
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();
            });
        }
    }

    // Reverse geocode lat/lng to display address in modal
    async function reverseGeocode(lat, lon) {
        const previewEl = document.getElementById('kcMapAddressPreviewText');
        const btnConfirm = document.getElementById('kcBtnConfirmLocation');

        if (previewEl) {
            previewEl.innerHTML = '<i class="fas fa-spinner fa-spin text-primary me-2"></i>Detecting address...';
        }
        if (btnConfirm) btnConfirm.disabled = true;

        try {
            const url = `${locState.apiUrl}?action=reverse&lat=${lat}&lon=${lon}`;
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();

            locState.modalSelected = {
                lat: lat,
                lon: lon,
                display_name: data.display_name || `${lat.toFixed(4)}, ${lon.toFixed(4)}`,
                city: data.city || data.district || '',
                suburb: data.suburb || '',
                district: data.district || '',
                state: data.state || '',
                locality_label: data.locality_label || ''
            };

            if (previewEl) {
                previewEl.textContent = locState.modalSelected.display_name;
            }
            if (btnConfirm) btnConfirm.disabled = false;
        } catch (e) {
            console.error('Reverse geocode failed:', e);
            locState.modalSelected = {
                lat: lat,
                lon: lon,
                display_name: `Coordinates: ${lat.toFixed(4)}, ${lon.toFixed(4)}`,
                city: '',
                suburb: '',
                district: '',
                state: '',
                locality_label: ''
            };
            if (previewEl) {
                previewEl.textContent = locState.modalSelected.display_name;
            }
            if (btnConfirm) btnConfirm.disabled = false;
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();

