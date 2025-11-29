<template>
  <div class="vehicle-search min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-gray-900">Cari Kendaraan</h1>
      </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
      <div class="flex flex-col lg:flex-row gap-6">
        <!-- Filters Sidebar -->
        <aside class="w-full lg:w-80 flex-shrink-0">
          <div class="bg-white rounded-lg shadow p-4 sticky top-4">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-lg font-semibold">Filter</h2>
              <button @click="resetFilters" class="text-sm text-blue-600 hover:text-blue-800">
                Reset
              </button>
            </div>

            <!-- Search -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Kata Kunci</label>
              <input
                v-model="filters.q"
                type="text"
                placeholder="Cari brand, model..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                @keyup.enter="search"
              />
            </div>

            <!-- Category -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
              <select v-model="filters.category" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                <option value="">Semua</option>
                <option value="mobil">Mobil</option>
                <option value="motor">Motor</option>
              </select>
            </div>

            <!-- Price Range -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Rentang Harga</label>
              <div class="grid grid-cols-2 gap-2">
                <input
                  v-model.number="filters.min_price"
                  type="number"
                  placeholder="Min"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md"
                />
                <input
                  v-model.number="filters.max_price"
                  type="number"
                  placeholder="Max"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md"
                />
              </div>
            </div>

            <!-- Year Range -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
              <div class="grid grid-cols-2 gap-2">
                <input
                  v-model.number="filters.year_from"
                  type="number"
                  placeholder="Dari"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md"
                />
                <input
                  v-model.number="filters.year_to"
                  type="number"
                  placeholder="Sampai"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md"
                />
              </div>
            </div>

            <!-- Location Filter -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Provinsi</label>
              <select v-model="filters.province_id" @change="onProvinceChange" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                <option value="">Semua Provinsi</option>
                <option v-for="province in provinces" :key="province.id" :value="province.id">
                  {{ province.name }}
                </option>
              </select>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Kota</label>
              <select v-model="filters.city_id" :disabled="!filters.province_id" class="w-full px-3 py-2 border border-gray-300 rounded-md disabled:bg-gray-100">
                <option value="">Semua Kota</option>
                <option v-for="city in cities" :key="city.id" :value="city.id">
                  {{ city.name }}
                </option>
              </select>
            </div>

            <!-- Radius Search -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Radius dari Lokasi Saya
              </label>
              <div class="flex gap-2">
                <select v-model.number="filters.radius" class="flex-1 px-3 py-2 border border-gray-300 rounded-md">
                  <option value="">Tidak aktif</option>
                  <option value="10">10 km</option>
                  <option value="25">25 km</option>
                  <option value="50">50 km</option>
                  <option value="100">100 km</option>
                </select>
                <button
                  @click="getMyLocation"
                  :disabled="gettingLocation"
                  class="px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:bg-blue-300"
                  title="Gunakan lokasi saya"
                >
                  <svg class="w-5 h-5" :class="{ 'animate-spin': gettingLocation }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="!gettingLocation" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path v-if="!gettingLocation" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                  </svg>
                </button>
              </div>
              <p v-if="userLocation" class="mt-1 text-xs text-green-600">
                Lokasi terdeteksi
              </p>
            </div>

            <!-- Sort -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Urutkan</label>
              <select v-model="filters.sort" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                <option value="newest">Terbaru</option>
                <option value="price_asc">Harga Terendah</option>
                <option value="price_desc">Harga Tertinggi</option>
                <option value="distance" :disabled="!userLocation">Terdekat</option>
              </select>
            </div>

            <button
              @click="search"
              :disabled="loading"
              class="w-full py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-blue-300"
            >
              {{ loading ? 'Mencari...' : 'Cari' }}
            </button>
          </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1">
          <!-- View Toggle -->
          <div class="bg-white rounded-lg shadow p-4 mb-4">
            <div class="flex justify-between items-center">
              <p class="text-gray-600">
                <span class="font-semibold">{{ meta.total }}</span> kendaraan ditemukan
              </p>
              <div class="flex gap-2">
                <button
                  @click="viewMode = 'list'"
                  :class="viewMode === 'list' ? 'bg-blue-100 text-blue-600' : 'text-gray-600'"
                  class="p-2 rounded-md hover:bg-gray-100"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                  </svg>
                </button>
                <button
                  @click="viewMode = 'map'"
                  :class="viewMode === 'map' ? 'bg-blue-100 text-blue-600' : 'text-gray-600'"
                  class="p-2 rounded-md hover:bg-gray-100"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Map View -->
          <div v-if="viewMode === 'map'" class="bg-white rounded-lg shadow overflow-hidden mb-4">
            <div ref="searchMap" class="w-full h-96 lg:h-[500px]"></div>
          </div>

          <!-- List View -->
          <div v-if="viewMode === 'list' || viewMode === 'map'">
            <!-- Loading State -->
            <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
              <div v-for="i in 6" :key="i" class="bg-white rounded-lg shadow overflow-hidden animate-pulse">
                <div class="h-48 bg-gray-200"></div>
                <div class="p-4">
                  <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                  <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
                  <div class="h-6 bg-gray-200 rounded w-1/3"></div>
                </div>
              </div>
            </div>

            <!-- Results -->
            <div v-else-if="vehicles.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
              <div
                v-for="vehicle in vehicles"
                :key="vehicle.id"
                class="bg-white rounded-lg shadow overflow-hidden hover:shadow-lg transition-shadow cursor-pointer"
                @click="viewVehicle(vehicle.id)"
              >
                <div class="h-48 bg-gray-200 relative">
                  <img
                    v-if="vehicle.images && vehicle.images.length"
                    :src="vehicle.images[0].image_path"
                    :alt="vehicle.brand + ' ' + vehicle.model"
                    class="w-full h-full object-cover"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                  </div>
                  <span class="absolute top-2 left-2 px-2 py-1 text-xs font-semibold rounded" :class="vehicle.category === 'mobil' ? 'bg-blue-500 text-white' : 'bg-green-500 text-white'">
                    {{ vehicle.category === 'mobil' ? 'Mobil' : 'Motor' }}
                  </span>
                  <span v-if="vehicle.distance_km" class="absolute top-2 right-2 px-2 py-1 text-xs font-semibold bg-white rounded shadow">
                    {{ vehicle.distance_km }} km
                  </span>
                </div>
                <div class="p-4">
                  <h3 class="font-semibold text-gray-900 truncate">
                    {{ vehicle.brand }} {{ vehicle.model }}
                  </h3>
                  <p class="text-sm text-gray-500 mb-2">
                    {{ vehicle.year }} • {{ vehicle.transmission }} • {{ vehicle.mileage?.toLocaleString() }} km
                  </p>
                  <p class="text-lg font-bold text-blue-600">
                    Rp {{ formatPrice(vehicle.price) }}
                  </p>
                  <p class="text-xs text-gray-400 mt-1">
                    {{ vehicle.location?.city || '-' }}, {{ vehicle.location?.province || '-' }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white rounded-lg shadow p-8 text-center">
              <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <h3 class="text-lg font-medium text-gray-900 mb-1">Tidak ada hasil</h3>
              <p class="text-gray-500">Coba ubah filter pencarian Anda</p>
            </div>

            <!-- Pagination -->
            <div v-if="meta.last_page > 1" class="mt-6 flex justify-center">
              <nav class="flex gap-1">
                <button
                  @click="goToPage(meta.current_page - 1)"
                  :disabled="meta.current_page === 1"
                  class="px-3 py-2 rounded-md border border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                >
                  Prev
                </button>
                <button
                  v-for="page in visiblePages"
                  :key="page"
                  @click="goToPage(page)"
                  :class="page === meta.current_page ? 'bg-blue-600 text-white' : 'border border-gray-300 hover:bg-gray-50'"
                  class="px-3 py-2 rounded-md"
                >
                  {{ page }}
                </button>
                <button
                  @click="goToPage(meta.current_page + 1)"
                  :disabled="meta.current_page === meta.last_page"
                  class="px-3 py-2 rounded-md border border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                >
                  Next
                </button>
              </nav>
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'VehicleSearch',

  data() {
    return {
      viewMode: 'list',
      loading: false,
      gettingLocation: false,
      vehicles: [],
      provinces: [],
      cities: [],
      userLocation: null,
      map: null,
      markers: [],
      filters: {
        q: '',
        category: '',
        province_id: '',
        city_id: '',
        min_price: null,
        max_price: null,
        year_from: null,
        year_to: null,
        radius: '',
        sort: 'newest',
      },
      meta: {
        total: 0,
        per_page: 20,
        current_page: 1,
        last_page: 1,
      }
    };
  },

  computed: {
    visiblePages() {
      const pages = [];
      const total = this.meta.last_page;
      const current = this.meta.current_page;
      
      let start = Math.max(1, current - 2);
      let end = Math.min(total, current + 2);
      
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }
      
      return pages;
    }
  },

  watch: {
    viewMode(newMode) {
      if (newMode === 'map') {
        this.$nextTick(() => this.initSearchMap());
      }
    }
  },

  mounted() {
    this.loadProvinces();
    this.search();
  },

  methods: {
    async loadProvinces() {
      try {
        const response = await fetch('/api/locations/provinces');
        const data = await response.json();
        if (data.status === 'success') {
          this.provinces = data.data;
        }
      } catch (error) {
        console.error('Failed to load provinces:', error);
      }
    },

    async onProvinceChange() {
      this.filters.city_id = '';
      this.cities = [];
      
      if (this.filters.province_id) {
        try {
          const response = await fetch(`/api/locations/cities/${this.filters.province_id}`);
          const data = await response.json();
          if (data.status === 'success') {
            this.cities = data.data;
          }
        } catch (error) {
          console.error('Failed to load cities:', error);
        }
      }
    },

    async search(page = 1) {
      this.loading = true;
      
      const params = new URLSearchParams();
      params.append('page', page);
      
      if (this.filters.q) params.append('q', this.filters.q);
      if (this.filters.category) params.append('category', this.filters.category);
      if (this.filters.province_id) params.append('province_id', this.filters.province_id);
      if (this.filters.city_id) params.append('city_id', this.filters.city_id);
      if (this.filters.min_price) params.append('min_price', this.filters.min_price);
      if (this.filters.max_price) params.append('max_price', this.filters.max_price);
      if (this.filters.year_from) params.append('year_from', this.filters.year_from);
      if (this.filters.year_to) params.append('year_to', this.filters.year_to);
      if (this.filters.sort) params.append('sort', this.filters.sort);
      
      if (this.filters.radius && this.userLocation) {
        params.append('lat', this.userLocation.lat);
        params.append('lng', this.userLocation.lng);
        params.append('radius', this.filters.radius);
      }

      try {
        const response = await fetch(`/api/vehicles/search?${params.toString()}`);
        const data = await response.json();
        
        if (data.status === 'success') {
          this.vehicles = data.data;
          this.meta = data.meta;
          
          if (this.viewMode === 'map' && this.map) {
            this.updateMapMarkers();
          }
        }
      } catch (error) {
        console.error('Search failed:', error);
      }
      
      this.loading = false;
    },

    resetFilters() {
      this.filters = {
        q: '',
        category: '',
        province_id: '',
        city_id: '',
        min_price: null,
        max_price: null,
        year_from: null,
        year_to: null,
        radius: '',
        sort: 'newest',
      };
      this.cities = [];
      this.search();
    },

    goToPage(page) {
      if (page >= 1 && page <= this.meta.last_page) {
        this.search(page);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    },

    getMyLocation() {
      if (!navigator.geolocation) {
        alert('Geolocation tidak didukung browser Anda');
        return;
      }

      this.gettingLocation = true;
      
      navigator.geolocation.getCurrentPosition(
        (position) => {
          this.userLocation = {
            lat: position.coords.latitude,
            lng: position.coords.longitude
          };
          this.gettingLocation = false;
          
          if (this.filters.radius) {
            this.search();
          }
        },
        (error) => {
          this.gettingLocation = false;
          alert('Gagal mendapatkan lokasi. Pastikan izin lokasi diaktifkan.');
        },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    },

    initSearchMap() {
      if (!window.google || !window.google.maps) {
        console.warn('Google Maps not loaded');
        return;
      }

      const center = this.userLocation || { lat: -6.2088, lng: 106.8456 };
      
      this.map = new google.maps.Map(this.$refs.searchMap, {
        center: center,
        zoom: 10,
        mapTypeControl: true,
        streetViewControl: false,
      });

      this.updateMapMarkers();
    },

    updateMapMarkers() {
      // Clear existing markers
      this.markers.forEach(marker => marker.setMap(null));
      this.markers = [];

      if (!this.map) return;

      const bounds = new google.maps.LatLngBounds();
      let hasValidCoords = false;

      this.vehicles.forEach(vehicle => {
        if (vehicle.latitude && vehicle.longitude) {
          hasValidCoords = true;
          const position = { lat: vehicle.latitude, lng: vehicle.longitude };
          
          const marker = new google.maps.Marker({
            position: position,
            map: this.map,
            title: `${vehicle.brand} ${vehicle.model}`,
          });

          const infoWindow = new google.maps.InfoWindow({
            content: `
              <div class="p-2 max-w-xs">
                <h3 class="font-semibold">${vehicle.brand} ${vehicle.model}</h3>
                <p class="text-sm text-gray-600">${vehicle.year}</p>
                <p class="font-bold text-blue-600">Rp ${this.formatPrice(vehicle.price)}</p>
                ${vehicle.distance_km ? `<p class="text-xs text-gray-500">${vehicle.distance_km} km dari Anda</p>` : ''}
              </div>
            `
          });

          marker.addListener('click', () => {
            infoWindow.open(this.map, marker);
          });

          this.markers.push(marker);
          bounds.extend(position);
        }
      });

      if (hasValidCoords && this.markers.length > 0) {
        this.map.fitBounds(bounds);
        if (this.markers.length === 1) {
          this.map.setZoom(15);
        }
      }
    },

    formatPrice(price) {
      return new Intl.NumberFormat('id-ID').format(price);
    },

    viewVehicle(id) {
      window.location.href = `/etalase/vehicles/${id}`;
    }
  }
};
</script>

<style scoped>
.vehicle-search {
  font-family: system-ui, -apple-system, sans-serif;
}
</style>
