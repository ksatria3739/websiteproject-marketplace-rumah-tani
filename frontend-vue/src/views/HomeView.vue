<template>
  <div class="home-view">
    <!-- Hero Section -->
    <section class="hero-section">
      <div class="container hero-container">
        <div class="hero-badge">🌱 Digitalisasi Rantai Pasok Hulu ke Hilir</div>
        <h1 class="hero-title">Ekosistem Marketplace Pertanian Terintegrasi</h1>
        <p class="hero-desc">
          Menghubungkan langsung Petani (Produsen), Tengkulak (Distributor Wilayah), dan Pembeli Korporasi/Umum
          dengan transparansi harga, kepastian grade komoditas, dan pemantauan stok real-time tanpa perantara panjang.
        </p>
        <div class="hero-actions">
          <a href="#katalog-langsung" class="btn btn-primary" style="font-weight: 700;">🌾 Belanja Hasil Panen Sekarang</a>
          <router-link to="/register" class="btn btn-secondary">Bergabung ke Ekosistem</router-link>
        </div>
      </div>
    </section>

    <!-- LIVE COMMODITY CATALOG ON HOMEPAGE -->
    <section id="katalog-langsung" class="container catalog-home-section">
      <div class="section-header" style="flex-direction: column; align-items: flex-start; gap: 0.25rem;">
        <span class="badge badge-green" style="font-size: 0.85rem;">Etalase Langsung dari Lahan Petani</span>
        <div style="display: flex; justify-content: space-between; align-items: flex-end; width: 100%; flex-wrap: wrap; gap: 1rem;">
          <div>
            <h2 class="section-title" style="margin-bottom: 0.25rem;">Katalog Komoditas Pilihan</h2>
            <p class="text-muted" style="font-size: 0.9rem;">
              Harga transparan dari hulu, stok real-time, dan kualitas terstandarisasi Grade A/B/C.
            </p>
          </div>
          <router-link to="/catalog" class="btn btn-secondary btn-sm" style="font-weight: 600;">
            Lihat Katalog Lengkap →
          </router-link>
        </div>
      </div>

      <!-- Search & Filters Bar -->
      <div class="card filter-card" style="margin: 1.25rem 0 2rem 0; padding: 1.25rem; background: #ffffff; border: 1px solid var(--border);">
        <div class="grid grid-4" style="gap: 1rem;">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="label" style="font-size: 0.8rem; font-weight: 600;">Pencarian Komoditas</label>
            <input
              v-model="filters.q"
              @input="fetchCatalog"
              type="text"
              class="input"
              placeholder="Cari cabai, beras, bawang..."
            />
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="label" style="font-size: 0.8rem; font-weight: 600;">Wilayah Asal</label>
            <select v-model="filters.regionId" @change="fetchCatalog" class="select">
              <option value="">Semua Wilayah Lahan</option>
              <option v-for="r in meta.regions" :key="r.id" :value="r.id">
                {{ r.name }} ({{ r.province }})
              </option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="label" style="font-size: 0.8rem; font-weight: 600;">Kategori Hasil Bumi</label>
            <select v-model="filters.categoryId" @change="fetchCatalog" class="select">
              <option value="">Semua Kategori</option>
              <option v-for="c in meta.categories" :key="c.id" :value="c.id">
                {{ c.name }}
              </option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="label" style="font-size: 0.8rem; font-weight: 600;">Klasifikasi Mutu</label>
            <select v-model="filters.grade" @change="fetchCatalog" class="select">
              <option value="">Semua Grade (A/B/C)</option>
              <option value="A">Grade A (Premium Super)</option>
              <option value="B">Grade B (Standar Pasar)</option>
              <option value="C">Grade C (Industri / Olahan)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loadingCatalog" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat komoditas panen segar...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="items.length === 0" class="card text-center" style="padding: 3rem 1rem;">
        <h3 style="font-size: 1.15rem; font-weight: 700;">Belum ada komoditas yang cocok dengan filter</h3>
        <p class="text-muted" style="margin-top: 0.5rem; font-size: 0.9rem;">
          Coba ganti kata kunci pencarian atau bersihkan filter pilihan Anda.
        </p>
        <button class="btn btn-secondary btn-sm" style="margin-top: 1rem;" @click="resetFilters">
          Reset Filter Pencarian
        </button>
      </div>

      <!-- Products Grid -->
      <div v-else class="grid grid-3" style="gap: 1.5rem;">
        <div v-for="item in items" :key="item.id" class="card product-card">
          <div class="product-image-wrap">
            <img
              :src="resolveImageUrl(item.image_url)"
              :alt="item.name"
              class="product-image"
              loading="lazy"
            />
            <span class="badge badge-green region-tag">📍 {{ item.region }}</span>
          </div>

          <div class="product-info">
            <span class="category-name">{{ item.category }}</span>
            <h3 class="product-name">{{ item.name }}</h3>

            <div class="grades-wrap">
              <span class="text-muted text-xs">Pilihan Mutu:</span>
              <span v-for="g in item.grades" :key="g" class="badge badge-blue">Grade {{ g }}</span>
            </div>

            <div class="price-row">
              <div>
                <span class="text-muted text-xs block">Mulai dari</span>
                <span class="price-val">{{ idr(item.price_from) }} <span class="text-xs text-muted">/kg</span></span>
              </div>
              <div class="stock-info">
                <span class="text-muted text-xs block">Stok Tersedia</span>
                <span class="stock-val">{{ item.available }} kg</span>
              </div>
            </div>

            <router-link :to="`/catalog/${item.slug}`" class="btn btn-primary w-full mt-4" style="font-weight: 700;">
              Lihat Detail & Pesan
            </router-link>
          </div>
        </div>
      </div>

      <!-- Explore More Button -->
      <div style="text-align: center; margin-top: 2.5rem;">
        <router-link to="/catalog" class="btn btn-secondary" style="font-weight: 700; padding: 0.75rem 2rem;">
          🌾 Lihat Semua Hasil Panen di Katalog Lengkap →
        </router-link>
      </div>
    </section>

    <!-- 5-Level Architecture Overview -->
    <section class="container roles-section" style="margin-top: 4rem;">
      <h2 class="section-title">Arsitektur Multi-Peran (5-Level RBAC)</h2>
      <div class="grid grid-4">
        <div class="card role-card">
          <div class="role-icon">🧑‍🌾</div>
          <h3>1. Petani (Produsen)</h3>
          <p>Registrasi mandiri, upload foto panen real-time, penentuan grade (A/B/C), dan pembaruan stok real-time bebas over-selling.</p>
        </div>
        <div class="card role-card">
          <div class="role-icon">🚚</div>
          <h3>2. Tengkulak (Distributor)</h3>
          <p>Kurasi komoditas wilayah, manajemen logistik armada angkut, dan kontrol distribusi pesanan berskala besar.</p>
        </div>
        <div class="card role-card">
          <div class="role-icon">🏢</div>
          <h3>3. Pembeli (B2B / B2C)</h3>
          <p>Pemesanan massal multi-vendor, negosiasi harga khusus korporasi, pembayaran praktis via QRIS/VA Tripay, dan pelacakan armada visual.</p>
        </div>
        <div class="card role-card">
          <div class="role-icon">👑</div>
          <h3>4. Super Admin & Support L3</h3>
          <p>Pengendali konfigurasi sistem global, dukungan stok L3 untuk petani, dan manajemen user role lapangan.</p>
        </div>
      </div>
    </section>

    <!-- Recent Articles -->
    <section class="container articles-section" v-if="articles.length">
      <div class="section-header">
        <h2 class="section-title">Kabar & Panduan Pertanian</h2>
        <router-link to="/articles" class="btn btn-secondary btn-sm">Lihat Semua</router-link>
      </div>
      <div class="grid grid-2">
        <div v-for="article in articles" :key="article.slug" class="card article-card">
          <span class="badge badge-green">Edukasi Tani</span>
          <h3 class="article-title">{{ article.title }}</h3>
          <p class="article-excerpt">{{ article.excerpt }}</p>
          <router-link :to="`/articles/${article.slug}`" class="read-link">Baca Selengkapnya →</router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { api, idr, resolveImageUrl } from '../services/api';

const meta = ref({ regions: [], categories: [] });
const items = ref([]);
const loadingCatalog = ref(true);
const articles = ref([]);

const filters = ref({
  q: '',
  regionId: '',
  categoryId: '',
  grade: ''
});

const fetchCatalog = async () => {
  loadingCatalog.value = true;
  try {
    const params = {};
    if (filters.value.q) params.q = filters.value.q;
    if (filters.value.regionId) params.regionId = filters.value.regionId;
    if (filters.value.categoryId) params.categoryId = filters.value.categoryId;
    if (filters.value.grade) params.grade = filters.value.grade;

    const res = await api.get('/public/catalog', params);
    items.value = res.items || [];
  } catch (err) {
    console.error('Failed to load home catalog:', err);
  } finally {
    loadingCatalog.value = false;
  }
};

const resetFilters = () => {
  filters.value = { q: '', regionId: '', categoryId: '', grade: '' };
  fetchCatalog();
};

const loadMetaAndArticles = async () => {
  try {
    const [metaRes, articlesRes] = await Promise.all([
      api.get('/public/meta'),
      api.get('/public/articles')
    ]);
    meta.value = metaRes;
    articles.value = (articlesRes || []).slice(0, 2);
  } catch (err) {
    console.error(err);
  }
};

onMounted(async () => {
  await Promise.all([
    fetchCatalog(),
    loadMetaAndArticles()
  ]);
});
</script>

<style scoped>
.hero-section {
  background: linear-gradient(135deg, #14532d 0%, #15803d 100%);
  color: #ffffff;
  padding: 4.5rem 0;
  border-radius: var(--radius);
  margin-bottom: 3rem;
}

.hero-container {
  text-align: center;
  max-width: 800px;
}

.hero-badge {
  display: inline-block;
  background: rgba(255, 255, 255, 0.2);
  padding: 0.35rem 1rem;
  border-radius: 9999px;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 1.25rem;
  backdrop-filter: blur(4px);
}

.hero-title {
  font-size: 2.75rem;
  font-weight: 800;
  line-height: 1.2;
  margin-bottom: 1.25rem;
}

.hero-desc {
  font-size: 1.1rem;
  opacity: 0.9;
  margin-bottom: 2rem;
  line-height: 1.6;
}

.hero-actions {
  display: flex;
  justify-content: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.catalog-home-section {
  margin-bottom: 3.5rem;
}

.product-card {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 0;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.product-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.product-image-wrap {
  position: relative;
  width: 100%;
  height: 200px;
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
  right: 0.75rem;
  backdrop-filter: blur(4px);
}

.product-info {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.category-name {
  font-size: 0.75rem;
  text-transform: uppercase;
  color: var(--text-muted);
  font-weight: 700;
  letter-spacing: 0.05em;
}

.product-name {
  font-size: 1.15rem;
  font-weight: 700;
  margin: 0.25rem 0 0.5rem 0;
}

.grades-wrap {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.price-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  border-top: 1px solid var(--border);
  padding-top: 0.75rem;
  margin-top: auto;
}

.price-val {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--primary);
}

.stock-info {
  text-align: right;
}

.stock-val {
  font-weight: 700;
  font-size: 1rem;
}

.roles-section {
  margin-bottom: 3.5rem;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 800;
  margin-bottom: 1rem;
}

.role-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.role-card:hover {
  transform: translateY(-4px);
}

.role-icon {
  font-size: 2.5rem;
  margin-bottom: 0.75rem;
}

.role-card h3 {
  font-size: 1.1rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.role-card p {
  font-size: 0.875rem;
  color: var(--text-muted);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.article-title {
  font-size: 1.15rem;
  font-weight: 700;
  margin: 0.75rem 0 0.5rem;
}

.article-excerpt {
  font-size: 0.875rem;
  color: var(--text-muted);
  margin-bottom: 1rem;
}

.read-link {
  color: var(--primary);
  font-weight: 600;
  text-decoration: none;
  font-size: 0.875rem;
}
</style>
