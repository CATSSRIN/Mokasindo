<template>
  <div class="location-picker">
    <!-- Search Box -->
    <div class="mb-4">
      <div class="relative">
        <input
          ref="searchInput"
          type="text"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          placeholder="Cari lokasi..."
          @keyup.enter="searchLocation"
        />
        <button
          type="button"
          class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm"
          @click="useMyLocation"
        >
          <span class="flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Lokasi Saya
          </span>
        </button>
      </div>
    </div>

    <!-- Map Container -->
    <div 
      ref="mapContainer" 
      class="w-full h-96 rounded-lg border border-gray-300"
      :class="{ 'bg-gray-100 flex items-center justify-center': !mapLoaded }"
    >
      <div v-if="!mapLoaded" class="text-gray-500">
        <div v-if="loading" class="flex items-center gap-2">
          <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Memuat peta...
        </div>
        <div v-else>Peta tidak tersedia</div>
      </div>
    </div>

    <!-- Coordinates Display -->
    <div class="mt-4 grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Latitude</label>
        <input
          type="text"
          :value="location.lat?.toFixed(8)"
          readonly
          class="mt-1 w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Longitude</label>
        <input
          type="text"
          :value="location.lng?.toFixed(8)"
          readonly
          class="mt-1 w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm"
        />
      </div>
    </div>

    <!-- Address Display -->
    <div class="mt-4" v-if="location.address">
      <label class="block text-sm font-medium text-gray-700">Alamat Lengkap</label>
      <textarea
        :value="location.address"
        readonly
        rows="2"
        class="mt-1 w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm"
      ></textarea>
    </div>

    <!-- Loading Overlay -->
    <div v-if="geocoding" class="mt-2 text-sm text-blue-600 flex items-center gap-2">
      <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Mengambil alamat...
    </div>

    <!-- Error Message -->
    <div v-if="error" class="mt-2 text-sm text-red-600">
      {{ error }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'LocationPicker',
  
  props: {
    initialLat: {
      type: Number,
      default: -6.2088 // Jakarta default
    },
    initialLng: {
      type: Number,
      default: 106.8456
    },
    modelValue: {
      type: Object,
      default: () => ({
        lat: null,
        lng: null,
        address: ''
      })
    }
  },

  emits: ['update:modelValue', 'location-changed'],

  data() {
    return {
      map: null,
      marker: null,
      geocoder: null,
      searchBox: null,
      mapLoaded: false,
      loading: true,
      geocoding: false,
      error: null,
      location: {
        lat: this.modelValue?.lat || this.initialLat,
        lng: this.modelValue?.lng || this.initialLng,
        address: this.modelValue?.address || ''
      }
    };
  },

  mounted() {
    this.loadGoogleMaps();
  },

  methods: {
    loadGoogleMaps() {
      if (window.google && window.google.maps) {
        this.initMap();
        return;
      }

      const apiKey = document.querySelector('meta[name="google-maps-api-key"]')?.content;
      
      if (!apiKey) {
        this.loading = false;
        this.error = 'Google Maps API key tidak ditemukan';
        return;
      }

      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
      script.async = true;
      script.defer = true;
      script.onload = () => this.initMap();
      script.onerror = () => {
        this.loading = false;
        this.error = 'Gagal memuat Google Maps';
      };
      document.head.appendChild(script);
    },

    initMap() {
      const mapOptions = {
        center: { lat: this.location.lat, lng: this.location.lng },
        zoom: 15,
        mapTypeControl: true,
        streetViewControl: false,
        fullscreenControl: true,
      };

      this.map = new google.maps.Map(this.$refs.mapContainer, mapOptions);
      this.geocoder = new google.maps.Geocoder();

      // Create draggable marker
      this.marker = new google.maps.Marker({
        position: { lat: this.location.lat, lng: this.location.lng },
        map: this.map,
        draggable: true,
        animation: google.maps.Animation.DROP,
      });

      // Listen for marker drag
      this.marker.addListener('dragend', () => {
        const position = this.marker.getPosition();
        this.updateLocation(position.lat(), position.lng());
      });

      // Listen for map click
      this.map.addListener('click', (event) => {
        this.marker.setPosition(event.latLng);
        this.updateLocation(event.latLng.lat(), event.latLng.lng());
      });

      // Initialize search box
      this.initSearchBox();

      this.mapLoaded = true;
      this.loading = false;

      // If we have initial coordinates, reverse geocode them
      if (this.location.lat && this.location.lng && !this.location.address) {
        this.reverseGeocode(this.location.lat, this.location.lng);
      }
    },

    initSearchBox() {
      const searchBox = new google.maps.places.SearchBox(this.$refs.searchInput);
      
      searchBox.addListener('places_changed', () => {
        const places = searchBox.getPlaces();
        if (places.length === 0) return;

        const place = places[0];
        if (!place.geometry || !place.geometry.location) return;

        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();

        this.map.setCenter({ lat, lng });
        this.map.setZoom(17);
        this.marker.setPosition({ lat, lng });
        
        this.updateLocation(lat, lng, place.formatted_address);
      });
    },

    searchLocation() {
      const query = this.$refs.searchInput.value;
      if (!query || !this.geocoder) return;

      this.geocoder.geocode({ address: query }, (results, status) => {
        if (status === 'OK' && results[0]) {
          const location = results[0].geometry.location;
          const lat = location.lat();
          const lng = location.lng();

          this.map.setCenter({ lat, lng });
          this.map.setZoom(17);
          this.marker.setPosition({ lat, lng });
          
          this.updateLocation(lat, lng, results[0].formatted_address);
        } else {
          this.error = 'Lokasi tidak ditemukan';
          setTimeout(() => { this.error = null; }, 3000);
        }
      });
    },

    useMyLocation() {
      if (!navigator.geolocation) {
        this.error = 'Geolocation tidak didukung browser Anda';
        return;
      }

      this.loading = true;
      this.error = null;

      navigator.geolocation.getCurrentPosition(
        (position) => {
          const lat = position.coords.latitude;
          const lng = position.coords.longitude;

          if (this.map && this.marker) {
            this.map.setCenter({ lat, lng });
            this.map.setZoom(17);
            this.marker.setPosition({ lat, lng });
          }

          this.updateLocation(lat, lng);
          this.loading = false;
        },
        (error) => {
          this.loading = false;
          switch (error.code) {
            case error.PERMISSION_DENIED:
              this.error = 'Izin lokasi ditolak. Silakan aktifkan di pengaturan browser.';
              break;
            case error.POSITION_UNAVAILABLE:
              this.error = 'Informasi lokasi tidak tersedia.';
              break;
            case error.TIMEOUT:
              this.error = 'Permintaan lokasi timeout.';
              break;
            default:
              this.error = 'Gagal mendapatkan lokasi.';
          }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
      );
    },

    updateLocation(lat, lng, address = null) {
      this.location.lat = lat;
      this.location.lng = lng;
      
      if (address) {
        this.location.address = address;
        this.emitUpdate();
      } else {
        this.reverseGeocode(lat, lng);
      }
    },

    async reverseGeocode(lat, lng) {
      this.geocoding = true;
      this.error = null;

      try {
        // Try backend API first
        const response = await fetch('/api/locations/reverse-geocode', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: JSON.stringify({ lat, lng })
        });

        if (response.ok) {
          const data = await response.json();
          if (data.status === 'success') {
            this.location.address = data.data.full_address;
            this.emitUpdate(data.data);
            this.geocoding = false;
            return;
          }
        }

        // Fallback to Google Maps client-side geocoding
        if (this.geocoder) {
          this.geocoder.geocode({ location: { lat, lng } }, (results, status) => {
            if (status === 'OK' && results[0]) {
              this.location.address = results[0].formatted_address;
              this.emitUpdate();
            }
            this.geocoding = false;
          });
        } else {
          this.geocoding = false;
        }
      } catch (err) {
        this.geocoding = false;
        // Silent fail - address won't be updated but coordinates are still valid
      }
    },

    emitUpdate(geocodeData = null) {
      const payload = {
        lat: this.location.lat,
        lng: this.location.lng,
        address: this.location.address,
        ...(geocodeData && {
          province: geocodeData.province,
          city: geocodeData.city,
          district: geocodeData.district,
          sub_district: geocodeData.sub_district,
          postal_code: geocodeData.postal_code,
        })
      };

      this.$emit('update:modelValue', payload);
      this.$emit('location-changed', payload);
    }
  }
};
</script>

<style scoped>
.location-picker {
  @apply w-full;
}
</style>
