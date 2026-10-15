<template>
  <div class="container catalog-page">
    <div class="catalog-header">
      <h1 class="page-title">Etalase Komoditas Pertanian</h1>
      <p class="page-subtitle">Transparansi harga langsung dari produsen tani terverifikasi</p>
    </div>

    <!-- Filters Card -->
    <div class="card filter-card">
      <div class="grid grid-4">
        <div class="form-group">
          <label class="label">Pencarian</label>
          <input v-model="filters.q" @input="fetchCatalog" class="input" placeholder="Cari nama komoditas..." />
        </div>
        <div class="form-group">
          <label class="label">Wilayah</label>
          <select v-model="filters.regionId" @change="fetchCatalog" class="select">
            <option value="">Semua Wilayah</option>
            <option v-for="r in meta.regions" :key="r.id" :value="r.id">{{ r.name }} ({{ r.province }})</option>
          </select>
        </div>
        <div class="form-group">
          <label class="label">Kategori</label>
          <select v-model="filters.categoryId" @change="fetchCatalog" class="select">
            <option value="">Semua Kategori</option>
            <option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="label">Klasifikasi Grade</label>
          <select v-model="filters.grade" @change="fetchCatalog" class="select">
            <option value="">Semua Grade (A/B/C)</option>
            <option value="A">Grade A (Premium)</option>
            <option value="B">Grade B (Standar)</option>
            <option value="C">Grade C (Industri/Olahan)</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Products Grid -->
    <div v-if="loading" class="text-center py-8">
      <p class="text-muted">Memuat komoditas segar...</p>
    </div>

    <div v-else-if="items.length === 0" class="card text-center py-12">
      <h3>Belum ada komoditas yang cocok dengan filter</h3>
      <p class="text-muted mt-2">Coba ganti filter atau kata kunci pencarian Anda.</p>
    </div>

    <div v-else class="grid grid-3 mt-6">
      <div v-for="item in items" :key="item.id" class="card product-card">
        <div class="product-image-wrap">
          <img :src="resolveImageUrl(item.image_url)" :alt="item.name" class="product-image" loading="lazy" />
          <span class="badge badge-green region-tag">📍 {{ item.region }}</span>
        </div>

        <div class="product-info">
          <span class="category-name">{{ item.category }}</span>
          <h3 class="product-name">{{ item.name }}</h3>

          <div class="grades-wrap">
            <span class="text-muted text-xs">Pilihan Grade:</span>
            <span v-for="g in item.grades" :key="g" class="badge badge-blue">Grade {{ g }}</span>
          </div>

          <div class="price-row">
            <div>
              <span class="text-muted text-xs block">Mulai dari</span>
              <span class="price-val">{{ idr(item.price_from) }} <span class="text-xs text-muted">/kg</span></span>
            </div>
            <div class="stock-info">
              <span class="text-muted text-xs block">Total Stok</span>
              <span class="stock-val">{{ item.available }} kg</span>
            </div>
          </div>

          <router-link :to="`/catalog/${item.slug}`" class="btn btn-primary w-full mt-4">
            Lihat Spesifikasi & Pesan
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { api, idr, resolveImageUrl } from '../services/api';

const meta = ref({ regions: [], categories: [] });
const items = ref([]);
const loading = ref(true);

const filters = ref({
  q: '',
  regionId: '',
  categoryId: '',
  grade: ''
});

const fetchCatalog = async () => {
  loading.value = true;
  try {
    const res = await api.get('/public/catalog', {
      q: filters.value.q,
      regionId: filters.value.regionId,
      categoryId: filters.value.categoryId,
      grade: filters.value.grade
    });
    items.value = res.items || [];
  } catch (err) {
    console.error(err);
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  try {
    meta.value = await api.get('/public/meta');
    await fetchCatalog();
  } catch (err) {
    console.error(err);
  }
});
</script>

<style scoped>
.catalog-page {
  padding-bottom: 3rem;
}

.catalog-header {
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 2rem;
  font-weight: 800;
}

.page-subtitle {
  color: var(--text-muted);
}

.filter-card {
  margin-bottom: 2rem;
}

.product-card {
  padding: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.product-image-wrap {
  position: relative;
  height: 180px;
  background: #f1f5f9;
}

.product-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.region-tag {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  backdrop-filter: blur(4px);
}

.product-info {
  padding: 1.25rem;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.category-name {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--primary);
  margin-bottom: 0.25rem;
}

.product-name {
  font-size: 1.15rem;
  font-weight: 700;
  line-height: 1.3;
  margin-bottom: 0.75rem;
}

.grades-wrap {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin-bottom: 1rem;
}

.price-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: auto;
  border-top: 1px solid var(--border);
  padding-top: 0.75rem;
}

.price-val {
  font-size: 1.2rem;
  font-weight: 800;
  color: var(--primary-dark);
}

.stock-val {
  font-weight: 700;
  color: var(--text-main);
}

.w-full {
  width: 100%;
}

.mt-4 { margin-top: 1rem; }
.mt-6 { margin-top: 1.5rem; }
.text-xs { font-size: 0.75rem; }
.block { display: block; }
</style>
