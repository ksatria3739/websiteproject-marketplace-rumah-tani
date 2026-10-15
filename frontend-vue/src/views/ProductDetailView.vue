<template>
  <div class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
    <!-- Breadcrumb / Back button -->
    <div style="margin-bottom: 1.25rem;">
      <router-link to="/catalog" class="btn btn-secondary btn-sm">
        ← Kembali ke Katalog
      </router-link>
    </div>

    <div v-if="loading" style="text-align: center; padding: 4rem 0;">
      <p class="text-muted">Memuat data komoditas...</p>
    </div>

    <div v-else-if="error" class="alert alert-error">
      {{ error }}
    </div>

    <div v-else-if="product" class="grid grid-2" style="gap: 2rem; align-items: start;">
      <!-- Product Media & Origin Info -->
      <div class="card">
        <div style="position: relative; height: 320px; border-radius: var(--radius); overflow: hidden; margin-bottom: 1.5rem; background: #f1f5f9;">
          <img
            :src="resolveImageUrl(product.image_url)"
            :alt="product.name"
            style="width: 100%; height: 100%; object-fit: cover;"
          />
        </div>
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
          <span class="badge badge-green">{{ product.category?.name || product.category_name || product.category }}</span>
          <span class="badge badge-blue">📍 {{ product.region?.name || product.region_name || product.region }}<span v-if="product.region?.province">, {{ product.region.province }}</span></span>
          <span class="badge badge-green">✓ Terkurasi</span>
        </div>
        <h1 style="font-size: 1.75rem; font-weight: 800; margin-bottom: 0.75rem;">
          {{ product.name }}
        </h1>
        <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 1.5rem;">
          {{ product.description || 'Komoditas pertanian segar berkualitas langsung dari lahan petani mitra Rumah Tani.' }}
        </p>

        <div style="background: #f8fafc; padding: 1rem; border-radius: var(--radius); border-left: 4px solid var(--primary);">
          <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">Informasi Petani Produsen:</div>
          <div style="font-size: 0.95rem; font-weight: 600; color: var(--primary);">{{ product.farmer?.name || product.farmer_name || 'Petani Mitra Rumah Tani' }}</div>
          <div style="font-size: 0.8rem; color: var(--text-muted);">{{ product.farmer?.address || 'Lahan Pertanian Terverifikasi' }}</div>
        </div>
      </div>

      <!-- Variants, Pricing & Purchase Actions -->
      <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">
          Pilih Klasifikasi Grade & Kuantitas
        </h2>

        <!-- Alert messages -->
        <div v-if="successMsg" class="alert alert-success" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
          <span>{{ successMsg }}</span>
          <router-link to="/buyer" class="btn btn-primary btn-sm" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
            Buka Keranjang →
          </router-link>
        </div>
        <div v-if="actionError" class="alert alert-error">
          {{ actionError }}
        </div>

        <!-- Grade Selector -->
        <div class="form-group">
          <label class="label">Pilihan Varian / Grade:</label>
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div
              v-for="v in product.variants"
              :key="v.id"
              @click="selectVariant(v)"
              :style="{
                border: selectedVariant?.id === v.id ? '2px solid var(--primary)' : '1px solid var(--border)',
                background: selectedVariant?.id === v.id ? 'var(--primary-light)' : '#ffffff',
                padding: '1rem',
                borderRadius: 'var(--radius)',
                cursor: 'pointer',
                transition: 'all 0.15s ease'
              }"
            >
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                <div>
                  <span class="badge" :class="getGradeBadge(v.grade)" style="margin-right: 0.5rem;">
                    {{ v.grade }}
                  </span>
                  <span style="font-weight: 700; font-size: 1.1rem; color: var(--primary);">
                    {{ idr(v.price) }}
                  </span>
                  <span style="font-size: 0.8rem; color: var(--text-muted);"> / {{ v.unit }}</span>
                </div>
                <div style="text-align: right;">
                  <span
                    :style="{
                      color: (v.stock_qty - (v.reserved_qty || 0)) > 0 ? '#166534' : '#991b1b',
                      fontWeight: '700',
                      fontSize: '0.9rem'
                    }"
                  >
                    Stok: {{ v.stock_qty - (v.reserved_qty || 0) }} {{ v.unit }}
                  </span>
                </div>
              </div>
              <p v-if="v.min_order > 1" style="font-size: 0.8rem; color: var(--accent); margin: 0 0 0.25rem 0; font-weight: 600;">
                Min. Pemesanan: {{ v.min_order }} {{ v.unit }}
              </p>
              <p v-if="v.notes" style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                {{ v.notes }}
              </p>
            </div>
          </div>
        </div>

        <!-- Quantity input -->
        <div v-if="selectedVariant" class="form-group" style="margin-top: 1.5rem;">
          <label class="label">
            Jumlah Pesanan ({{ selectedVariant.unit }}):
            <span v-if="selectedVariant.min_order > 1" style="color: var(--accent); font-weight: normal; font-size: 0.85rem; margin-left: 0.35rem;">
              (Minimal: {{ selectedVariant.min_order }} {{ selectedVariant.unit }})
            </span>
          </label>
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <input
              v-model.number="quantity"
              type="number"
              :min="selectedVariant.min_order || 1"
              :max="selectedVariant.stock_qty - (selectedVariant.reserved_qty || 0)"
              class="input"
              style="width: 140px; font-weight: 700; font-size: 1.1rem; text-align: center;"
            />
            <span class="text-muted" style="font-size: 0.9rem;">
              Total estimasi: <strong>{{ idr(quantity * (selectedVariant?.price || 0)) }}</strong>
            </span>
          </div>
        </div>

        <!-- Role Action Area -->
        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
          <!-- 1. Guest -->
          <div v-if="!auth.isAuthenticated" style="text-align: center; background: #f8fafc; padding: 1.25rem; border-radius: var(--radius);">
            <p style="font-size: 0.9rem; margin-bottom: 0.75rem; color: var(--text-muted);">
              Anda sedang menjelajah sebagai <strong>Tamu (Guest)</strong>. Silakan masuk untuk melakukan pembelian atau negosiasi harga.
            </p>
            <router-link :to="`/login?redirect=/catalog/${product.slug}`" class="btn btn-primary">
              Masuk untuk Beli / Negosiasi
            </router-link>
          </div>

          <!-- 2. Pembeli (B2C & B2B) -->
          <div v-else-if="auth.isPembeli" style="display: flex; flex-direction: column; gap: 0.75rem;">
            <button
              class="btn btn-primary"
              style="width: 100%; font-size: 1rem; padding: 0.75rem;"
              :disabled="actionLoading || !hasAvailableStock"
              @click="addToCart"
            >
              🛒 Tambah ke Keranjang Belanja
            </button>

            <!-- B2B Exclusive Negotiation Trigger -->
            <button
              v-if="auth.isB2B"
              class="btn btn-secondary"
              style="width: 100%; border-color: var(--accent); color: #b45309; font-weight: 700;"
              @click="showNegotiationModal = true"
            >
              🤝 Ajukan Penawaran Harga Khusus (B2B Negotiation)
            </button>
            <p v-if="auth.isB2B" style="font-size: 0.75rem; color: var(--text-muted); text-align: center;">
              Fitur khusus akun Pembeli B2B untuk pembelian volume besar / kontrak pasokan industri.
            </p>
          </div>

          <!-- 3. Seller / Admin Info -->
          <div v-else style="background: #f1f5f9; padding: 1rem; border-radius: var(--radius); text-align: center; font-size: 0.85rem;">
            Anda masuk sebagai <strong>{{ auth.user?.role }}</strong>. Untuk berbelanja atau negosiasi, gunakan akun Pembeli (B2B / B2C).
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Form Negosiasi B2B -->
    <div
      v-if="showNegotiationModal"
      style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 999; padding: 1rem;"
    >
      <div class="card" style="width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3 style="font-size: 1.2rem; font-weight: 700;">Ajukan Negosiasi Harga (B2B)</h3>
          <button @click="showNegotiationModal = false" class="btn btn-secondary btn-sm">✕</button>
        </div>

        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
          Kirimkan tawaran harga volume besar untuk komoditas <strong>{{ product?.name }}</strong> (Grade: {{ selectedVariant?.grade }}).
        </p>

        <form @submit.prevent="submitNegotiation">
          <div class="form-group">
            <label class="label">Harga Penawaran Anda (per {{ selectedVariant?.unit }}):</label>
            <input
              v-model.number="negoForm.offered_price"
              type="number"
              class="input"
              required
              :placeholder="`Harga normal: ${idr(selectedVariant?.price)}`"
            />
          </div>

          <div class="form-group">
            <label class="label">Volume Pembelian ({{ selectedVariant?.unit }}):</label>
            <input
              v-model.number="negoForm.requested_volume"
              type="number"
              class="input"
              required
              min="1"
            />
          </div>

          <div class="form-group">
            <label class="label">Catatan Kebutuhan / Spesifikasi Khusus:</label>
            <textarea
              v-model="negoForm.buyer_note"
              class="textarea"
              rows="3"
              placeholder="Contoh: Jadwal pengiriman berkala setiap Senin, kemasan khusus sak 50kg."
            ></textarea>
          </div>

          <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1rem;">
            <button type="button" @click="showNegotiationModal = false" class="btn btn-secondary">
              Batal
            </button>
            <button type="submit" class="btn btn-primary" :disabled="negoLoading">
              <span v-if="negoLoading">Mengirim Tawaran...</span>
              <span v-else>Kirim Negosiasi</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { api, idr, resolveImageUrl } from '../services/api';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const auth = useAuthStore();

const product = ref(null);
const selectedVariant = ref(null);
const quantity = ref(1);
const loading = ref(true);
const error = ref('');
const actionLoading = ref(false);
const actionError = ref('');
const successMsg = ref('');

// B2B Negotiation
const showNegotiationModal = ref(false);
const negoLoading = ref(false);
const negoForm = reactive({
  offered_price: null,
  requested_volume: 100,
  buyer_note: '',
});

const hasAvailableStock = computed(() => {
  if (!selectedVariant.value) return false;
  const avail = selectedVariant.value.stock_qty - (selectedVariant.value.reserved_qty || 0);
  const minOrder = selectedVariant.value.min_order || 1;
  return avail >= quantity.value && quantity.value >= minOrder;
});

const getGradeBadge = (grade) => {
  if (grade === 'Grade A') return 'badge-green';
  if (grade === 'Grade B') return 'badge-blue';
  return 'badge-amber';
};

const selectVariant = (v) => {
  selectedVariant.value = v;
  const minOrder = v.min_order || 1;
  if (quantity.value < minOrder) {
    quantity.value = minOrder;
  }
  negoForm.offered_price = v.price;
};

const loadProduct = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await api.get(`/public/catalog/${route.params.slug}`);
    product.value = res.commodity || res;
    if (product.value.variants && product.value.variants.length > 0) {
      selectVariant(product.value.variants[0]);
    }
  } catch (err) {
    error.value = err.message || 'Komoditas tidak ditemukan.';
  } finally {
    loading.value = false;
  }
};

const addToCart = async () => {
  if (!selectedVariant.value) return;
  actionLoading.value = true;
  actionError.value = '';
  successMsg.value = '';

  try {
    await api.put('/cart/items', {
      variantId: selectedVariant.value.id,
      variant_id: selectedVariant.value.id,
      qty: quantity.value,
      quantity: quantity.value,
    });
    successMsg.value = `✓ Berhasil menambahkan ${quantity.value} ${selectedVariant.value.unit} ke keranjang belanja!`;
  } catch (err) {
    actionError.value = err.message || 'Gagal menambahkan ke keranjang.';
  } finally {
    actionLoading.value = false;
  }
};

const submitNegotiation = async () => {
  if (!selectedVariant.value) return;
  negoLoading.value = true;
  try {
    await api.post('/negotiations', {
      variantId: selectedVariant.value.id,
      qty: negoForm.requested_volume,
      unitPrice: negoForm.offered_price,
      message: negoForm.buyer_note,
    });
    showNegotiationModal.value = false;
    successMsg.value = 'Negosiasi harga berhasil dikirimkan ke pihak penjual/tengkulak. Pantau status di Workspace Pembeli.';
  } catch (err) {
    alert(err.message || 'Gagal mengirim penawaran negosiasi.');
  } finally {
    negoLoading.value = false;
  }
};

onMounted(loadProduct);
</script>
