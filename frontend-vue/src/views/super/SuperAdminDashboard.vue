<template>
  <div class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
    <!-- Welcome Header -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span
            style="font-size: 0.8rem; padding: 0.2rem 0.6rem; border-radius: 9999px; text-transform: uppercase; font-weight: 700;"
            :style="{ background: auth.isSuperAdmin ? 'rgba(239,68,68,0.3)' : 'rgba(59,130,246,0.3)', color: auth.isSuperAdmin ? '#fca5a5' : '#93c5fd' }"
          >
            {{ auth.isSuperAdmin ? 'Super Admin Control Center' : 'Admin Support L3 Operations' }}
          </span>
          <h1 style="font-size: 1.6rem; font-weight: 800; margin-top: 0.35rem;">
            {{ auth.isSuperAdmin ? 'Dashboard Eksekutif & Manajemen Sistem' : 'Pusat Operasional & Dukungan Petani' }}
          </h1>
          <p style="font-size: 0.85rem; color: #94a3b8;">
            {{ auth.isSuperAdmin
              ? 'Kendali master ekosistem Rumah Tani: Pemantauan GMV, kurasi KYC, manajemen pengguna 5-RBAC global, dan audit transaksi.'
              : 'Dukungan L3 operasional: Manajemen peran pengguna, penyesuaian stok panen petani darurat, dan audit logistik.' }}
          </p>
        </div>

        <div v-if="auth.isSuperAdmin">
          <button class="btn btn-secondary btn-sm" @click="exportReport('csv')">
            📥 Ekspor Laporan Finansial (CSV)
          </button>
        </div>
      </div>
    </div>

    <!-- Notices -->
    <div v-if="noticeMessage" class="alert alert-success">
      {{ noticeMessage }}
    </div>
    <div v-if="errorMessage" class="alert alert-error">
      {{ errorMessage }}
    </div>

    <!-- Navigation Tabs -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; flex-wrap: wrap;">
      <button
        class="btn btn-sm"
        :class="activeTab === 'analytics' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'analytics'"
      >
        📊 Analitik & Finansial
      </button>
      <button
        class="btn btn-sm"
        :class="activeTab === 'users' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'users'"
      >
        👥 Manajemen Pengguna & RBAC ({{ users.length }})
      </button>
      <button
        class="btn btn-sm"
        :class="activeTab === 'stock-support' ? 'btn-primary' : 'btn-secondary'"
        @click="switchTabToStockSupport"
      >
        🌾 Dukungan Stok L3 ({{ allCommodities.length }})
      </button>
      <button
        v-if="auth.isSuperAdmin"
        class="btn btn-sm"
        :class="activeTab === 'settings' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'settings'"
      >
        ⚙️ Konfigurasi Sistem Global
      </button>
    </div>

    <!-- TAB 1: ANALYTICS & FINANCIAL OVERVIEW -->
    <div v-if="activeTab === 'analytics'">
      <div v-if="loadingAnalytics" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat ringkasan performa sistem...</p>
      </div>

      <div v-else>
        <!-- Metric Cards -->
        <div class="grid grid-4" style="margin-bottom: 1.5rem;">
          <div class="card" style="border-left: 4px solid #15803d;">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
              Total GMV Transaksi
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #15803d; margin-top: 0.35rem;">
              {{ idr(analytics.profit?.gross_sales || 0) }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
              Volume kotor hasil panen
            </div>
          </div>

          <div class="card" style="border-left: 4px solid #2563eb;">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
              Pendapatan Aplikasi (2%)
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #2563eb; margin-top: 0.35rem;">
              {{ idr(analytics.profit?.revenue || 0) }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
              Platform admin fee
            </div>
          </div>

          <div class="card" style="border-left: 4px solid #f59e0b;">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
              Total Transaksi Pesanan
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #d97706; margin-top: 0.35rem;">
              {{ analytics.profit?.orders || 0 }} Pesanan
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
              B2B massal & B2C retail
            </div>
          </div>

          <div class="card" style="border-left: 4px solid #6366f1;">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
              Total Komoditas Unggulan
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #6366f1; margin-top: 0.35rem;">
              {{ (analytics.top || []).length }} Komoditas
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
              Perputaran aktif sistem
            </div>
          </div>
        </div>

        <!-- Top Turnover Commodities & Revenue Breakdown -->
        <div class="grid grid-2" style="gap: 1.5rem;">
          <div class="card">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">
              🔥 Komoditas Perputaran Tertinggi
            </h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Komoditas</th>
                    <th>Volume Terjual</th>
                    <th>Perputaran Nilai</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in analytics.top || []" :key="item.variant_id || item.name">
                    <td style="font-weight: 600;">{{ item.name }}</td>
                    <td>{{ item.total_qty || item.volume || 0 }} kg</td>
                    <td style="font-weight: 700; color: var(--primary);">{{ idr(item.total_sales || item.value || 0) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">
              📈 Rasio Laba-Rugi Operasional
            </h3>
            <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9rem;">
              <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                <span class="text-muted">Bruto Pendapatan Admin:</span>
                <span style="font-weight: 700; color: #15803d;">{{ idr(analytics.profit?.admin_fee_revenue || 0) }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                <span class="text-muted">Estimasi Biaya Server & Infrastruktur:</span>
                <span style="font-weight: 700; color: #dc2626;">- {{ idr(1500000) }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                <span class="text-muted">Biaya Operasional Verifikasi Lapangan:</span>
                <span style="font-weight: 700; color: #dc2626;">- {{ idr(2500000) }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 1.1rem; padding-top: 0.5rem;">
                <span>Net Margin Operasional:</span>
                <span :style="{ color: ((analytics.profit?.revenue || 0) - 4000000) >= 0 ? '#15803d' : '#dc2626' }">
                  {{ idr((analytics.profit?.revenue || 0) - 4000000) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: USER MANAGEMENT & ROLE-BASED ACCESS CONTROL (RBAC) -->
    <div v-if="activeTab === 'users'">
      <div v-if="loadingUsers" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat daftar pengguna...</p>
      </div>

      <div v-else class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 700;">
              Manajemen Pengguna & Pengaturan Peran (RBAC)
            </h3>
            <p style="font-size: 0.8rem; color: var(--text-muted);">
              {{ auth.isSuperAdmin
                ? 'Super Admin memiliki wewenang global untuk mengatur seluruh peran akun (Super Admin, Admin Support, Tengkulak, Petani, Pembeli).'
                : 'Admin Support L3 dapat mengelola peran Petani, Tengkulak, dan Pembeli.' }}
            </p>
          </div>
          <input
            v-model="userSearch"
            type="text"
            class="input"
            style="width: 250px; font-size: 0.85rem;"
            placeholder="Cari nama atau email..."
          />
        </div>

        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Pengguna</th>
                <th style="min-width: 170px;">Peran (Role) & Ubah Role</th>
                <th>Wilayah</th>
                <th>Status KYC</th>
                <th>Status Akun</th>
                <th>Aksi Verifikasi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in filteredUsers" :key="u.id">
                <td>
                  <div style="font-weight: 600;">{{ u.full_name || u.name }}</div>
                  <div style="font-size: 0.75rem; color: var(--text-muted);">{{ u.email }} • {{ u.phone || '-' }}</div>
                  <div v-if="u.company_name" style="font-size: 0.75rem; color: var(--accent); font-weight: 600;">
                    🏢 {{ u.company_name }}
                  </div>
                </td>
                <td>
                  <!-- Interactive Role Switcher Dropdown -->
                  <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <select
                      :value="u.role"
                      @change="handleRoleChange(u, $event.target.value)"
                      class="select"
                      style="font-size: 0.8rem; padding: 0.25rem 0.5rem; font-weight: 600;"
                      :disabled="!canManageUserRole(u) || roleUpdating === u.id"
                    >
                      <option value="super_admin" :disabled="!auth.isSuperAdmin">🔴 Super Admin</option>
                      <option value="admin" :disabled="!auth.isSuperAdmin">🛡️ Admin Support L3</option>
                      <option value="admin_tengkulak">🔵 Tengkulak</option>
                      <option value="petani">🟢 Petani</option>
                      <option value="pembeli">🟡 Pembeli</option>
                    </select>

                    <span v-if="roleUpdating === u.id" style="font-size: 0.7rem; color: var(--primary);">
                      Menyimpan role...
                    </span>
                    <span v-else-if="u.buyer_type" style="font-size: 0.7rem; color: var(--text-muted);">
                      Tipe: {{ u.buyer_type.toUpperCase() }}
                    </span>
                  </div>
                </td>
                <td>{{ u.region?.name || u.region || '-' }}</td>
                <td>
                  <span class="badge" :class="u.kyc_status === 'approved' || u.kyc_status === 'verified' ? 'badge-green' : 'badge-amber'">
                    {{ u.kyc_status || 'unverified' }}
                  </span>
                </td>
                <td>
                  <span class="badge" :class="u.is_active ? 'badge-green' : 'badge-red'">
                    {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td>
                  <div style="display: flex; gap: 0.35rem;">
                    <button
                      v-if="u.kyc_status !== 'approved' && u.kyc_status !== 'verified'"
                      class="btn btn-primary btn-sm"
                      style="font-size: 0.75rem; padding: 0.2rem 0.5rem;"
                      @click="verifyKyc(u.id)"
                    >
                      ✓ Verifikasi
                    </button>
                    <button
                      class="btn btn-secondary btn-sm"
                      style="font-size: 0.75rem; padding: 0.2rem 0.5rem;"
                      @click="toggleUserActive(u)"
                    >
                      {{ u.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 3: L3 SUPPORT / FARMER STOCK MANAGEMENT -->
    <div v-if="activeTab === 'stock-support'">
      <div class="card" style="margin-bottom: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 700;">
              🛠️ Dukungan Stok Petani (Supporting L3 Operations)
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
              Fitur khusus Administrator untuk membantu petani mengoreksi stok lapangan, audit stok fisik, dan penyesuaian darurat secara atomik.
            </p>
          </div>

          <div style="display: flex; gap: 0.5rem;">
            <input
              v-model="stockSearch"
              type="text"
              class="input"
              style="width: 260px; font-size: 0.85rem;"
              placeholder="Cari komoditas atau petani..."
            />
            <button class="btn btn-secondary btn-sm" @click="loadAllCommodities">
              🔄 Muat Ulang
            </button>
          </div>
        </div>
      </div>

      <div v-if="loadingStock" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat komoditas seluruh petani...</p>
      </div>

      <div v-else-if="filteredCommodities.length === 0" class="card" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Tidak ada komoditas hasil tani ditemukan.</p>
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div v-for="c in filteredCommodities" :key="c.id" class="card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem;">
            <div>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <h4 style="font-size: 1.15rem; font-weight: 700;">{{ c.name }}</h4>
                <span class="badge" :class="c.curation === 'approved' ? 'badge-green' : 'badge-amber'">
                  {{ c.curation }}
                </span>
              </div>
              <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                Petani: <strong style="color: var(--primary);">{{ c.farmer_name || 'Petani Mitra' }}</strong> • Wilayah: {{ c.region }} • Kategori: {{ c.category }}
              </p>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">
              ID Komoditas: #{{ c.id }}
            </div>
          </div>

          <!-- Variants Table with Delta Buttons -->
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Grade Mutu</th>
                  <th>Harga Satuan</th>
                  <th>Stok Fisik Total</th>
                  <th>Terkunci (Reserved)</th>
                  <th>Stok Tersedia</th>
                  <th>Aksi Penyesuaian Stok L3 (+/-)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in c.variants" :key="v.id">
                  <td>
                    <span class="badge" :class="getGradeBadge(v.grade)">{{ v.grade }}</span>
                  </td>
                  <td>{{ idr(v.price) }} / {{ v.unit }}</td>
                  <td style="font-weight: 600;">{{ v.stock_qty }} {{ v.unit }}</td>
                  <td style="color: var(--text-muted);">{{ v.reserved_qty || 0 }} {{ v.unit }}</td>
                  <td>
                    <span style="font-size: 1.1rem; font-weight: 800; color: var(--primary);">
                      {{ v.stock_qty - (v.reserved_qty || 0) }}
                    </span>
                    <span style="font-size: 0.8rem; color: var(--text-muted);"> {{ v.unit }}</span>
                  </td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                      <button
                        class="btn btn-secondary btn-sm"
                        style="padding: 0.2rem 0.5rem; font-weight: 700;"
                        title="Kurangi 50 unit"
                        :disabled="stockUpdating === v.id"
                        @click="adjustAdminStock(v, -50)"
                      >
                        -50
                      </button>
                      <button
                        class="btn btn-secondary btn-sm"
                        style="padding: 0.2rem 0.5rem; font-weight: 700;"
                        title="Kurangi 10 unit"
                        :disabled="stockUpdating === v.id"
                        @click="adjustAdminStock(v, -10)"
                      >
                        -10
                      </button>
                      <button
                        class="btn btn-primary btn-sm"
                        style="padding: 0.2rem 0.5rem; font-weight: 700;"
                        title="Tambah 10 unit"
                        :disabled="stockUpdating === v.id"
                        @click="adjustAdminStock(v, 10)"
                      >
                        +10
                      </button>
                      <button
                        class="btn btn-primary btn-sm"
                        style="padding: 0.2rem 0.5rem; font-weight: 700;"
                        title="Tambah 50 unit"
                        :disabled="stockUpdating === v.id"
                        @click="adjustAdminStock(v, 50)"
                      >
                        +50
                      </button>
                      <button
                        class="btn btn-primary btn-sm"
                        style="padding: 0.2rem 0.5rem; font-weight: 700;"
                        title="Tambah 100 unit"
                        :disabled="stockUpdating === v.id"
                        @click="adjustAdminStock(v, 100)"
                      >
                        +100
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 4: SYSTEM CONFIGURATION (SUPER ADMIN ONLY) -->
    <div v-if="activeTab === 'settings' && auth.isSuperAdmin" class="card" style="max-width: 650px; margin: 0 auto;">
      <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem;">
        Konfigurasi Parameter Finansial & Sistem Global
      </h3>

      <form @submit.prevent="saveSettings">
        <div class="form-group">
          <label class="label">Persentase Biaya Layanan Aplikasi (Admin Fee %):</label>
          <input v-model.number="settings.platform_fee_percent" type="number" step="0.1" class="input" required />
        </div>

        <div class="form-group">
          <label class="label">Persentase Pajak Pertambahan Nilai (PPN %):</label>
          <input v-model.number="settings.ppn_rate" type="number" step="0.1" class="input" required />
        </div>

        <div class="form-group">
          <label class="label">Batas Minimum Kuantitas Pesanan B2B (kg):</label>
          <input v-model.number="settings.min_order_b2b_kg" type="number" class="input" required />
        </div>

        <div class="form-group">
          <label class="label">Masa Kadaluarsa Penawaran Negosiasi (Hari):</label>
          <input v-model.number="settings.negotiation_expiry_days" type="number" class="input" required />
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
          <button type="submit" class="btn btn-primary">
            Simpan Konfigurasi Global
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { api, idr } from '../../services/api';

const auth = useAuthStore();

const activeTab = ref('analytics');
const analytics = ref({});
const users = ref([]);
const userSearch = ref('');
const allCommodities = ref([]);
const stockSearch = ref('');
const loadingStock = ref(false);
const stockUpdating = ref(null);
const roleUpdating = ref(null);

const settings = reactive({
  platform_fee_percent: 2.0,
  ppn_rate: 11.0,
  min_order_b2b_kg: 50,
  negotiation_expiry_days: 3,
});

const loadingAnalytics = ref(true);
const loadingUsers = ref(false);
const noticeMessage = ref('');
const errorMessage = ref('');

const getRoleBadge = (role) => {
  switch (role) {
    case 'super_admin': return 'badge-red';
    case 'admin': return 'badge-blue';
    case 'admin_tengkulak': return 'badge-blue';
    case 'petani': return 'badge-green';
    case 'pembeli': return 'badge-amber';
    default: return 'badge-gray';
  }
};

const getGradeBadge = (grade) => {
  if (grade === 'A' || grade === 'Grade A') return 'badge-green';
  if (grade === 'B' || grade === 'Grade B') return 'badge-blue';
  return 'badge-amber';
};

const filteredUsers = computed(() => {
  if (!userSearch.value) return users.value;
  const q = userSearch.value.toLowerCase();
  return users.value.filter(
    (u) =>
      (u.name && u.name.toLowerCase().includes(q)) ||
      (u.full_name && u.full_name.toLowerCase().includes(q)) ||
      (u.email && u.email.toLowerCase().includes(q)) ||
      (u.company_name && u.company_name.toLowerCase().includes(q))
  );
});

const filteredCommodities = computed(() => {
  if (!stockSearch.value) return allCommodities.value;
  const q = stockSearch.value.toLowerCase();
  return allCommodities.value.filter(
    (c) =>
      c.name.toLowerCase().includes(q) ||
      (c.farmer_name && c.farmer_name.toLowerCase().includes(q)) ||
      (c.region && c.region.toLowerCase().includes(q))
  );
});

const canManageUserRole = (targetUser) => {
  if (auth.isSuperAdmin) {
    return true;
  }
  if (auth.isAdmin) {
    // Admin support cannot change super_admin or admin roles
    return !['super_admin', 'admin'].includes(targetUser.role);
  }
  return false;
};

const handleRoleChange = async (targetUser, newRole) => {
  roleUpdating.value = targetUser.id;
  noticeMessage.value = '';
  errorMessage.value = '';
  try {
    const res = await api.patch(`/admin/users/${targetUser.id}/role`, {
      role: newRole,
    });
    targetUser.role = res.user.role;
    targetUser.buyer_type = res.user.buyer_type;
    noticeMessage.value = `Peran akun ${targetUser.email} berhasil diperbarui menjadi ${newRole.toUpperCase()}!`;
  } catch (err) {
    errorMessage.value = err.message || 'Gagal mengubah peran pengguna.';
  } finally {
    roleUpdating.value = null;
  }
};

const loadAnalytics = async () => {
  loadingAnalytics.value = true;
  try {
    const res = await api.get('/admin/analytics');
    analytics.value = res;
  } catch (err) {
    console.error('Failed to load analytics:', err);
  } finally {
    loadingAnalytics.value = false;
  }
};

const loadUsers = async () => {
  loadingUsers.value = true;
  try {
    const res = await api.get('/admin/users');
    users.value = Array.isArray(res) ? res : (res.users || []);
  } catch (err) {
    console.error('Failed to load users:', err);
  } finally {
    loadingUsers.value = false;
  }
};

const loadAllCommodities = async () => {
  loadingStock.value = true;
  try {
    const res = await api.get('/inventory/commodities');
    allCommodities.value = Array.isArray(res) ? res : (res.commodities || []);
  } catch (err) {
    console.error('Failed to load commodities for L3 support:', err);
  } finally {
    loadingStock.value = false;
  }
};

const switchTabToStockSupport = async () => {
  activeTab.value = 'stock-support';
  if (allCommodities.value.length === 0) {
    await loadAllCommodities();
  }
};

const adjustAdminStock = async (variant, delta) => {
  stockUpdating.value = variant.id;
  noticeMessage.value = '';
  errorMessage.value = '';
  try {
    const res = await api.post(`/inventory/variants/${variant.id}/stock`, {
      delta,
      reason: 'l3_support',
    });
    variant.stock_qty = res.stock_qty;
    variant.reserved_qty = res.reserved_qty;
    variant.stock = res.stock_qty;
    variant.reserved = res.reserved_qty;
    noticeMessage.value = `[L3 Support] Stok ${variant.name || variant.grade} berhasil disesuaikan (${delta > 0 ? '+' : ''}${delta} ${variant.unit}). Stok sekarang: ${res.stock_qty} ${variant.unit}.`;
  } catch (err) {
    errorMessage.value = err.message || 'Gagal menyesuaikan stok komoditas.';
  } finally {
    stockUpdating.value = null;
  }
};

const loadSettings = async () => {
  try {
    const res = await api.get('/admin/settings');
    if (res) {
      Object.assign(settings, res);
    }
  } catch (err) {
    console.error('Failed to load settings:', err);
  }
};

const verifyKyc = async (userId) => {
  try {
    await api.post(`/kyc/${userId}/review`, { decision: 'approve', note: 'Verifikasi KYC disetujui Administrator' });
    noticeMessage.value = 'Dokumen KYC pengguna berhasil divalidasi!';
    await loadUsers();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memverifikasi KYC.';
  }
};

const toggleUserActive = async (user) => {
  try {
    await api.patch(`/admin/users/${user.id}`, { isActive: !user.is_active });
    noticeMessage.value = 'Status aktif pengguna berhasil diperbarui.';
    await loadUsers();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal mengubah status pengguna.';
  }
};

const saveSettings = async () => {
  try {
    for (const [key, val] of Object.entries(settings)) {
      await api.put(`/admin/settings/${key}`, { value: val });
    }
    noticeMessage.value = 'Pengaturan parameter sistem berhasil disimpan.';
  } catch (err) {
    errorMessage.value = err.message || 'Gagal menyimpan pengaturan.';
  }
};

const exportReport = (format) => {
  const token = localStorage.getItem('rt_token');
  window.open(`http://127.0.0.1:8000/api/admin/reports/export?format=${format}&token=${token}`, '_blank');
};

onMounted(() => {
  loadAnalytics();
  loadUsers();
  loadAllCommodities();
  if (auth.isSuperAdmin) {
    loadSettings();
  }
});
</script>
