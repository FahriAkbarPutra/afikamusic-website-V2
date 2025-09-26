document.addEventListener('DOMContentLoaded', () => {
    // Fungsi Animasi Gelombang
    const wave1 = document.getElementById('wave1');
    const wave2 = document.getElementById('wave2');
    const wave3 = document.getElementById('wave3');
    if (wave1 && wave2 && wave3) {
        let time = 0;
        const animateWaves = () => {
            const width = window.innerWidth;
            const height = window.innerHeight;
            wave1.setAttribute('d', generateWavePath(width, height, time, 110, 0.002, 0.35)); // top (blue)
            wave2.setAttribute('d', generateWavePath(width, height, time, 130, 0.0018, 0.52)); // middle (yellow)
            wave3.setAttribute('d', generateWavePath(width, height, time, 150, 0.0014, 0.68)); // bottom (blue)
            time += 0.3;
            requestAnimationFrame(animateWaves);
        };
        const generateWavePath = (width, height, time, amplitude, frequency, yOffset) => {
            let path = `M ${-5} ${height * yOffset}`;
            for (let x = 0; x <= width + 5; x += 5) {
                const y = Math.sin(x * frequency - time * 0.05) * amplitude + (height * yOffset);
                path += ` L ${x} ${y}`;
            }
            return path;
        };
        animateWaves();
    }

    // Fungsi Animasi Scroll
    const observerOptions = { root: null, rootMargin: '0px', threshold: 0.1 };
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    const sectionsToAnimate = document.querySelectorAll('.fade-in-section');
    sectionsToAnimate.forEach(section => observer.observe(section));

    // Fungsi Menu Mobile
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const navContainer = document.getElementById('nav-container');
    const navOverlay = document.getElementById('nav-overlay');
    const mobileDrawer = document.getElementById('mobile-drawer');
    const mobileDrawerClose = document.getElementById('mobile-drawer-close');
    if (mobileMenuButton && mobileDrawer) {
        const toggleMenu = () => {
            const isOpen = mobileDrawer.classList.toggle('is-open');
            mobileMenuButton.classList.toggle('is-active', isOpen);
            document.body.classList.toggle('menu-open', isOpen);
            mobileMenuButton.setAttribute('aria-expanded', String(isOpen));
        };
        mobileMenuButton.addEventListener('click', toggleMenu);
        mobileDrawer.addEventListener('click', (e) => {
            const target = e.target;
            if (target.tagName === 'A' && mobileDrawer.classList.contains('is-open')) {
                toggleMenu();
            }
        });
        if (mobileDrawerClose) {
            mobileDrawerClose.addEventListener('click', () => {
                if (mobileDrawer.classList.contains('is-open')) toggleMenu();
            });
        }
        if (navOverlay) {
            navOverlay.addEventListener('click', () => {
                if (mobileDrawer.classList.contains('is-open')) toggleMenu();
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && mobileDrawer.classList.contains('is-open')) {
                toggleMenu();
            }
        });
    }

    // Set active nav link based on current path
    const basePath = document.body.getAttribute('data-base-path') || '';
    const currentPath = window.location.pathname.replace(basePath, '') || '/home';
    const navLinks = document.querySelectorAll('#nav-container .nav-center a, #mobile-drawer .mobile-drawer__links a');
    navLinks.forEach(link => {
        try {
            const linkPath = new URL(link.href).pathname.replace(basePath, '');
            if (linkPath === currentPath || (currentPath === '/' && linkPath === '/home')) {
                link.classList.add('is-active');
            }
        } catch (_) {}
        // Close drawer when clicking inside
        link.addEventListener('click', () => {
            if (mobileDrawer && mobileDrawer.classList.contains('is-open')) {
                mobileDrawer.classList.remove('is-open');
                mobileMenuButton.classList.remove('is-active');
                document.body.classList.remove('menu-open');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Leaflet Map Initialization for Admin Schedule Page
    const scheduleMapElement = document.getElementById('schedule-map');
    if (scheduleMapElement && typeof L !== 'undefined') {
        // Initialize map centered on Indonesia (Jakarta)
        const scheduleMap = L.map('schedule-map', {
            center: [-6.2088, 106.8456],
            zoom: 12,
            zoomControl: true,
            scrollWheelZoom: true,
            dragging: true,
            touchZoom: true,
            doubleClickZoom: true
        });

        // Add tile layer with better attribution
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(scheduleMap);

        // Initialize marker with custom icon and draggable
        let scheduleMarker = L.marker([-6.2088, 106.8456], {
            draggable: true,
            title: 'Geser marker ini untuk menentukan lokasi'
        }).addTo(scheduleMap);

        // Add popup to marker with instructions
        scheduleMarker.bindPopup('Geser marker ini atau klik di peta untuk menentukan lokasi event').openPopup();

        // Function to update coordinates in form
        function updateCoordinates(lat, lng) {
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const locationStatus = document.getElementById('location-search-status');
            
            if (latInput && lngInput) {
                latInput.value = lat.toFixed(6);
                lngInput.value = lng.toFixed(6);
                
                if (locationStatus) {
                    locationStatus.textContent = `Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                    locationStatus.style.color = '#22c55e';
                }
            }
        }

        // Update coordinates when marker is dragged
        scheduleMarker.on('dragend', function(e) {
            const position = e.target.getLatLng();
            updateCoordinates(position.lat, position.lng);
            scheduleMarker.bindPopup(`Lokasi: ${position.lat.toFixed(4)}, ${position.lng.toFixed(4)}`).openPopup();
        });

        // Update marker position when map is clicked
        scheduleMap.on('click', function(e) {
            const position = e.latlng;
            scheduleMarker.setLatLng(position);
            updateCoordinates(position.lat, position.lng);
            scheduleMarker.bindPopup(`Lokasi: ${position.lat.toFixed(4)}, ${position.lng.toFixed(4)}`).openPopup();
        });

        // Location search functionality
        const locationSearchBtn = document.getElementById('location-search');
        const locationInput = document.getElementById('location');
        const locationStatus = document.getElementById('location-search-status');

        if (locationSearchBtn && locationInput && locationStatus) {
            async function searchLocation() {
                const query = locationInput.value.trim();
                if (!query) {
                    locationStatus.textContent = 'Masukkan alamat terlebih dahulu.';
                    locationStatus.style.color = 'var(--admin-text-muted)';
                    return;
                }

                locationStatus.textContent = 'Mencari lokasi...';
                locationStatus.style.color = '#0ea5e9';
                locationSearchBtn.disabled = true;
                locationSearchBtn.textContent = 'Mencari...';

                try {
                    // Use PHP proxy to avoid CORS issues
                    const basePath = document.body.getAttribute('data-base-path') || '';
                    const response = await fetch(`${basePath}/geocode.php?q=${encodeURIComponent(query)}`);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();

                    if (data && data.length > 0) {
                        const result = data[0];
                        const lat = parseFloat(result.lat);
                        const lng = parseFloat(result.lon);
                        const displayName = result.display_name;

                        // Update map view and marker with smooth animation
                        scheduleMap.flyTo([lat, lng], 16, {
                            animate: true,
                            duration: 1.5
                        });
                        
                        scheduleMarker.setLatLng([lat, lng]);
                        
                        // Show popup with found location and source
                        const source = result.source || 'unknown';
                        let sourceLabel = 'Unknown';
                        let sourceColor = '#6B7280';
                        
                        switch(source) {
                            case 'offline':
                                sourceLabel = 'Database Lokal';
                                sourceColor = '#10B981';
                                break;
                            case 'indonesian_address_api':
                                sourceLabel = 'Indonesian Address API';
                                sourceColor = '#DC2626';
                                break;
                            case 'positionstack':
                                sourceLabel = 'PositionStack';
                                sourceColor = '#F59E0B';
                                break;
                            case 'nominatim':
                                sourceLabel = 'OpenStreetMap';
                                sourceColor = '#7C3AED';
                                break;
                            case 'google':
                                sourceLabel = 'Google Maps';
                                sourceColor = '#4285f4';
                                break;
                        }
                        
                        scheduleMarker.bindPopup(`
                            <div style="max-width: 200px;">
                                <strong>Lokasi Ditemukan:</strong><br>
                                <small>${displayName}</small><br>
                                <small>Koordinat: ${lat.toFixed(4)}, ${lng.toFixed(4)}</small><br>
                                <small style="color: ${sourceColor};">📍 Via ${sourceLabel}</small>
                            </div>
                        `).openPopup();

                        // Update coordinate inputs
                        updateCoordinates(lat, lng);

                        // Update location field with cleaner address
                        if (result.address) {
                            const address = result.address;
                            let cleanAddress = '';
                            
                            if (address.road) cleanAddress += address.road;
                            if (address.village || address.suburb) cleanAddress += (cleanAddress ? ', ' : '') + (address.village || address.suburb);
                            if (address.city || address.town) cleanAddress += (cleanAddress ? ', ' : '') + (address.city || address.town);
                            if (address.state) cleanAddress += (cleanAddress ? ', ' : '') + address.state;
                            
                            if (cleanAddress) {
                                locationInput.value = cleanAddress;
                            }
                        }

                        locationStatus.textContent = `Lokasi ditemukan! Geser marker untuk penyesuaian yang lebih tepat.`;
                        locationStatus.style.color = '#22c55e';
                    } else {
                        locationStatus.textContent = 'Lokasi tidak ditemukan. Coba dengan alamat yang lebih spesifik (misalnya: "Jalan Sudirman Jakarta").';
                        locationStatus.style.color = '#ef4444';
                    }
                } catch (error) {
                    console.error('Error searching location:', error);
                    locationStatus.textContent = 'Terjadi kesalahan saat mencari lokasi. Periksa koneksi internet Anda.';
                    locationStatus.style.color = '#ef4444';
                } finally {
                    locationSearchBtn.disabled = false;
                    locationSearchBtn.textContent = 'Cari';
                }
            }

            locationSearchBtn.addEventListener('click', searchLocation);

            // Allow search on Enter key press
            locationInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchLocation();
                }
            });
        }

        // Set initial coordinates if available
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        if (latInput && lngInput && latInput.value && lngInput.value) {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                scheduleMap.setView([lat, lng], 15);
                scheduleMarker.setLatLng([lat, lng]);
                scheduleMarker.bindPopup(`Lokasi Tersimpan: ${lat.toFixed(4)}, ${lng.toFixed(4)}`);
                locationStatus.textContent = `Koordinat tersimpan: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                locationStatus.style.color = '#22c55e';
            }
        }

        // Ensure map renders properly after initialization
        setTimeout(() => {
            scheduleMap.invalidateSize();
        }, 250);
    }
});