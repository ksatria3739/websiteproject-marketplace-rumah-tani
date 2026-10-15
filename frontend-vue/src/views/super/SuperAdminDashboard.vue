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
        class="btn btn-sm"
        :class="activeTab === 'distribution' ? 'btn-primary' : 'btn-secondary'"
        @click="switchTabToDistribution"
      >
        🚛 Pemantauan Distribusi ({{ shipments.length }})
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

        <!-- KYC Filter Pills -->
        <div style="display: flex; gap: 0.4rem; margin-bottom: 1rem; flex-wrap: wrap;">
          <button
            class="btn btn-sm"
            :class="kycFilter === 'all' ? 'btn-primary' : 'btn-secondary'"
            style="font-size: 0.78rem; padding: 0.25rem 0.65rem;"
            @click="kycFilter = 'all'"
          >
            Semua Pengguna ({{ users.length }})
          </button>
          <button
            class="btn btn-sm"
            :class="kycFilter === 'pending' ? 'btn-primary' : 'btn-secondary'"
            style="font-size: 0.78rem; padding: 0.25rem 0.65rem; border: 1px solid #f59e0b;"
            @click="kycFilter = 'pending'"
          >
            ⏳ Menunggu Review KYC ({{ pendingKycUsersCount }})
          </button>
          <button
            class="btn btn-sm"
            :class="kycFilter === 'approved' ? 'btn-primary' : 'btn-secondary'"
            style="font-size: 0.78rem; padding: 0.25rem 0.65rem;"
            @click="kycFilter = 'approved'"
          >
            ✓ Terverifikasi ({{ approvedKycUsersCount }})
          </button>
          <button
            class="btn btn-sm"
            :class="kycFilter === 'rejected' ? 'btn-primary' : 'btn-secondary'"
            style="font-size: 0.78rem; padding: 0.25rem 0.65rem;"
            @click="kycFilter = 'rejected'"
          >
            ✕ Ditolak ({{ rejectedKycUsersCount }})
          </button>
        </div>

        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Pengguna</th>
                <th style="min-width: 170px;">Peran (Role) & Ubah Role</th>
                <th>Wilayah</th>
                <th>Status KYC & Berkas</th>
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
                  <div>
                    <span
                      class="badge"
                      :class="{
                        'badge-green': u.kyc_status === 'approved' || u.kyc_status === 'verified',
                        'badge-amber': u.kyc_status === 'pending',
                        'badge-red': u.kyc_status === 'rejected',
                        'badge-slate': !u.kyc_status || u.kyc_status === 'unsubmitted'
                      }"
                    >
                      {{
                        u.kyc_status === 'approved' || u.kyc_status === 'verified'
                          ? '✓ Terverifikasi'
                          : (u.kyc_status === 'pending'
                              ? '⏳ Menunggu Validasi'
                              : (u.kyc_status === 'rejected' ? '✕ Ditolak' : 'Belum Mengajukan'))
                      }}
                    </span>
                  </div>
                  <!-- Uploaded documents pill -->
                  <div v-if="u.docs && u.docs.length > 0" style="margin-top: 0.35rem;">
                    <button
                      class="btn btn-secondary btn-sm"
                      style="font-size: 0.72rem; padding: 0.15rem 0.5rem; display: inline-flex; align-items: center; gap: 0.25rem; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;"
                      @click="openKycReviewModal(u)"
                    >
                      📄 {{ u.docs.length }} Berkas Digital
                    </button>
                  </div>
                  <div v-if="u.kyc_note && u.kyc_status === 'rejected'" style="font-size: 0.7rem; color: #ef4444; margin-top: 0.2rem;">
                    Alasan: {{ u.kyc_note }}
                  </div>
                </td>
                <td>
                  <span class="badge" :class="u.is_active ? 'badge-green' : 'badge-red'">
                    {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td>
                  <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                    <!-- If user has docs or pending, show Review button -->
                    <button
                      v-if="(u.docs && u.docs.length > 0) || u.kyc_status === 'pending'"
                      class="btn btn-primary btn-sm"
                      style="font-size: 0.75rem; padding: 0.2rem 0.55rem; background: #0284c7; border-color: #0284c7;"
                      @click="openKycReviewModal(u)"
                    >
                      🔍 Tinjau Berkas KYC
                    </button>
                    <!-- Fallback quick verify button if not approved and no docs -->
                    <button
                      v-else-if="u.kyc_status !== 'approved' && u.kyc_status !== 'verified'"
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

    <!-- TAB 5: PEMANTAUAN DISTRIBUSI & LOGISTIK -->
    <div v-if="activeTab === 'distribution'">
      <!-- Stats Summary -->
      <div class="grid grid-4" style="margin-bottom: 1.5rem; gap: 1rem;">
        <div class="card" style="border-left: 4px solid #3b82f6;">
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Total Pengiriman</div>
          <div style="font-size: 1.5rem; font-weight: 800; color: #1e3a8a; margin-top: 0.25rem;">
            {{ shipments.length }}
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Seluruh rute nasional</div>
        </div>

        <div class="card" style="border-left: 4px solid #f59e0b;">
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Dalam Perjalanan</div>
          <div style="font-size: 1.5rem; font-weight: 800; color: #b45309; margin-top: 0.25rem;">
            {{ activeShipmentsCount }}
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">In transit & armada jalan</div>
        </div>

        <div class="card" style="border-left: 4px solid #10b981;">
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Tiba / Selesai</div>
          <div style="font-size: 1.5rem; font-weight: 800; color: #047857; margin-top: 0.25rem;">
            {{ deliveredShipmentsCount }}
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Diterima pemesan</div>
        </div>

        <div class="card" style="border-left: 4px solid #8b5cf6;">
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Armada Terdaftar</div>
          <div style="font-size: 1.5rem; font-weight: 800; color: #6d28d9; margin-top: 0.25rem;">
            {{ vehicles.length }}
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Truk & armada aktif</div>
        </div>
      </div>

      <!-- Controls & Filter -->
      <div class="card" style="margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
          <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; flex: 1;">
            <input
              v-model="shipmentSearch"
              class="input input-sm"
              style="max-width: 280px;"
              placeholder="Cari resi, wilayah, atau supir..."
            />
            <select v-model="shipmentFilterStatus" class="select select-sm" style="max-width: 200px;">
              <option value="">-- Semua Status Logistik --</option>
              <option value="preparing">Preparing (Menyiapkan)</option>
              <option value="picked_up">Picked Up (Diambil)</option>
              <option value="in_transit">In Transit (Perjalanan)</option>
              <option value="arrived_hub">Arrived Hub (Tiba di Hub)</option>
              <option value="out_for_delivery">Out for Delivery (Kurir Jalan)</option>
              <option value="delivered">Delivered (Telah Sampai)</option>
            </select>
          </div>

          <div style="display: flex; gap: 0.5rem;">
            <button class="btn btn-secondary btn-sm" @click="loadShipments">
              🔄 Segarkan Data
            </button>
          </div>
        </div>
      </div>

      <!-- Shipments List -->
      <div v-if="loadingShipments" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat data logistik distribusi nasional...</p>
      </div>

      <div v-else-if="filteredShipments.length === 0" class="card" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Tidak ada data pengiriman yang sesuai filter.</p>
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
        <div v-for="s in filteredShipments" :key="s.id" class="card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem;">
            <div>
              <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <span class="badge" :class="getShipmentBadge(s.status)">
                  {{ formatShipmentStatus(s.status) }}
                </span>
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                  Order: <strong>{{ s.order_code }}</strong>
                </span>
              </div>
              <h3 style="font-size: 1.15rem; font-weight: 700; margin-top: 0.35rem;">
                Resi: <span style="font-family: monospace; color: #1e40af;">{{ s.tracking_code }}</span>
              </h3>
              <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                📍 Rute: <strong>{{ s.origin || s.origin_region?.name || '-' }}</strong> ➔ <strong>{{ s.destination || s.destination_region?.name || '-' }}</strong> • Muatan: <strong>{{ s.actual_weight_kg || s.total_weight_kg || 0 }} kg</strong>
              </p>
            </div>

            <div style="text-align: right;">
              <div v-if="s.vehicle || (s.plate && s.plate !== '-')" style="font-size: 0.85rem; font-weight: 600;">
                🚛 {{ s.vehicle?.plate || s.vehicle?.plate_number || s.plate }} ({{ s.vehicle?.type || s.vehicle?.model || 'Armada Angkut' }})
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                  Pengemudi: <strong>{{ s.vehicle?.driver_name || s.driver_name || '-' }}</strong>
                </div>
              </div>
              <div v-else style="font-size: 0.85rem; color: #dc2626; font-weight: 600;">
                ⚠️ Belum Ditugaskan Armada
              </div>
            </div>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted);">
              Terakhir Diperbarui: {{ formatDateTime(s.updated_at) }}
            </div>
            <div style="display: flex; gap: 0.5rem;">
              <button
                class="btn btn-secondary btn-sm"
                @click="openShipmentStatusModal(s)"
              >
                ✏️ Update Status Logistik
              </button>
              <router-link :to="`/track/${s.tracking_code}`" class="btn btn-primary btn-sm">
                🗺️ Pantau di Peta →
              </router-link>
            </div>
          </div>
        </div>
      </div>

      <!-- Fleet Overview Section -->
      <div class="card" style="margin-top: 1.5rem;">
        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.75rem;">
          🚛 Armada Kendaraan Distribusi Terdaftar
        </h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
          Seluruh kendaraan operasional logistik di bawah manajemen hub regional dan pusat.
        </p>

        <div v-if="vehicles.length === 0" style="text-align: center; padding: 2rem 0; color: var(--text-muted);">
          Belum ada armada yang terdaftar.
        </div>

        <div v-else class="grid grid-3" style="gap: 1rem;">
          <div v-for="v in vehicles" :key="v.id" class="card" style="background: #f8fafc; border: 1px solid var(--border);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span style="font-weight: 700; font-size: 1rem; color: #1e3a8a;">{{ v.plate }}</span>
              <span class="badge" :class="v.is_available ? 'badge-green' : 'badge-amber'">
                {{ v.is_available ? 'Tersedia' : 'Dalam Tugas' }}
              </span>
            </div>
            <p style="font-size: 0.85rem; margin-bottom: 0.25rem;">
              Tipe: <strong>{{ v.type }}</strong>
            </p>
            <p style="font-size: 0.85rem; margin-bottom: 0.25rem;">
              Kapasitas: <strong>{{ v.capacity_kg }} kg</strong>
            </p>
            <p style="font-size: 0.8rem; color: var(--text-muted);">
              Pengemudi: {{ v.driver_name || 'Belum Ditugaskan' }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Update Status Pengiriman (Admin & Super Admin) -->
    <div
      v-if="modalShipmentStatus.show"
      style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem;"
    >
      <div class="card" style="width: 100%; max-width: 480px; background: #ffffff;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
          <h3 style="font-size: 1.15rem; font-weight: 800; color: #1e40af; margin: 0;">
            🚚 Update Status Pengiriman
          </h3>
          <button class="btn btn-sm btn-secondary" @click="modalShipmentStatus.show = false">✕</button>
        </div>

        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
          Perbarui status perjalanan resi <strong>{{ modalShipmentStatus.shipment?.tracking_code }}</strong> secara real-time.
        </p>

        <form @submit.prevent="submitShipmentStatusUpdate">
          <div class="form-group">
            <label class="label">Status Baru</label>
            <select v-model="modalShipmentStatus.status" class="select" required>
              <option value="preparing">Preparing (Pengemasan di Petani/Hub)</option>
              <option value="picked_up">Picked Up (Armada Menjemput Muatan)</option>
              <option value="in_transit">In Transit (Perjalanan di Jalan Raya)</option>
              <option value="arrived_hub">Arrived Hub (Tiba di Hub Transit Kota)</option>
              <option value="out_for_delivery">Out for Delivery (Kurir Menuju Alamat)</option>
              <option value="delivered">Delivered (Telah Diterima Pembeli)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="label">Catatan Perjalanan / Titik Posisi</label>
            <input
              v-model="modalShipmentStatus.note"
              type="text"
              class="input"
              placeholder="Contoh: Melewati Gerbang Tol Pasteur, cuaca lancar"
            />
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.25rem;">
            <button type="button" class="btn btn-secondary" @click="modalShipmentStatus.show = false">
              Batal
            </button>
            <button type="submit" class="btn btn-primary" :disabled="isUpdatingShipment">
              {{ isUpdatingShipment ? 'Menyimpan...' : 'Perbarui Status' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Peninjauan Berkas KYC Pengguna (Admin & Super Admin) -->
    <div
      v-if="showKycReviewModal && selectedKycUser"
      style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 10000; padding: 1rem;"
    >
      <div class="card" style="width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; background: #ffffff; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; margin-bottom: 1rem;">
          <div>
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #1e3a8a; margin: 0;">
              🔍 Peninjauan Dokumen KYC Petani / Mitra
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; margin-bottom: 0;">
              Tinjau keabsahan berkas identitas & legalitas sebelum menyetujui akun untuk berjualan di katalog Rumah Tani.
            </p>
          </div>
          <button class="btn btn-sm btn-secondary" style="font-size: 1rem; line-height: 1;" @click="closeKycReviewModal">✕</button>
        </div>

        <!-- User Information Card -->
        <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 0.85rem; margin-bottom: 1.25rem;">
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; font-size: 0.85rem;">
            <div>
              <span style="color: var(--text-muted); font-size: 0.75rem;">Nama Lengkap:</span>
              <div style="font-weight: 700; font-size: 0.95rem;">{{ selectedKycUser.full_name || selectedKycUser.name }}</div>
            </div>
            <div>
              <span style="color: var(--text-muted); font-size: 0.75rem;">Email & Kontak:</span>
              <div style="font-weight: 600;">{{ selectedKycUser.email }}</div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">{{ selectedKycUser.phone || '-' }}</div>
            </div>
            <div>
              <span style="color: var(--text-muted); font-size: 0.75rem;">Peran & Wilayah:</span>
              <div style="font-weight: 600;">
                <span class="badge badge-primary" style="font-size: 0.75rem; text-transform: uppercase;">{{ selectedKycUser.role }}</span>
                {{ selectedKycUser.region?.name || selectedKycUser.region || '-' }}
              </div>
            </div>
            <div>
              <span style="color: var(--text-muted); font-size: 0.75rem;">Status KYC Saat Ini:</span>
              <div>
                <span
                  class="badge"
                  :class="{
                    'badge-green': selectedKycUser.kyc_status === 'approved' || selectedKycUser.kyc_status === 'verified',
                    'badge-amber': selectedKycUser.kyc_status === 'pending',
                    'badge-red': selectedKycUser.kyc_status === 'rejected',
                    'badge-slate': !selectedKycUser.kyc_status || selectedKycUser.kyc_status === 'unsubmitted'
                  }"
                  style="font-size: 0.85rem;"
                >
                  {{ selectedKycUser.kyc_status || 'unsubmitted' }}
                </span>
              </div>
            </div>
          </div>
          <div v-if="selectedKycUser.address" style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-muted); border-top: 1px dashed var(--border); padding-top: 0.4rem;">
            📍 Alamat Lahan/Domisili: {{ selectedKycUser.address }}
          </div>
        </div>

        <!-- Documents List -->
        <div style="margin-bottom: 1.25rem;">
          <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; color: #0f172a;">
            Berkas Dokumen Terlampir ({{ selectedKycUser.docs?.length || 0 }})
          </h4>

          <div v-if="!selectedKycUser.docs || selectedKycUser.docs.length === 0" style="text-align: center; padding: 2.5rem 1rem; background: #f8fafc; border: 1px dashed var(--border); border-radius: var(--radius);">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📁</div>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">
              Pengguna ini belum mengunggah dokumen digital KYC.
            </p>
            <p style="font-size: 0.8rem; color: #94a3b8;">
              Anda dapat memberikan verifikasi darurat jika konfirmasi keaslian identitas telah divalidasi.
            </p>
          </div>

          <div v-else style="display: flex; flex-direction: column; gap: 1rem;">
            <div
              v-for="(doc, idx) in selectedKycUser.docs"
              :key="doc.id || idx"
              style="border: 1px solid var(--border); border-radius: var(--radius); padding: 0.85rem; background: #f8fafc;"
            >
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <span class="badge badge-primary" style="font-size: 0.8rem; text-transform: uppercase;">
                    {{ doc.type || 'Dokumen Identitas' }}
                  </span>
                  <span style="font-size: 0.75rem; color: var(--text-muted);">
                    {{ doc.created_at ? formatDate(doc.created_at) : 'Diunggah Petani' }}
                  </span>
                </div>
                <a
                  :href="resolveImageUrl(doc.file || doc.url)"
                  target="_blank"
                  class="btn btn-secondary btn-sm"
                  style="font-size: 0.75rem; padding: 0.2rem 0.6rem; text-decoration: none;"
                >
                  ↗ Buka Ukuran Asli (Tab Baru)
                </a>
              </div>

              <!-- Media Preview -->
              <div style="text-align: center; background: #0f172a; border-radius: 8px; padding: 0.5rem; overflow: hidden; min-height: 180px; display: flex; align-items: center; justify-content: center;">
                <template v-if="isPdfDoc(doc.file || doc.url)">
                  <div style="padding: 2rem; color: #ffffff;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;">📄</div>
                    <div style="font-weight: 600; margin-bottom: 0.75rem; font-size: 0.95rem;">Dokumen Berformat PDF</div>
                    <a
                      :href="resolveImageUrl(doc.file || doc.url)"
                      target="_blank"
                      class="btn btn-primary btn-sm"
                    >
                      Buka & Unduh PDF
                    </a>
                  </div>
                </template>
                <template v-else>
                  <img
                    :src="resolveImageUrl(doc.file || doc.url)"
                    :alt="'Berkas KYC ' + (doc.type || 'Petani')"
                    style="max-width: 100%; max-height: 420px; object-fit: contain; border-radius: 4px; display: block; margin: 0 auto; cursor: zoom-in;"
                    @click="openFullscreenImage(resolveImageUrl(doc.file || doc.url))"
                    title="Klik untuk membuka ukuran asli di tab baru"
                  />
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Verification Notes -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
          <label class="label" style="font-weight: 700;">Catatan Verifikasi / Alasan Penolakan</label>
          <textarea
            v-model="kycReviewNote"
            rows="2"
            class="textarea"
            placeholder="Tuliskan catatan verifikasi (misal: 'Foto KTP jelas dan legalitas lahan valid' atau 'Foto KTP buram, mohon upload ulang')..."
          ></textarea>
        </div>

        <!-- Action Footer -->
        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap;">
          <button type="button" class="btn btn-secondary" :disabled="submittingKycDecision" @click="closeKycReviewModal">
            Tutup
          </button>
          <button
            type="button"
            class="btn btn-danger"
            :disabled="submittingKycDecision"
            @click="handleKycDecision(selectedKycUser.id, 'reject')"
          >
            ✕ Tolak Berkas KYC
          </button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="submittingKycDecision"
            @click="handleKycDecision(selectedKycUser.id, 'approve')"
          >
            {{ submittingKycDecision ? 'Menyimpan Keputusan...' : '✓ Setujui KYC Petani' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { api, idr, resolveImageUrl } from '../../services/api';

const auth = useAuthStore();

const activeTab = ref('analytics');
const analytics = ref({});
const users = ref([]);
const userSearch = ref('');
const kycFilter = ref('all');
const showKycReviewModal = ref(false);
const selectedKycUser = ref(null);
const kycReviewNote = ref('');
const submittingKycDecision = ref(false);

const pendingKycUsersCount = computed(() => {
  return users.value.filter((u) => u.kyc_status === 'pending').length;
});

const approvedKycUsersCount = computed(() => {
  return users.value.filter((u) => u.kyc_status === 'approved' || u.kyc_status === 'verified').length;
});

const rejectedKycUsersCount = computed(() => {
  return users.value.filter((u) => u.kyc_status === 'rejected').length;
});
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

// Distribution & Logistics Monitoring State
const shipments = ref([]);
const vehicles = ref([]);
const loadingShipments = ref(false);
const shipmentSearch = ref('');
const shipmentFilterStatus = ref('');
const isUpdatingShipment = ref(false);

const modalShipmentStatus = reactive({
  show: false,
  shipment: null,
  status: 'in_transit',
  note: '',
});

const activeShipmentsCount = computed(() => {
  return shipments.value.filter((s) =>
    ['preparing', 'picked_up', 'in_transit', 'arrived_hub', 'out_for_delivery'].includes(s.status)
  ).length;
});

const deliveredShipmentsCount = computed(() => {
  return shipments.value.filter((s) => s.status === 'delivered').length;
});

const filteredShipments = computed(() => {
  let list = shipments.value;
  if (shipmentFilterStatus.value) {
    list = list.filter((s) => s.status === shipmentFilterStatus.value);
  }
  if (shipmentSearch.value) {
    const q = shipmentSearch.value.toLowerCase();
    list = list.filter((s) =>
      (s.tracking_code && s.tracking_code.toLowerCase().includes(q)) ||
      (s.order_code && s.order_code.toLowerCase().includes(q)) ||
      (s.origin && s.origin.toLowerCase().includes(q)) ||
      (s.destination && s.destination.toLowerCase().includes(q)) ||
      (s.driver_name && s.driver_name.toLowerCase().includes(q)) ||
      (s.plate && s.plate.toLowerCase().includes(q))
    );
  }
  return list;
});

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
  let list = users.value;
  if (kycFilter.value !== 'all') {
    list = list.filter((u) => {
      const st = u.kyc_status || 'unsubmitted';
      if (kycFilter.value === 'pending') return st === 'pending';
      if (kycFilter.value === 'approved') return st === 'approved' || st === 'verified';
      if (kycFilter.value === 'rejected') return st === 'rejected';
      if (kycFilter.value === 'unsubmitted') return !st || st === 'unsubmitted';
      return true;
    });
  }
  if (!userSearch.value) return list;
  const q = userSearch.value.toLowerCase();
  return list.filter(
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

const openKycReviewModal = (user) => {
  selectedKycUser.value = user;
  kycReviewNote.value = user.kyc_note || '';
  showKycReviewModal.value = true;
};

const closeKycReviewModal = () => {
  showKycReviewModal.value = false;
  selectedKycUser.value = null;
  kycReviewNote.value = '';
};

const isPdfDoc = (file) => {
  if (!file) return false;
  return file.toLowerCase().includes('.pdf');
};

const openFullscreenImage = (url) => {
  if (url) window.open(url, '_blank');
};

const handleKycDecision = async (userId, decision) => {
  try {
    submittingKycDecision.value = true;
    errorMessage.value = '';
    const isApprove = decision === 'approve';
    const note = kycReviewNote.value.trim() || (isApprove ? 'Verifikasi KYC disetujui Administrator' : 'Dokumen belum memenuhi kriteria validasi');

    await api.post(`/kyc/${userId}/review`, {
      decision: isApprove ? 'approve' : 'reject',
      note: note,
    });

    noticeMessage.value = isApprove
      ? `✓ Dokumen KYC untuk ${selectedKycUser.value?.name || selectedKycUser.value?.full_name} berhasil disetujui!`
      : `✕ Berkas KYC ${selectedKycUser.value?.name || selectedKycUser.value?.full_name} telah ditolak.`;

    closeKycReviewModal();
    await loadUsers();
    setTimeout(() => { noticeMessage.value = ''; }, 5000);
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memproses peninjauan KYC.';
    setTimeout(() => { errorMessage.value = ''; }, 5000);
  } finally {
    submittingKycDecision.value = false;
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

const switchTabToDistribution = async () => {
  activeTab.value = 'distribution';
  await loadShipments();
  await loadVehicles();
};

const loadShipments = async () => {
  loadingShipments.value = true;
  try {
    const res = await api.get('/logistics/shipments');
    shipments.value = Array.isArray(res) ? res : [];
  } catch (err) {
    console.error('Failed to load shipments:', err);
  } finally {
    loadingShipments.value = false;
  }
};

const loadVehicles = async () => {
  try {
    const res = await api.get('/logistics/vehicles');
    vehicles.value = Array.isArray(res) ? res : [];
  } catch (err) {
    console.error('Failed to load vehicles:', err);
  }
};

const openShipmentStatusModal = (shipment) => {
  modalShipmentStatus.shipment = shipment;
  const flow = ['preparing', 'picked_up', 'in_transit', 'arrived_hub', 'out_for_delivery', 'delivered'];
  const curIdx = flow.indexOf(shipment.status);
  modalShipmentStatus.status = (curIdx >= 0 && curIdx < flow.length - 1) ? flow[curIdx + 1] : (shipment.status || 'picked_up');
  modalShipmentStatus.note = '';
  modalShipmentStatus.show = true;
};

const submitShipmentStatusUpdate = async () => {
  if (!modalShipmentStatus.shipment) return;
  isUpdatingShipment.value = true;
  noticeMessage.value = '';
  errorMessage.value = '';
  try {
    await api.patch(`/logistics/shipments/${modalShipmentStatus.shipment.id}/status`, {
      status: modalShipmentStatus.status,
      note: modalShipmentStatus.note || `Status diperbarui menjadi ${modalShipmentStatus.status} oleh Administrator`,
    });
    modalShipmentStatus.show = false;
    noticeMessage.value = `Status pengiriman resi ${modalShipmentStatus.shipment.tracking_code} berhasil diperbarui!`;
    await loadShipments();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memperbarui status pengiriman.';
  } finally {
    isUpdatingShipment.value = false;
  }
};

const formatShipmentStatus = (status) => {
  switch (status) {
    case 'preparing': return 'Menyiapkan';
    case 'picked_up': return 'Diambil Armada';
    case 'in_transit': return 'Dalam Perjalanan';
    case 'arrived_hub': return 'Tiba di Hub Transit';
    case 'out_for_delivery': return 'Kurir Menuju Alamat';
    case 'delivered': return 'Telah Sampai';
    default: return status;
  }
};

const getShipmentBadge = (status) => {
  switch (status) {
    case 'delivered': return 'badge-green';
    case 'in_transit':
    case 'out_for_delivery': return 'badge-blue';
    case 'picked_up':
    case 'arrived_hub': return 'badge-amber';
    default: return 'badge-gray';
  }
};

const formatDateTime = (iso) => {
  if (!iso) return '-';
  return new Date(iso).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

onMounted(() => {
  loadAnalytics();
  loadUsers();
  loadAllCommodities();
  loadShipments();
  loadVehicles();
  if (auth.isSuperAdmin) {
    loadSettings();
  }
});
</script>
