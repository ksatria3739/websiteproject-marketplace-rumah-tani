<template>
  <div class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
    <!-- Welcome Header & KYC Status -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #15803d 0%, #166534 100%); color: #ffffff;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span style="font-size: 0.8rem; background: rgba(255,255,255,0.2); padding: 0.2rem 0.6rem; border-radius: 9999px; text-transform: uppercase; font-weight: 700;">
            Portal Petani Produsen
          </span>
          <h1 style="font-size: 1.6rem; font-weight: 800; margin-top: 0.35rem;">
            Halo, {{ auth.user?.name }}!
          </h1>
          <p style="font-size: 0.85rem; opacity: 0.9;">
            Kelola hasil panen, pantau persediaan stok real-time, dan tanggapi pesanan dari pembeli.
          </p>
        </div>

        <div style="text-align: right;">
          <div style="font-size: 0.8rem; opacity: 0.85;">Status Verifikasi KYC:</div>
          <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; margin-top: 0.25rem; flex-wrap: wrap;">
            <span
              class="badge"
              :class="{
                'badge-green': auth.user?.kyc_status === 'approved' || auth.user?.kyc_status === 'verified',
                'badge-amber': auth.user?.kyc_status === 'pending',
                'badge-rose': auth.user?.kyc_status === 'rejected',
                'badge-slate': !auth.user?.kyc_status || auth.user?.kyc_status === 'unsubmitted'
              }"
              style="font-size: 0.85rem;"
            >
              <template v-if="auth.user?.kyc_status === 'approved' || auth.user?.kyc_status === 'verified'">
                ✓ Terverifikasi (KYC Valid)
              </template>
              <template v-else-if="auth.user?.kyc_status === 'pending'">
                ⏳ Menunggu Validasi Administrator
              </template>
              <template v-else-if="auth.user?.kyc_status === 'rejected'">
                ✕ Verifikasi Ditolak
              </template>
              <template v-else>
                ⚠️ Belum Mengajukan KYC
              </template>
            </span>
            <button
              v-if="auth.user?.kyc_status !== 'approved' && auth.user?.kyc_status !== 'verified'"
              class="btn btn-sm"
              style="font-size: 0.75rem; padding: 0.2rem 0.6rem; background: rgba(255,255,255,0.25); color: #ffffff; border: 1px solid rgba(255,255,255,0.4);"
              @click="openKycModal = true"
            >
              {{ auth.user?.kyc_status === 'rejected' ? 'Ajukan Ulang' : 'Upload Berkas' }}
            </button>
          </div>
          <div v-if="auth.user?.kyc_note && auth.user?.kyc_status === 'rejected'" style="font-size: 0.75rem; color: #fca5a5; margin-top: 0.25rem;">
            Catatan: {{ auth.user?.kyc_note }}
          </div>
        </div>
      </div>
    </div>

    <!-- Alert / Notice -->
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
        :class="activeTab === 'inventory' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'inventory'"
      >
        🌾 Manajemen Panen & Stok ({{ commodities.length }})
      </button>
      <button
        class="btn btn-sm"
        :class="activeTab === 'orders' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'orders'"
      >
        📦 Pesanan Masuk ({{ orders.length }})
      </button>
      <button
        class="btn btn-sm"
        :class="activeTab === 'negotiations' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'negotiations'"
      >
        🤝 Negosiasi Masuk ({{ negotiations.length }})
      </button>
      <button
        class="btn btn-sm"
        :class="activeTab === 'add-harvest' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'add-harvest'"
      >
        <span v-if="!isKycApproved">🔒 </span>+ Input Panen Baru
      </button>
    </div>

    <!-- TAB 1: INVENTORY & ATOMIC STOCK ADJUSTMENT -->
    <div v-if="activeTab === 'inventory'">
      <div v-if="loadingInventory" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat inventaris kebun...</p>
      </div>

      <div v-else-if="commodities.length === 0" class="card" style="text-align: center; padding: 3rem 1rem;">
        <p style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 1rem;">
          Anda belum menginput komoditas hasil panen.
        </p>
        <button v-if="isKycApproved" class="btn btn-primary" @click="activeTab = 'add-harvest'">
          + Mulai Input Hasil Panen
        </button>
        <button v-else class="btn btn-primary" style="background: #f59e0b; border-color: #d97706;" @click="openKycModal = true">
          🔒 Verifikasi KYC Dahulu Untuk Menambahkan Panen
        </button>
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div v-for="c in commodities" :key="c.id" class="card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
              <div style="width: 64px; height: 64px; border-radius: var(--radius); overflow: hidden; background: #f1f5f9; flex-shrink: 0; border: 1px solid var(--border);">
                <img :src="resolveImageUrl(c.image_url)" :alt="c.name" style="width: 100%; height: 100%; object-fit: cover;" />
              </div>
              <div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <h2 style="font-size: 1.25rem; font-weight: 700;">{{ c.name }}</h2>
                  <span class="badge" :class="getCurationBadge(c.curation || c.curation_status)">
                    {{ c.curation || c.curation_status }}
                  </span>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                  Kategori: {{ c.category?.name || c.category }} • Wilayah: {{ c.region?.name || c.region }}
                </p>
              </div>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">
              Dibuat: {{ formatDate(c.created_at) }}
            </div>
          </div>

          <!-- Variants Table with Delta Buttons -->
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Grade</th>
                  <th>Harga Satuan</th>
                  <th>Stok Tersedia</th>
                  <th>Terpesan / Reserved</th>
                  <th>Aksi Cepat Stok (+/- Real-time)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in c.variants" :key="v.id">
                  <td>
                    <span class="badge" :class="getGradeBadge(v.grade)">{{ v.grade }}</span>
                  </td>
                  <td style="font-weight: 600;">
                    {{ idr(v.price) }} / {{ v.unit }}
                  </td>
                  <td>
                    <span style="font-size: 1.1rem; font-weight: 800; color: var(--primary);">
                      {{ (v.stock ?? v.stock_qty ?? 0) - (v.reserved ?? v.reserved_qty ?? 0) }}
                    </span>
                    <span style="font-size: 0.8rem; color: var(--text-muted);"> {{ v.unit }}</span>
                  </td>
                  <td style="color: var(--text-muted);">
                    {{ v.reserved ?? v.reserved_qty ?? 0 }} {{ v.unit }}
                  </td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                      <button
                        class="btn btn-secondary btn-sm"
                        style="padding: 0.2rem 0.6rem; font-weight: 800;"
                        title="Kurangi 5 unit"
                        :disabled="stockUpdating === v.id"
                        @click="adjustStock(v, 'subtract', 5)"
                      >
                        -5
                      </button>
                      <button
                        class="btn btn-secondary btn-sm"
                        style="padding: 0.2rem 0.6rem; font-weight: 800;"
                        title="Kurangi 1 unit"
                        :disabled="stockUpdating === v.id"
                        @click="adjustStock(v, 'subtract', 1)"
                      >
                        -1
                      </button>
                      <button
                        class="btn btn-primary btn-sm"
                        style="padding: 0.2rem 0.6rem; font-weight: 800;"
                        title="Tambah 1 unit"
                        :disabled="stockUpdating === v.id"
                        @click="adjustStock(v, 'add', 1)"
                      >
                        +1
                      </button>
                      <button
                        class="btn btn-primary btn-sm"
                        style="padding: 0.2rem 0.6rem; font-weight: 800;"
                        title="Tambah 10 unit"
                        :disabled="stockUpdating === v.id"
                        @click="adjustStock(v, 'add', 10)"
                      >
                        +10
                      </button>
                      <button
                        class="btn btn-secondary btn-sm"
                        style="padding: 0.2rem 0.5rem;"
                        title="Atur Angka Kustom"
                        @click="openCustomStockModal(v)"
                      >
                        ✏️
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

    <!-- TAB: PESANAN MASUK DARI PEMBELI -->
    <div v-if="activeTab === 'orders'">
      <div v-if="loadingOrders" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat pesanan masuk dari pembeli...</p>
      </div>

      <div v-else-if="orders.length === 0" class="card" style="text-align: center; padding: 3rem 0;">
        <p style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 0.5rem;">
          Belum ada pesanan masuk untuk komoditas hasil panen Anda.
        </p>
        <p style="font-size: 0.85rem; color: var(--text-muted);">
          Ketika pembeli melakukan checkout dan pembayaran lunas, rincian pesanan akan otomatis muncul di sini.
        </p>
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div v-for="ord in orders" :key="ord.id" class="card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; margin-bottom: 1rem;">
            <div>
              <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <span class="badge" :class="getOrderBadge(ord.status)">
                  {{ getOrderStatusLabel(ord.status) }}
                </span>
                <span v-if="ord.tracking_code" class="badge badge-blue">
                  Resi: {{ ord.tracking_code }}
                </span>
                <span class="badge badge-gray" style="font-size: 0.75rem;">
                  {{ formatDate(ord.created_at) }}
                </span>
              </div>
              <h3 style="font-size: 1.25rem; font-weight: 700; margin-top: 0.35rem;">
                {{ ord.code }}
              </h3>
              <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                Pembeli: <strong>{{ ord.buyer_name || ord.buyer }}</strong> • Wilayah Tujuan: <strong>{{ ord.dest_region || '-' }}</strong>
              </p>
            </div>

            <div style="text-align: right;">
              <div style="font-size: 0.8rem; color: var(--text-muted);">Total Pembayaran:</div>
              <div style="font-size: 1.35rem; font-weight: 800; color: var(--primary);">
                {{ idr(ord.total) }}
              </div>
              <div style="font-size: 0.8rem; color: var(--text-muted);">
                Total Muatan: {{ ord.total_weight_kg || 0 }} kg
              </div>
            </div>
          </div>

          <!-- Items list -->
          <div class="table-responsive" style="margin-bottom: 1rem;">
            <table class="table">
              <thead>
                <tr>
                  <th>Komoditas & Grade Mutu</th>
                  <th>Kuantitas</th>
                  <th>Harga Satuan</th>
                  <th>Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in (ord.items || [])" :key="item.id || item.name">
                  <td style="font-weight: 600;">
                    {{ item.name }}
                    <span v-if="item.grade" class="badge" :class="getGradeBadge(item.grade)" style="margin-left: 0.35rem;">
                      {{ item.grade }}
                    </span>
                  </td>
                  <td>{{ item.qty }} kg</td>
                  <td>{{ idr(item.unit_price) }}</td>
                  <td style="font-weight: 700;">{{ idr(item.line_total) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Action buttons for order processing -->
          <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; border-top: 1px solid var(--border); padding-top: 0.75rem; background: #f8fafc; padding: 0.75rem; border-radius: var(--radius);">
            <div style="font-size: 0.85rem; color: var(--text-muted);">
              Alamat Kirim: <em>{{ ord.ship_address || 'Alamat Penerima / Gudang Pembeli' }}</em>
            </div>
            <div>
              <button
                v-if="ord.status === 'paid'"
                class="btn btn-primary btn-sm"
                :disabled="acceptingOrderId === ord.id"
                @click="acceptOrder(ord.id)"
              >
                <span v-if="acceptingOrderId === ord.id">Memproses...</span>
                <span v-else>✓ Siapkan Panen & Serahkan ke Tengkulak Wilayah</span>
              </button>
              <span v-else-if="ord.status === 'processing'" class="badge badge-green" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">
                ✓ Panen Siap / Menunggu Dijemput Armada Tengkulak
              </span>
              <span v-else-if="ord.status === 'delivered' || ord.status === 'completed'" class="badge badge-green" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">
                ✓ Pesanan Telah Diterima Pembeli
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: INCOMING NEGOTIATIONS -->
    <div v-if="activeTab === 'negotiations'">
      <div v-if="loadingNego" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat daftar penawaran harga...</p>
      </div>

      <div v-else-if="negotiations.length === 0" class="card" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Belum ada negosiasi harga dari pembeli.</p>
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 1rem;">
        <div v-for="neg in negotiations" :key="neg.id" class="card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div>
              <span class="badge" :class="getNegoBadge(neg.status)">{{ neg.status }}</span>
              <h3 style="font-size: 1.15rem; font-weight: 700; margin-top: 0.25rem;">
                {{ neg.variant_name || (neg.variant?.commodity?.name ? neg.variant.commodity.name + ' - ' + neg.variant.grade : 'Komoditas') }}
              </h3>
              <p style="font-size: 0.85rem; color: var(--text-muted);">
                Dari Pembeli: <strong>{{ neg.buyer?.name || 'Pembeli B2B' }}</strong> ({{ neg.buyer?.company_name || 'B2B/Umum' }})
              </p>
            </div>
            <div style="text-align: right;">
              <div style="font-size: 0.8rem; color: var(--text-muted);">Tawaran Harga:</div>
              <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary);">
                {{ idr(neg.current_offer_price || neg.latest_offer?.offered_price || neg.list_price || 0) }}
              </div>
              <div style="font-size: 0.8rem; color: var(--text-muted);">
                Volume: {{ neg.qty || neg.requested_volume || 0 }} {{ neg.unit || neg.variant?.unit || 'kg' }}
              </div>
            </div>
          </div>

          <div v-if="neg.buyer_note || neg.latest_offer?.message" style="background: #f8fafc; padding: 0.75rem; border-radius: var(--radius); font-size: 0.85rem; margin-bottom: 1rem;">
            <strong>Catatan Pembeli:</strong> {{ neg.buyer_note || neg.latest_offer?.message }}
          </div>

          <!-- Action buttons for pending negotiation -->
          <div v-if="neg.status === 'open' || neg.status === 'buyer_countered'" style="display: flex; gap: 0.5rem; justify-content: flex-end;">
            <button class="btn btn-danger btn-sm" @click="respondNego(neg.id, 'rejected')">
              Tolak Tawaran
            </button>
            <button class="btn btn-primary btn-sm" @click="respondNego(neg.id, 'accepted')">
              ✓ Terima Harga Ini
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: ADD NEW HARVEST COMMODITY -->
    <div v-if="activeTab === 'add-harvest'" class="card" style="max-width: 700px; margin: 0 auto;">
      <!-- Guard if KYC is not verified -->
      <div v-if="!isKycApproved" style="text-align: center; padding: 2.5rem 1.5rem; background: #fffbeb; border: 2px dashed #f59e0b; border-radius: var(--radius);">
        <div style="font-size: 3.5rem; margin-bottom: 1rem;">🔒</div>
        <h2 style="font-size: 1.35rem; font-weight: 800; color: #92400e; margin-bottom: 0.5rem;">
          Verifikasi KYC Diperlukan
        </h2>
        <p style="font-size: 0.95rem; color: #78350f; max-width: 520px; margin: 0 auto 1.5rem auto; line-height: 1.5;">
          Sesuai regulasi ekosistem Rumah Tani, <strong>petani diwajibkan menyelesaikan verifikasi identitas (KYC)</strong> dan disetujui oleh tim verifikator sebelum dapat menambahkan komoditas hasil panen ke katalog pasar.
        </p>

        <div style="margin-bottom: 1.5rem;">
          <span
            class="badge"
            :class="{
              'badge-amber': auth.user?.kyc_status === 'pending',
              'badge-rose': auth.user?.kyc_status === 'rejected',
              'badge-slate': !auth.user?.kyc_status || auth.user?.kyc_status === 'unsubmitted'
            }"
            style="font-size: 0.95rem; padding: 0.4rem 1rem;"
          >
            Status Saat Ini: {{
              auth.user?.kyc_status === 'pending'
                ? '⏳ Menunggu Validasi Administrator'
                : (auth.user?.kyc_status === 'rejected' ? '✕ Verifikasi Ditolak' : '⚠️ Belum Mengajukan KYC')
            }}
          </span>
        </div>

        <div v-if="auth.user?.kyc_note && auth.user?.kyc_status === 'rejected'" class="alert alert-error" style="max-width: 480px; margin: 0 auto 1.5rem auto; text-align: left; font-size: 0.85rem;">
          <strong>Alasan Penolakan:</strong> {{ auth.user?.kyc_note }}
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
          <button class="btn btn-primary" style="padding: 0.6rem 1.5rem; font-weight: 700;" @click="openKycModal = true">
            📄 {{ auth.user?.kyc_status === 'rejected' ? 'Ajukan Ulang Berkas KYC' : (auth.user?.kyc_status === 'pending' ? 'Periksa / Upload Tambahan Berkas' : 'Unggah Dokumen KYC Sekarang') }}
          </button>
          <button class="btn btn-secondary" @click="activeTab = 'inventory'">
            Kembali ke Manajemen Panen
          </button>
        </div>
      </div>

      <!-- Actual Form (Only if KYC is approved) -->
      <div v-else>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">
          Formulir Input Hasil Panen Baru
        </h2>

        <form @submit.prevent="submitNewCommodity">
        <div class="form-group">
          <label class="label">Nama Komoditas</label>
          <input
            v-model="newCommodity.name"
            type="text"
            class="input"
            required
            placeholder="Contoh: Beras Pandan Wangi Organik, Cabai Merah Keriting"
          />
        </div>

        <div class="grid grid-2" style="gap: 1rem;">
          <div class="form-group">
            <label class="label">Kategori Komoditas</label>
            <select v-model="newCommodity.category_id" class="select" required>
              <option value="" disabled>-- Pilih Kategori --</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
              <label class="label" style="margin-bottom: 0;">Wilayah Asal Lahan</label>
              <button
                type="button"
                class="btn btn-sm"
                style="font-size: 0.72rem; padding: 0.15rem 0.5rem; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;"
                @click="showAddRegion = !showAddRegion"
              >
                {{ showAddRegion ? '✕ Batal' : '➕ Input Wilayah Baru' }}
              </button>
            </div>

            <!-- Form Input Wilayah Baru Mandiri -->
            <div v-if="showAddRegion" class="card" style="margin-bottom: 0.75rem; padding: 0.75rem; background: #f0fdf4; border: 1px solid #bbf7d0;">
              <div style="font-weight: 700; font-size: 0.82rem; color: #166534; margin-bottom: 0.4rem;">
                📍 Daftarkan Asal Lahan / Wilayah Baru
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;">
                <div>
                  <label class="label" style="font-size: 0.72rem;">Nama Wilayah / Kab</label>
                  <input v-model="newRegionForm.name" class="input input-sm" placeholder="Misal: Temanggung" />
                </div>
                <div>
                  <label class="label" style="font-size: 0.72rem;">Provinsi</label>
                  <input v-model="newRegionForm.province" class="input input-sm" placeholder="Misal: Jawa Tengah" />
                </div>
              </div>
              <button
                type="button"
                class="btn btn-sm btn-primary"
                style="font-size: 0.75rem; padding: 0.25rem 0.75rem;"
                :disabled="isSubmittingRegion || !newRegionForm.name || !newRegionForm.province"
                @click="submitNewRegion"
              >
                {{ isSubmittingRegion ? 'Menyimpan...' : '✓ Simpan & Pilih Wilayah' }}
              </button>
            </div>

            <select v-model="newCommodity.region_id" class="select" required>
              <option value="" disabled>-- Pilih Wilayah ({{ regions.length }} Wilayah Tersedia) --</option>
              <option v-for="reg in regions" :key="reg.id" :value="reg.id">
                {{ reg.name }} ({{ reg.province }})
              </option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="label">Deskripsi & Karakteristik Panen</label>
          <textarea
            v-model="newCommodity.description"
            rows="3"
            class="textarea"
            placeholder="Jelaskan masa panen, kesegaran, kelembapan, atau metode tanam..."
          ></textarea>
        </div>

        <!-- REAL-TIME PHOTO UPLOAD (Camera / File Upload) -->
        <div class="form-group" style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: var(--radius); padding: 1.25rem; margin-bottom: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <label class="label" style="font-weight: 700; margin-bottom: 0; font-size: 0.95rem;">
              📷 Foto Hasil Panen Asli (Real-Time Camera / File)
            </label>
            <span v-if="uploadingImage" style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">
              ⏳ Mengunggah foto ke server...
            </span>
          </div>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">
            Unggah foto langsung dari kamera HP di lahan pertanian atau pilih file foto panen untuk meningkatkan kepercayaan pembeli & verifikasi tengkulak.
          </p>

          <!-- Hidden Native File & Camera Input -->
          <input
            ref="fileInputRef"
            type="file"
            accept="image/*"
            capture="environment"
            style="display: none;"
            @change="handleImageSelected"
          />

          <!-- Action buttons for upload -->
          <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <button
              type="button"
              class="btn btn-primary btn-sm"
              style="display: inline-flex; align-items: center; gap: 0.35rem;"
              :disabled="uploadingImage"
              @click="triggerFileInput"
            >
              <span>📷 Ambil / Unggah Foto Panen</span>
            </button>
            <span style="font-size: 0.8rem; color: var(--text-muted);">atau gunakan foto contoh:</span>
          </div>

          <!-- Quick Preset Agricultural Photos -->
          <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.75rem;">
            <button
              v-for="p in presetPhotos"
              :key="p.name"
              type="button"
              class="btn btn-secondary btn-sm"
              style="font-size: 0.75rem; padding: 0.2rem 0.5rem;"
              @click="selectPresetPhoto(p.url)"
            >
              {{ p.name }}
            </button>
          </div>

          <!-- Real-Time Photo Preview Box -->
          <div v-if="imagePreview || newCommodity.image_url" style="margin-top: 1rem; position: relative; width: 100%; max-width: 320px; height: 180px; border-radius: var(--radius); overflow: hidden; border: 2px solid var(--primary); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <img
              :src="resolveImageUrl(imagePreview || newCommodity.image_url)"
              alt="Preview Panen"
              style="width: 100%; height: 100%; object-fit: cover;"
            />
            <div style="position: absolute; bottom: 0; inset-x: 0; background: rgba(0,0,0,0.65); color: #fff; padding: 0.35rem 0.6rem; font-size: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
              <span>✓ Foto siap disimpan</span>
              <button
                type="button"
                @click="removeImage"
                style="background: #ef4444; border: none; color: #fff; border-radius: 4px; padding: 0.15rem 0.4rem; font-size: 0.7rem; cursor: pointer;"
              >
                Hapus
              </button>
            </div>
          </div>
        </div>

        <!-- Variants Config (Grades A, B, C) -->
        <div style="margin: 1.5rem 0;">
          <label class="label" style="font-size: 1rem;">Klasifikasi Grade & Stok Awal</label>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem;">
            Tentukan harga dan ketersediaan stok untuk masing-masing mutu hasil panen.
          </p>

          <div v-for="(v, idx) in newCommodity.variants" :key="idx" style="background: #f8fafc; padding: 1rem; border-radius: var(--radius); margin-bottom: 0.75rem;">
            <div style="font-weight: 700; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--primary);">
              Mutu {{ v.grade }}
            </div>
            <div class="grid grid-3" style="gap: 0.5rem;">
              <div>
                <label style="font-size: 0.75rem;">Harga Satuan (Rp)</label>
                <input v-model.number="v.price" type="number" class="input" required min="500" />
              </div>
              <div>
                <label style="font-size: 0.75rem;">Satuan Unit</label>
                <input v-model="v.unit" type="text" class="input" required placeholder="kg" />
              </div>
              <div>
                <label style="font-size: 0.75rem;">Stok Awal Panen</label>
                <input v-model.number="v.stock_qty" type="number" class="input" required min="0" />
              </div>
            </div>
          </div>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
          <button type="button" class="btn btn-secondary" @click="activeTab = 'inventory'">
            Batal
          </button>
          <button type="submit" class="btn btn-primary" :disabled="submittingCommodity">
            <span v-if="submittingCommodity">Menyimpan...</span>
            <span v-else>Simpan & Ajukan Kurasi</span>
          </button>
        </div>
      </form>
      </div>
    </div>

    <!-- Custom Stock Adjustment Modal -->
    <div
      v-if="modalStock.show"
      style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 999; padding: 1rem;"
    >
      <div class="card" style="width: 100%; max-width: 420px;">
        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.5rem;">
          Atur Stok: {{ modalStock.variant?.grade }}
        </h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
          Pembaruan stok berjalan secara real-time dengan penguncian database atomik.
        </p>

        <div class="form-group">
          <label class="label">Jenis Perubahan:</label>
          <select v-model="modalStock.action" class="select">
            <option value="add">Tambah Stok (+)</option>
            <option value="subtract">Kurangi Stok (-)</option>
            <option value="set">Tetapkan Jumlah Baru (=)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="label">Jumlah ({{ modalStock.variant?.unit }}):</label>
          <input v-model.number="modalStock.qty" type="number" min="1" class="input" required />
        </div>

        <div class="form-group">
          <label class="label">Alasan Perubahan:</label>
          <input
            v-model="modalStock.reason"
            type="text"
            class="input"
            placeholder="Panen tambahan, susut sortir, dll."
          />
        </div>

        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
          <button type="button" class="btn btn-secondary" @click="modalStock.show = false">
            Batal
          </button>
          <button type="button" class="btn btn-primary" @click="executeCustomStock">
            Simpan Perubahan Stok
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Upload Berkas KYC Petani -->
    <div
      v-if="openKycModal"
      style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem;"
    >
      <div class="card" style="width: 100%; max-width: 480px; background: #ffffff;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
          <h3 style="font-size: 1.2rem; font-weight: 800; color: #166534; margin: 0;">
            📋 Verifikasi KYC Petani
          </h3>
          <button class="btn btn-sm btn-secondary" @click="openKycModal = false">✕</button>
        </div>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
          Unggah dokumen resmi (Foto KTP, Kartu Kelompok Tani, atau Surat Kepemilikan Lahan) agar status akun terverifikasi dan hasil panen dapat dikurasi oleh distributor.
        </p>

        <form @submit.prevent="submitKycDocuments">
          <div class="form-group">
            <label class="label">Jenis Berkas Dokumen</label>
            <select v-model="kycForm.docType" class="select">
              <option value="ktp">Kartu Tanda Penduduk (KTP)</option>
              <option value="kartu_kelompok_tani">Kartu Keanggotaan Kelompok Tani</option>
              <option value="surat_lahan">Surat Kepemilikan / Sewa Lahan</option>
              <option value="sertifikat_organik">Sertifikat Pertanian Organik</option>
            </select>
          </div>

          <div class="form-group">
            <label class="label">Pilih File (JPG, PNG, atau PDF max 5MB)</label>
            <input
              type="file"
              accept="image/*,application/pdf"
              class="input"
              required
              @change="handleKycFileChange"
            />
          </div>

          <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1.25rem;">
            <button type="button" class="btn btn-secondary" @click="openKycModal = false">
              Batal
            </button>
            <button type="submit" class="btn btn-primary" :disabled="isSubmittingKyc">
              {{ isSubmittingKyc ? 'Mengunggah Berkas...' : 'Kirim Berkas Verifikasi' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { api, idr, resolveImageUrl } from '../../services/api';

const auth = useAuthStore();

const isKycApproved = computed(() => {
  const status = auth.user?.kyc_status;
  return status === 'approved' || status === 'verified';
});

const activeTab = ref('inventory');
const commodities = ref([]);
const orders = ref([]);
const negotiations = ref([]);
const categories = ref([]);
const regions = ref([]);

const loadingInventory = ref(true);
const loadingOrders = ref(false);
const acceptingOrderId = ref(null);
const loadingNego = ref(false);
const submittingCommodity = ref(false);
const stockUpdating = ref(null);

const noticeMessage = ref('');
const errorMessage = ref('');

// Wilayah Baru Mandiri State
const showAddRegion = ref(false);
const isSubmittingRegion = ref(false);
const newRegionForm = reactive({ name: '', province: '' });

// KYC Modal & Submission State
const openKycModal = ref(false);
const isSubmittingKyc = ref(false);
const kycFile = ref(null);
const kycForm = reactive({ docType: 'ktp' });

// Real-Time Image Upload State
const fileInputRef = ref(null);
const uploadingImage = ref(false);
const imagePreview = ref('');

const presetPhotos = [
  { name: '🌶️ Cabai Merah', url: 'https://images.unsplash.com/photo-1596797038530-2c107229654b?w=800' },
  { name: '🌾 Beras Padi', url: 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=800' },
  { name: '🧅 Bawang Merah', url: 'https://images.unsplash.com/photo-1618512496248-a07fe83aa8cb?w=800' },
  { name: '🌽 Jagung Manis', url: 'https://images.unsplash.com/photo-1551754655-cd27e38d2076?w=800' },
  { name: '🍅 Tomat Segar', url: 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800' },
  { name: '🥔 Kentang Lahan', url: 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?w=800' },
];

const triggerFileInput = () => {
  if (fileInputRef.value) {
    fileInputRef.value.click();
  }
};

const handleImageSelected = async (e) => {
  const file = e.target.files?.[0];
  if (!file) return;

  // Real-time client preview
  imagePreview.value = URL.createObjectURL(file);
  uploadingImage.value = true;
  errorMessage.value = '';
  noticeMessage.value = '';

  try {
    const fd = new FormData();
    fd.append('image', file);
    const res = await api.post('/inventory/upload-image', fd);
    newCommodity.image_url = res.path;
    noticeMessage.value = 'Foto berhasil diunggah langsung secara real-time!';
  } catch (err) {
    errorMessage.value = err.message || 'Gagal mengunggah foto.';
  } finally {
    uploadingImage.value = false;
  }
};

const selectPresetPhoto = (url) => {
  newCommodity.image_url = url;
  imagePreview.value = url;
  noticeMessage.value = 'Foto komoditas terpilih.';
};

const removeImage = () => {
  newCommodity.image_url = '';
  imagePreview.value = '';
  if (fileInputRef.value) fileInputRef.value.value = '';
};

// Form for new commodity
const newCommodity = reactive({
  name: '',
  category_id: '',
  region_id: '',
  description: '',
  image_url: '',
  variants: [
    { grade: 'Grade A', price: 20000, unit: 'kg', stock_qty: 100 },
    { grade: 'Grade B', price: 16000, unit: 'kg', stock_qty: 80 },
    { grade: 'Grade C', price: 12000, unit: 'kg', stock_qty: 50 },
  ],
});

// Custom Stock Modal
const modalStock = reactive({
  show: false,
  variant: null,
  action: 'add',
  qty: 10,
  reason: '',
});

const getGradeBadge = (grade) => {
  if (grade === 'Grade A') return 'badge-green';
  if (grade === 'Grade B') return 'badge-blue';
  return 'badge-amber';
};

const getCurationBadge = (status) => {
  switch (status) {
    case 'approved': return 'badge-green';
    case 'rejected': return 'badge-red';
    default: return 'badge-amber';
  }
};

const getNegoBadge = (status) => {
  switch (status) {
    case 'accepted': return 'badge-green';
    case 'rejected': return 'badge-red';
    default: return 'badge-blue';
  }
};

const getOrderBadge = (status) => {
  switch (status) {
    case 'paid': return 'badge-green';
    case 'processing': return 'badge-blue';
    case 'delivered':
    case 'completed': return 'badge-green';
    default: return 'badge-amber';
  }
};

const getOrderStatusLabel = (status) => {
  switch (status) {
    case 'paid': return 'LUNAS (Menyiapkan Panen)';
    case 'processing': return 'SEDANG DIKEMAS / SIAP JEMPUT';
    case 'delivered': return 'TELAH DITERIMA';
    case 'completed': return 'TRANSAKSI SELESAI';
    default: return status;
  }
};

const formatDate = (iso) => {
  if (!iso) return '-';
  return new Date(iso).toLocaleDateString('id-ID');
};

const loadOrders = async () => {
  loadingOrders.value = true;
  try {
    const res = await api.get('/orders');
    orders.value = Array.isArray(res) ? res : (res.orders || []);
  } catch (err) {
    console.error('Failed to load farmer orders:', err);
  } finally {
    loadingOrders.value = false;
  }
};

const acceptOrder = async (orderId) => {
  acceptingOrderId.value = orderId;
  noticeMessage.value = '';
  errorMessage.value = '';
  try {
    await api.post(`/orders/${orderId}/accept`);
    noticeMessage.value = '✓ Hasil panen telah ditandai SIAP! Notifikasi telah dikirimkan ke hub logistik tengkulak wilayah untuk penjemputan armada.';
    await loadOrders();
    await loadInventory();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memperbarui status pesanan.';
  } finally {
    acceptingOrderId.value = null;
  }
};

const loadInventory = async () => {
  loadingInventory.value = true;
  try {
    const res = await api.get('/inventory/commodities');
    commodities.value = Array.isArray(res) ? res : (res.commodities || []);
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memuat daftar komoditas.';
  } finally {
    loadingInventory.value = false;
  }
};

const loadNegotiations = async () => {
  loadingNego.value = true;
  try {
    const res = await api.get('/negotiations');
    negotiations.value = Array.isArray(res) ? res : (res.negotiations || []);
  } catch (err) {
    console.error('Failed to load negotiations:', err);
  } finally {
    loadingNego.value = false;
  }
};

const loadMeta = async () => {
  try {
    const res = await api.get('/public/meta');
    categories.value = res.categories || [];
    regions.value = res.regions || [];
    if (categories.value.length > 0) newCommodity.category_id = categories.value[0].id;
    if (regions.value.length > 0) newCommodity.region_id = regions.value[0].id;
  } catch (err) {
    console.error('Failed to load meta:', err);
  }
};

const adjustStock = async (variant, action, delta) => {
  stockUpdating.value = variant.id;
  noticeMessage.value = '';
  errorMessage.value = '';
  try {
    const signedDelta = action === 'subtract' ? -Math.abs(delta) : Math.abs(delta);
    const res = await api.post(`/inventory/variants/${variant.id}/stock`, {
      delta: signedDelta,
      reason: 'adjust',
    });
    variant.stock_qty = res.stock_qty;
    variant.reserved_qty = res.reserved_qty;
    variant.stock = res.stock_qty;
    variant.reserved = res.reserved_qty;
    noticeMessage.value = `Stok ${variant.grade} berhasil diperbarui menjadi ${res.stock_qty} ${variant.unit}.`;
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memperbarui stok.';
  } finally {
    stockUpdating.value = null;
  }
};

const openCustomStockModal = (variant) => {
  modalStock.variant = variant;
  modalStock.qty = 10;
  modalStock.action = 'add';
  modalStock.reason = '';
  modalStock.show = true;
};

const executeCustomStock = async () => {
  if (!modalStock.variant) return;
  modalStock.show = false;
  await adjustStock(modalStock.variant, modalStock.action, modalStock.qty);
};

const respondNego = async (id, status) => {
  try {
    await api.post(`/negotiations/${id}/respond`, {
      status,
      note: status === 'accepted' ? 'Tawaran disetujui petani.' : 'Tawaran belum sesuai.',
    });
    noticeMessage.value = `Status negosiasi berhasil diubah menjadi ${status}.`;
    await loadNegotiations();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal merespon negosiasi.';
  }
};

const submitNewCommodity = async () => {
  if (!isKycApproved.value) {
    errorMessage.value = 'Akses Ditolak: Anda wajib menyelesaikan verifikasi KYC terlebih dahulu sebelum dapat menambahkan komoditas hasil panen.';
    return;
  }
  submittingCommodity.value = true;
  noticeMessage.value = '';
  errorMessage.value = '';
  try {
    await api.post('/inventory/commodities', {
      name: newCommodity.name,
      categoryId: newCommodity.category_id,
      regionId: newCommodity.region_id,
      description: newCommodity.description,
      imageUrl: newCommodity.image_url || null,
      variants: newCommodity.variants.map((v) => ({
        variantName: `${newCommodity.name} ${v.grade}`,
        grade: v.grade.replace('Grade ', ''),
        unit: v.unit,
        price: v.price,
        stockQty: v.stock_qty,
        minOrder: 1,
        weightPerUnitKg: 1,
      })),
    });
    noticeMessage.value = 'Hasil panen & foto komoditas berhasil disimpan dan diajukan ke tengkulak wilayah untuk kurasi!';
    newCommodity.name = '';
    newCommodity.description = '';
    newCommodity.image_url = '';
    imagePreview.value = '';
    activeTab.value = 'inventory';
    await loadInventory();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal menyimpan hasil panen.';
  } finally {
    submittingCommodity.value = false;
  }
};

const handleKycFileChange = (e) => {
  kycFile.value = e.target.files[0] || null;
};

const submitKycDocuments = async () => {
  if (!kycFile.value) return;
  try {
    isSubmittingKyc.value = true;
    errorMessage.value = '';
    const fd = new FormData();
    fd.append('files[]', kycFile.value);
    fd.append('docTypes[]', kycForm.docType);

    await api.post('/kyc/documents', fd);
    noticeMessage.value = 'Dokumen KYC berhasil diajukan! Menunggu peninjauan Administrator.';
    openKycModal.value = false;
    kycFile.value = null;
    await auth.fetchMe();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal mengajukan berkas KYC.';
  } finally {
    isSubmittingKyc.value = false;
  }
};

const submitNewRegion = async () => {
  if (!newRegionForm.name || !newRegionForm.province) return;
  try {
    isSubmittingRegion.value = true;
    errorMessage.value = '';
    const res = await api.post('/regions', {
      name: newRegionForm.name.trim(),
      province: newRegionForm.province.trim(),
    });
    regions.value.push(res);
    regions.value.sort((a, b) => a.name.localeCompare(b.name));
    newCommodity.region_id = res.id;
    showAddRegion.value = false;
    newRegionForm.name = '';
    newRegionForm.province = '';
    noticeMessage.value = `Wilayah "${res.name} (${res.province})" berhasil ditambahkan dan dipilih!`;
    setTimeout(() => { noticeMessage.value = ''; }, 4000);
  } catch (err) {
    errorMessage.value = err.message || 'Gagal menambahkan wilayah baru.';
    setTimeout(() => { errorMessage.value = ''; }, 4000);
  } finally {
    isSubmittingRegion.value = false;
  }
};

onMounted(() => {
  auth.fetchMe();
  loadInventory();
  loadOrders();
  loadNegotiations();
  loadMeta();
});
</script>
