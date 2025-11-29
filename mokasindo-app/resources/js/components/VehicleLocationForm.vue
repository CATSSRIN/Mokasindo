<template>
  <div class="vehicle-location-form">
    <!-- Google Maps Picker -->
    <div class="mb-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Pilih Lokasi di Peta</h3>
      <LocationPicker
        v-model="location"
        :initial-lat="form.latitude || -6.2088"
        :initial-lng="form.longitude || 106.8456"
        @location-changed="handleLocationChange"
      />
    </div>

    <!-- Location Form Fields -->
    <div class="space-y-4">
      <h3 class="text-lg font-medium text-gray-900">Detail Lokasi</h3>
      
      <!-- Province -->
      <div>
        <label class="block text-sm font-medium text-gray-700">
          Provinsi <span class="text-red-500">*</span>
        </label>
        <select
          v-model="form.province_id"
          @change="onProvinceChange"
          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
          :class="{ 'border-red-500': errors.province_id }"
        >
          <option value="">Pilih Provinsi</option>
          <option v-for="province in provinces" :key="province.id" :value="province.id">
            {{ province.name }}
          </option>
        </select>
        <p v-if="errors.province_id" class="mt-1 text-sm text-red-500">{{ errors.province_id }}</p>
      </div>

      <!-- City -->
      <div>
        <label class="block text-sm font-medium text-gray-700">
          Kota/Kabupaten <span class="text-red-500">*</span>
        </label>
        <select
          v-model="form.city_id"
          @change="onCityChange"
          :disabled="!form.province_id || loadingCities"
          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
          :class="{ 'border-red-500': errors.city_id }"
        >
          <option value="">{{ loadingCities ? 'Memuat...' : 'Pilih Kota/Kabupaten' }}</option>
          <option v-for="city in cities" :key="city.id" :value="city.id">
            {{ city.name }}
          </option>
        </select>
        <p v-if="errors.city_id" class="mt-1 text-sm text-red-500">{{ errors.city_id }}</p>
      </div>

      <!-- District -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Kecamatan</label>
        <select
          v-model="form.district_id"
          @change="onDistrictChange"
          :disabled="!form.city_id || loadingDistricts"
          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
        >
          <option value="">{{ loadingDistricts ? 'Memuat...' : 'Pilih Kecamatan' }}</option>
          <option v-for="district in districts" :key="district.id" :value="district.id">
            {{ district.name }}
          </option>
        </select>
      </div>

      <!-- Sub District -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Kelurahan</label>
        <select
          v-model="form.sub_district_id"
          @change="onSubDistrictChange"
          :disabled="!form.district_id || loadingSubDistricts"
          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
        >
          <option value="">{{ loadingSubDistricts ? 'Memuat...' : 'Pilih Kelurahan' }}</option>
          <option v-for="subDistrict in subDistricts" :key="subDistrict.id" :value="subDistrict.id">
            {{ subDistrict.name }}
          </option>
        </select>
      </div>

      <!-- Postal Code -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Kode Pos</label>
        <input
          v-model="form.postal_code"
          type="text"
          maxlength="10"
          placeholder="Kode Pos"
          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
        />
      </div>

      <!-- Full Address -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Alamat Lengkap</label>
        <textarea
          v-model="form.full_address"
          rows="3"
          placeholder="Alamat lengkap lokasi kendaraan"
          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
        ></textarea>
      </div>

      <!-- Hidden Coordinate Fields -->
      <input type="hidden" :value="form.latitude" />
      <input type="hidden" :value="form.longitude" />
    </div>
  </div>
</template>

<script>
import LocationPicker from './LocationPicker.vue';

export default {
  name: 'VehicleLocationForm',
  
  components: {
    LocationPicker
  },

  props: {
    modelValue: {
      type: Object,
      default: () => ({})
    },
    errors: {
      type: Object,
      default: () => ({})
    }
  },

  emits: ['update:modelValue'],

  data() {
    return {
      form: {
        province_id: this.modelValue.province_id || '',
        city_id: this.modelValue.city_id || '',
        district_id: this.modelValue.district_id || '',
        sub_district_id: this.modelValue.sub_district_id || '',
        postal_code: this.modelValue.postal_code || '',
        latitude: this.modelValue.latitude || null,
        longitude: this.modelValue.longitude || null,
        full_address: this.modelValue.full_address || '',
      },
      location: {
        lat: this.modelValue.latitude || null,
        lng: this.modelValue.longitude || null,
        address: this.modelValue.full_address || '',
      },
      provinces: [],
      cities: [],
      districts: [],
      subDistricts: [],
      loadingProvinces: false,
      loadingCities: false,
      loadingDistricts: false,
      loadingSubDistricts: false,
    };
  },

  watch: {
    form: {
      handler(newVal) {
        this.$emit('update:modelValue', newVal);
      },
      deep: true
    },
    modelValue: {
      handler(newVal) {
        if (newVal) {
          this.form = { ...this.form, ...newVal };
        }
      },
      deep: true
    }
  },

  mounted() {
    this.loadProvinces();
    
    // Load cascading data if editing
    if (this.form.province_id) {
      this.loadCities(this.form.province_id);
    }
    if (this.form.city_id) {
      this.loadDistricts(this.form.city_id);
    }
    if (this.form.district_id) {
      this.loadSubDistricts(this.form.district_id);
    }
  },

  methods: {
    async loadProvinces() {
      this.loadingProvinces = true;
      try {
        const response = await fetch('/api/locations/provinces');
        const data = await response.json();
        if (data.status === 'success') {
          this.provinces = data.data;
        }
      } catch (error) {
        console.error('Failed to load provinces:', error);
      }
      this.loadingProvinces = false;
    },

    async loadCities(provinceId) {
      if (!provinceId) {
        this.cities = [];
        return;
      }
      
      this.loadingCities = true;
      try {
        const response = await fetch(`/api/locations/cities/${provinceId}`);
        const data = await response.json();
        if (data.status === 'success') {
          this.cities = data.data;
        }
      } catch (error) {
        console.error('Failed to load cities:', error);
      }
      this.loadingCities = false;
    },

    async loadDistricts(cityId) {
      if (!cityId) {
        this.districts = [];
        return;
      }
      
      this.loadingDistricts = true;
      try {
        const response = await fetch(`/api/locations/districts/${cityId}`);
        const data = await response.json();
        if (data.status === 'success') {
          this.districts = data.data;
        }
      } catch (error) {
        console.error('Failed to load districts:', error);
      }
      this.loadingDistricts = false;
    },

    async loadSubDistricts(districtId) {
      if (!districtId) {
        this.subDistricts = [];
        return;
      }
      
      this.loadingSubDistricts = true;
      try {
        const response = await fetch(`/api/locations/sub-districts/${districtId}`);
        const data = await response.json();
        if (data.status === 'success') {
          this.subDistricts = data.data;
        }
      } catch (error) {
        console.error('Failed to load sub-districts:', error);
      }
      this.loadingSubDistricts = false;
    },

    onProvinceChange() {
      this.form.city_id = '';
      this.form.district_id = '';
      this.form.sub_district_id = '';
      this.cities = [];
      this.districts = [];
      this.subDistricts = [];
      
      if (this.form.province_id) {
        this.loadCities(this.form.province_id);
      }
    },

    onCityChange() {
      this.form.district_id = '';
      this.form.sub_district_id = '';
      this.districts = [];
      this.subDistricts = [];
      
      if (this.form.city_id) {
        this.loadDistricts(this.form.city_id);
      }
    },

    onDistrictChange() {
      this.form.sub_district_id = '';
      this.subDistricts = [];
      
      if (this.form.district_id) {
        this.loadSubDistricts(this.form.district_id);
      }
    },

    onSubDistrictChange() {
      // Auto-fill postal code from selected sub-district
      const selected = this.subDistricts.find(s => s.id === this.form.sub_district_id);
      if (selected && selected.postal_code) {
        this.form.postal_code = selected.postal_code;
      }
    },

    handleLocationChange(locationData) {
      this.form.latitude = locationData.lat;
      this.form.longitude = locationData.lng;
      this.form.full_address = locationData.address || this.form.full_address;
      
      // If geocode data includes location info, try to match with database
      if (locationData.province) {
        this.matchLocationFromGeocode(locationData);
      }
    },

    async matchLocationFromGeocode(geocodeData) {
      // Try to match province
      const province = this.provinces.find(p => 
        p.name.toLowerCase().includes(geocodeData.province?.toLowerCase()) ||
        geocodeData.province?.toLowerCase().includes(p.name.toLowerCase())
      );
      
      if (province && province.id !== this.form.province_id) {
        this.form.province_id = province.id;
        await this.loadCities(province.id);
        
        // Try to match city
        const city = this.cities.find(c =>
          c.name.toLowerCase().includes(geocodeData.city?.toLowerCase()) ||
          geocodeData.city?.toLowerCase().includes(c.name.toLowerCase())
        );
        
        if (city) {
          this.form.city_id = city.id;
          await this.loadDistricts(city.id);
          
          // Try to match district
          const district = this.districts.find(d =>
            d.name.toLowerCase().includes(geocodeData.district?.toLowerCase()) ||
            geocodeData.district?.toLowerCase().includes(d.name.toLowerCase())
          );
          
          if (district) {
            this.form.district_id = district.id;
            await this.loadSubDistricts(district.id);
            
            // Try to match sub-district
            const subDistrict = this.subDistricts.find(s =>
              s.name.toLowerCase().includes(geocodeData.sub_district?.toLowerCase()) ||
              geocodeData.sub_district?.toLowerCase().includes(s.name.toLowerCase())
            );
            
            if (subDistrict) {
              this.form.sub_district_id = subDistrict.id;
              if (subDistrict.postal_code) {
                this.form.postal_code = subDistrict.postal_code;
              }
            }
          }
        }
      }
      
      // Set postal code from geocode if available
      if (geocodeData.postal_code && !this.form.postal_code) {
        this.form.postal_code = geocodeData.postal_code;
      }
    }
  }
};
</script>

<style scoped>
.vehicle-location-form {
  @apply w-full;
}
</style>
