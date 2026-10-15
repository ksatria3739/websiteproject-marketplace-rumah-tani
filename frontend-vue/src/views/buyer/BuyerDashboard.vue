<template>
  <div class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
    <!-- Welcome Header -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span style="font-size: 0.8rem; background: rgba(255,255,255,0.25); padding: 0.2rem 0.6rem; border-radius: 9999px; text-transform: uppercase; font-weight: 700;">
            Workspace Pembeli ({{ auth.isB2B ? 'B2B Korporasi' : 'B2C Retail' }})
          </span>
          <h1 style="font-size: 1.6rem; font-weight: 800; margin-top: 0.35rem;">
            Halo, {{ auth.user?.name }}!
          </h1>
          <p style="font-size: 0.85rem; opacity: 0.95;">
            {{ auth.user?.company_name ? `Perusahaan: ${auth.user.company_name} • ` : '' }}Kelola keranjang massal, negosiasi harga, dan pembayaran praktis via Tripay (QRIS & VA).
          </p>
        </div>

        <div>
          <router-link to="/catalog" class="btn btn-secondary btn-sm" style="background: #ffffff; color: #b45309; font-weight: 700;">
            + Belanja Komoditas Lagi
          </router-link>
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
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
      <button
        class="btn btn-sm"
        :class="activeTab === 'cart' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'cart'"
      >
        🛒 Keranjang Belanja ({{ cartItems.length }})
      </button>
      <button
        class="btn btn-sm"
        :class="activeTab === 'negotiations' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'negotiations'"
      >
        🤝 Negosiasi & Bidding B2B ({{ negotiations.length }})
      </button>
      <button
        class="btn btn-sm"
        :class="activeTab === 'orders' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'orders'"
      >
        📦 Riwayat Pesanan & Pembayaran ({{ orders.length }})
      </button>
    </div>

    <!-- TAB 1: CART & MULTI-VENDOR CHECKOUT -->
    <div v-if="activeTab === 'cart'">
      <div v-if="loadingCart" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat keranjang belanja...</p>
      </div>

      <div v-else-if="cartItems.length === 0" class="card" style="text-align: center; padding: 4rem 1rem;">
        <div style="font-size: 3rem; margin-bottom: 0.5rem;">🛒</div>
        <h3 style="font-size: 1.25rem; font-weight: 700;">Keranjang Belanja Anda Kosong</h3>
        <p class="text-muted" style="margin-top: 0.5rem; margin-bottom: 1.5rem;">
          Jelajahi hasil panen segar langsung dari petani terverifikasi di katalog.
        </p>
        <router-link to="/catalog" class="btn btn-primary">
          Buka Katalog Komoditas
        </router-link>
      </div>

      <div v-else class="grid grid-3" style="gap: 1.5rem; align-items: start;">
        <!-- Left: Cart Items List (2 cols) -->
        <div style="grid-column: span 2; display: flex; flex-direction: column; gap: 1rem;">
          <div v-for="item in cartItems" :key="item.variantId" class="card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
              <div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <h3 style="font-size: 1.15rem; font-weight: 700;">{{ item.name }}</h3>
                  <span class="badge badge-green">Grade {{ item.grade }}</span>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                  Petani: <strong>{{ item.sellerName }}</strong> (📍 {{ item.originRegion }})
                </p>
                <div style="font-size: 0.95rem; font-weight: 700; color: var(--primary); margin-top: 0.35rem;">
                  {{ idr(item.unitPrice) }} / kg
                  <span v-if="item.hasNegotiatedPrice" class="badge badge-amber" style="margin-left: 0.35rem;">
                    Harga Negosiasi B2B
                  </span>
                </div>
              </div>

              <div>
                <button
                  class="btn btn-danger btn-sm"
                  style="padding: 0.25rem 0.6rem; font-size: 0.8rem;"
                  @click="removeCartItem(item.variantId)"
                >
                  ✕ Hapus
                </button>
              </div>
            </div>

            <!-- Qty & Line Total -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 0.75rem; margin-top: 0.75rem;">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label style="font-size: 0.85rem; font-weight: 600;">Jumlah Kuantitas:</label>
                <input
                  v-model.number="item.qty"
                  type="number"
                  min="1"
                  @change="updateCartQty(item)"
                  class="input"
                  style="width: 85px; padding: 0.35rem; text-align: center;"
                />
                <span style="font-size: 0.85rem; color: var(--text-muted);">kg</span>
              </div>

              <div style="text-align: right;">
                <div style="font-size: 0.75rem; color: var(--text-muted);">Subtotal Item:</div>
                <div style="font-weight: 800; font-size: 1.1rem; color: var(--text-main);">
                  {{ idr((item.qty || 1) * (item.unitPrice || 0)) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Checkout & Pricing & Payment Method Selection (1 col) -->
        <div class="card" style="position: sticky; top: 5rem;">
          <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            Rincian Pesanan & Pembayaran
          </h3>

          <div class="form-group">
            <label class="label">Wilayah Tujuan Pengiriman:</label>
            <select v-model="destinationRegionId" class="select" @change="recalculatePricing">
              <option v-for="reg in regions" :key="reg.id" :value="reg.id">
                {{ reg.name }} ({{ reg.province }})
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="label">Alamat Pengiriman Lengkap:</label>
            <textarea v-model="deliveryAddress" rows="2" class="textarea" placeholder="Jalan, nomor gudang/pabrik, kelurahan, kota"></textarea>
          </div>

          <!-- PAYMENT GATEWAY SELECTION (Tripay & Instant Gateway) -->
          <div class="form-group" style="background: #f8fafc; padding: 0.85rem; border-radius: var(--radius); border: 1px solid #e2e8f0; margin: 1rem 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <label class="label" style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0;">
                Metode Pembayaran (Tripay / Direct)
              </label>
              <span class="badge badge-green" style="font-size: 0.7rem;">Tanpa Ribet</span>
            </div>
            <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.75rem;">
              Pendaftaran instan, mendukung QRIS langsung scan di semua m-Banking & E-Wallet, dan VA Otomatis 24 Jam.
            </p>

            <select v-model="selectedPaymentMethod" class="select" style="font-size: 0.85rem; font-weight: 600;">
              <optgroup label="⚡ QRIS Real-Time (Rekomendasi)">
                <option value="tripay_qris">📱 QRIS Real-Time (BCA, Livin, BRImo, GoPay, OVO, DANA)</option>
              </optgroup>
              <optgroup label="🏦 Virtual Account Bank (Verifikasi Instan)">
                <option value="tripay_bca_va">🏦 BCA Virtual Account</option>
                <option value="tripay_mandiri_va">🏦 Mandiri Virtual Account</option>
                <option value="tripay_bri_va">🏦 BRI Virtual Account (BRIVA)</option>
                <option value="tripay_bni_va">🏦 BNI Virtual Account</option>
                <option value="tripay_bsi_va">🌙 BSI Syariah Virtual Account</option>
              </optgroup>
              <optgroup label="🏪 Gerai Kasir Retail">
                <option value="tripay_alfamart">🏪 Alfamart / Alfamidi</option>
                <option value="tripay_indomaret">🏪 Indomaret</option>
              </optgroup>
            </select>
          </div>

          <!-- Pricing summary rows -->
          <div style="display: flex; flex-direction: column; gap: 0.5rem; margin: 1rem 0; font-size: 0.85rem;">
            <div style="display: flex; justify-content: space-between;">
              <span class="text-muted">Total Berat Komoditas:</span>
              <span><strong>{{ totalWeightKg }} kg</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span class="text-muted">Subtotal Produk:</span>
              <span>{{ idr(cartSubtotal) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span class="text-muted">Estimasi Biaya Logistik Wilayah:</span>
              <span>{{ idr(estimatedShipping) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span class="text-muted">PPN (11%):</span>
              <span>{{ idr(taxAmount) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span class="text-muted">Biaya Layanan Platform (2%):</span>
              <span>{{ idr(adminFee) }}</span>
            </div>
            <div style="border-top: 2px solid var(--border); padding-top: 0.75rem; margin-top: 0.25rem; display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 800; color: var(--primary);">
              <span>Total Tagihan:</span>
              <span>{{ idr(grandTotal) }}</span>
            </div>
          </div>

          <!-- Anti-Race Condition Checkout Button -->
          <button
            class="btn btn-primary"
            style="width: 100%; font-size: 1rem; padding: 0.85rem; font-weight: 700;"
            :disabled="checkingOut"
            @click="handleCheckout"
          >
            <span v-if="checkingOut">Mengunci Stok & Memproses...</span>
            <span v-else>🔒 Bayar & Kunci Stok (Checkout)</span>
          </button>
          <p style="font-size: 0.75rem; color: var(--text-muted); text-align: center; margin-top: 0.5rem;">
            Transaksi atomik aman dari perebutan stok dengan reservasi instan.
          </p>
        </div>
      </div>
    </div>

    <!-- TAB 2: B2B NEGOTIATIONS -->
    <div v-if="activeTab === 'negotiations'">
      <div v-if="loadingNego" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat negosiasi penawaran...</p>
      </div>

      <div v-else-if="negotiations.length === 0" class="card" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Anda belum memiliki riwayat negosiasi aktif.</p>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">
          Akun Pembeli B2B dapat mengajukan penawaran harga khusus dari halaman detail produk di katalog.
        </p>
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 1rem;">
        <div v-for="neg in negotiations" :key="neg.id" class="card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div>
              <span class="badge" :class="getNegoBadge(neg.status)">{{ neg.status }}</span>
              <h3 style="font-size: 1.15rem; font-weight: 700; margin-top: 0.25rem;">
                {{ neg.variant?.commodity?.name }} ({{ neg.variant?.grade }})
              </h3>
              <p style="font-size: 0.85rem; color: var(--text-muted);">
                Petani: {{ neg.variant?.commodity?.farmer?.name }} • Volume: {{ neg.requested_volume }} {{ neg.variant?.unit }}
              </p>
            </div>
            <div style="text-align: right;">
              <div style="font-size: 0.8rem; color: var(--text-muted);">Harga Ditawarkan:</div>
              <div style="font-size: 1.2rem; font-weight: 800; color: var(--primary);">
                {{ idr(neg.latest_offer?.offered_price || neg.current_offer_price) }}
              </div>
              <div style="font-size: 0.8rem; color: var(--text-muted);">
                Harga Normal: {{ idr(neg.variant?.price) }}
              </div>
            </div>
          </div>

          <div v-if="neg.buyer_note" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">
            <strong>Catatan Anda:</strong> {{ neg.buyer_note }}
          </div>
          <div v-if="neg.seller_note" style="font-size: 0.85rem; color: var(--primary-dark); background: var(--primary-light); padding: 0.5rem 0.75rem; border-radius: var(--radius); margin-bottom: 0.5rem;">
            <strong>Respon Petani:</strong> {{ neg.seller_note }}
          </div>

          <!-- Checkout deal if accepted -->
          <div v-if="neg.status === 'accepted'" style="display: flex; justify-content: flex-end; margin-top: 0.75rem;">
            <button class="btn btn-primary btn-sm" @click="checkoutNegotiation(neg)">
              ⚡ Beli dengan Harga Negosiasi Ini
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: ORDER HISTORY & PAYMENT LIST -->
    <div v-if="activeTab === 'orders'">
      <div v-if="loadingOrders" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat daftar pesanan Anda...</p>
      </div>

      <div v-else-if="orders.length === 0" class="card" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Belum ada transaksi pesanan yang dibuat.</p>
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div v-for="ord in orders" :key="ord.id" class="card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; margin-bottom: 1rem;">
            <div>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="badge" :class="getOrderBadge(ord.status)">{{ getStatusLabel(ord.status) }}</span>
                <span class="badge badge-blue">{{ getPaymentLabel(ord.payment?.method) }}</span>
              </div>
              <h3 style="font-size: 1.15rem; font-weight: 800; font-family: monospace; margin-top: 0.35rem;">
                {{ ord.code || ord.order_number }}
              </h3>
              <p style="font-size: 0.8rem; color: var(--text-muted);">
                Waktu Transaksi: {{ formatDate(ord.created_at) }} • Produsen: <strong>{{ ord.seller }}</strong>
              </p>
            </div>

            <div style="text-align: right;">
              <div style="font-size: 0.8rem; color: var(--text-muted);">Total Pembayaran:</div>
              <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary);">
                {{ idr(ord.total || ord.grand_total) }}
              </div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">
                Muatan: {{ ord.total_weight_kg || 0 }} kg
              </div>
            </div>
          </div>

          <!-- Payment code bar & action buttons -->
          <div style="background: #f8fafc; padding: 0.85rem; border-radius: var(--radius); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
              <div v-if="ord.payment?.pay_code" style="font-size: 0.85rem;">
                <span class="text-muted">Kode Pembayaran:</span>
                <strong style="font-family: monospace; font-size: 0.95rem; margin-left: 0.35rem; color: var(--primary);">
                  {{ ord.payment.pay_code }}
                </strong>
              </div>
              <div v-if="ord.tracking_code" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                Resi Armada: <strong>{{ ord.tracking_code }}</strong>
              </div>
            </div>

            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
              <!-- Open Payment Modal / QRIS if pending -->
              <button
                v-if="ord.status === 'pending_payment'"
                class="btn btn-secondary btn-sm"
                @click="openPaymentModalForOrder(ord)"
              >
                📱 Lihat Instruksi Pembayaran
              </button>

              <!-- Mock Payment Sandbox trigger if pending -->
              <button
                v-if="ord.status === 'pending_payment'"
                class="btn btn-primary btn-sm"
                @click="simulatePayment(ord.id)"
              >
                ⚡ Bayar Sekarang (Tripay Sandbox)
              </button>

              <!-- Track Link -->
              <router-link
                v-if="ord.tracking_code"
                :to="`/track/${ord.tracking_code}`"
                class="btn btn-secondary btn-sm"
              >
                Lacak Armada →
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- INTERACTIVE PAYMENT MODAL (TRIPAY & QRIS / VA) -->
    <div
      v-if="paymentModal.show"
      style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; backdrop-filter: blur(4px);"
    >
      <div class="card" style="width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; text-align: center; padding: 1.75rem;">
        <!-- Header -->
        <div style="margin-bottom: 1rem;">
          <span class="badge badge-green" style="font-size: 0.8rem; margin-bottom: 0.5rem;">
            Tripay Gateway Terverifikasi
          </span>
          <h2 style="font-size: 1.35rem; font-weight: 800; margin-top: 0.25rem;">
            Instruksi Pembayaran
          </h2>
          <p style="font-size: 0.85rem; color: var(--text-muted);">
            Selesaikan pembayaran sebelum batas waktu berakhir untuk konfirmasi pesanan otomatis.
          </p>
        </div>

        <!-- Total Amount -->
        <div style="background: #f8fafc; border-radius: var(--radius); padding: 1rem; margin-bottom: 1.25rem; border: 1px solid var(--border);">
          <div style="font-size: 0.8rem; color: var(--text-muted);">Total Tagihan Pembayaran:</div>
          <div style="font-size: 1.6rem; font-weight: 800; color: var(--primary);">
            {{ idr(paymentModal.amount) }}
          </div>
          <div style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem;">
            ⏳ Batas Waktu Pembayaran: 60 Menit
          </div>
        </div>

        <!-- 1. IF QRIS -->
        <div v-if="isQrisMethod(paymentModal.method)" style="margin-bottom: 1.25rem;">
          <div style="background: #ffffff; border: 2px dashed #15803d; border-radius: var(--radius); padding: 1rem; display: inline-block;">
            <img
              :src="paymentModal.qrUrl || getDynamicQrUrl(paymentModal.payCode, paymentModal.amount)"
              alt="QRIS Code"
              style="width: 240px; height: 240px; display: block; margin: 0 auto; object-fit: contain;"
            />
          </div>
          <div style="font-size: 0.85rem; font-weight: 700; color: #166534; margin-top: 0.5rem;">
            NMID: ID1020038847192 (Rumah Tani Indonesia)
          </div>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem; line-height: 1.4;">
            Buka aplikasi m-Banking (BCA, Livin Mandiri, BRImo, BNI) atau E-Wallet (GoPay, OVO, ShopeePay, DANA) lalu arahkan kamera ke kode QR di atas.
          </p>
        </div>

        <!-- 2. IF VIRTUAL ACCOUNT -->
        <div v-else-if="isVaMethod(paymentModal.method)" style="margin-bottom: 1.25rem;">
          <div style="background: #f1f5f9; padding: 1.25rem; border-radius: var(--radius); margin-bottom: 0.75rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.25rem;">
              {{ getPaymentLabel(paymentModal.method) }}
            </div>
            <div style="font-size: 1.4rem; font-weight: 800; font-family: monospace; letter-spacing: 0.05em; color: var(--text-main);">
              {{ paymentModal.payCode }}
            </div>
            <button
              class="btn btn-secondary btn-sm"
              style="margin-top: 0.5rem; font-size: 0.75rem; padding: 0.25rem 0.6rem;"
              @click="copyToClipboard(paymentModal.payCode)"
            >
              📋 Salin Nomor VA
            </button>
          </div>
          <div style="text-align: left; font-size: 0.8rem; color: var(--text-muted); background: #f8fafc; padding: 0.75rem; border-radius: var(--radius);">
            <strong>Panduan Transfer:</strong>
            <ol style="margin: 0.35rem 0 0 1rem; padding: 0;">
              <li>Pilih menu <strong>Transfer > Virtual Account</strong> pada m-Banking/ATM Anda.</li>
              <li>Masukkan nomor VA di atas dan pastikan nama tujuan adalah <strong>Rumah Tani</strong>.</li>
              <li>Masukkan nominal tagihan tepat sebesar <strong>{{ idr(paymentModal.amount) }}</strong>.</li>
            </ol>
          </div>
        </div>

        <!-- 3. IF RETAIL (ALFAMART / INDOMARET) -->
        <div v-else style="margin-bottom: 1.25rem;">
          <div style="background: #f1f5f9; padding: 1.25rem; border-radius: var(--radius);">
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.25rem;">
              Kode Pembayaran Kasir:
            </div>
            <div style="font-size: 1.4rem; font-weight: 800; font-family: monospace; color: var(--text-main);">
              {{ paymentModal.payCode }}
            </div>
            <button
              class="btn btn-secondary btn-sm"
              style="margin-top: 0.5rem; font-size: 0.75rem; padding: 0.25rem 0.6rem;"
              @click="copyToClipboard(paymentModal.payCode)"
            >
              📋 Salin Kode
            </button>
          </div>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
            Tunjukkan kode pembayaran ini ke kasir gerai terdekat dan sebutkan pembayaran untuk Rumah Tani.
          </p>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem;">
          <button
            class="btn btn-primary"
            style="width: 100%; font-weight: 700; padding: 0.75rem;"
            :disabled="settlingSandbox"
            @click="executeModalPayment"
          >
            <span v-if="settlingSandbox">Memverifikasi Pembayaran...</span>
            <span v-else>⚡ Simulasikan Pembayaran Instan (Tripay Sandbox)</span>
          </button>
          <button
            type="button"
            class="btn btn-secondary"
            style="width: 100%;"
            @click="paymentModal.show = false"
          >
            Tutup & Buka Daftar Pesanan
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { api, idr } from '../../services/api';

const auth = useAuthStore();

const activeTab = ref('cart');
const cartItems = ref([]);
const negotiations = ref([]);
const orders = ref([]);
const regions = ref([]);

const destinationRegionId = ref('');
const deliveryAddress = ref(auth.user?.address || 'Jl. Gatot Subroto No. 45, Jakarta Selatan');
const selectedPaymentMethod = ref('tripay_qris');

const loadingCart = ref(true);
const loadingNego = ref(false);
const loadingOrders = ref(false);
const checkingOut = ref(false);
const settlingSandbox = ref(false);

const noticeMessage = ref('');
const errorMessage = ref('');

// Payment Modal State
const paymentModal = reactive({
  show: false,
  orderId: null,
  gatewayRef: '',
  method: 'tripay_qris',
  payCode: '',
  qrUrl: '',
  amount: 0,
});

const isQrisMethod = (method) => {
  return ['qris', 'tripay_qris'].includes(method);
};

const isVaMethod = (method) => {
  return ['tripay_bca_va', 'tripay_mandiri_va', 'tripay_bri_va', 'tripay_bni_va', 'tripay_bsi_va', 'va_bca', 'va_bni', 'va_bri', 'va_mandiri'].includes(method);
};

const getPaymentLabel = (method) => {
  switch (method) {
    case 'tripay_qris':
    case 'qris':
      return '📱 QRIS Real-Time';
    case 'tripay_bca_va':
    case 'va_bca':
      return '🏦 BCA Virtual Account';
    case 'tripay_mandiri_va':
    case 'va_mandiri':
      return '🏦 Mandiri Virtual Account';
    case 'tripay_bri_va':
    case 'va_bri':
      return '🏦 BRI Virtual Account';
    case 'tripay_bni_va':
    case 'va_bni':
      return '🏦 BNI Virtual Account';
    case 'tripay_bsi_va':
      return '🌙 BSI Virtual Account';
    case 'tripay_alfamart':
      return '🏪 Alfamart';
    case 'tripay_indomaret':
      return '🏪 Indomaret';
    default:
      return '💳 Pembayaran Otomatis';
  }
};

const getStatusLabel = (status) => {
  switch (status) {
    case 'pending_payment': return '⏳ Menunggu Pembayaran';
    case 'paid': return '✓ Lunas (Disiapkan Petani)';
    case 'processing': return '⚙️ Sedang Dikemas';
    case 'shipped': return '🚚 Dalam Pengiriman Armada';
    case 'delivered': return '📦 Sampai Tujuan';
    case 'completed': return '✓ Selesai';
    default: return status;
  }
};

const getDynamicQrUrl = (code, amount) => {
  const payload = `00020101021226670016ID.CO.TRIPAY.WWW011893600002011${code || 'RT999'}52045814530336054${amount || 10000}5802ID5911RUMAH TANI6007JAKARTA6304`;
  return `https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=${encodeURIComponent(payload)}`;
};

const copyToClipboard = (text) => {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text);
    noticeMessage.value = `Berhasil disalin ke clipboard: ${text}`;
  }
};

const getNegoBadge = (status) => {
  switch (status) {
    case 'accepted': return 'badge-green';
    case 'rejected': return 'badge-red';
    case 'ordered': return 'badge-blue';
    default: return 'badge-amber';
  }
};

const getOrderBadge = (status) => {
  switch (status) {
    case 'completed': return 'badge-green';
    case 'paid': return 'badge-green';
    case 'processing': return 'badge-blue';
    case 'shipped': return 'badge-blue';
    default: return 'badge-amber';
  }
};

const formatDate = (iso) => {
  if (!iso) return '-';
  return new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};

// Calculations
const cartSubtotal = computed(() => {
  return cartItems.value.reduce((acc, it) => acc + ((it.qty || 1) * (it.unitPrice || 0)), 0);
});

const totalWeightKg = computed(() => {
  return cartItems.value.reduce((acc, it) => acc + ((it.qty || 1) * 1), 0);
});

const estimatedShipping = computed(() => {
  if (totalWeightKg.value === 0) return 0;
  return 25000 + (totalWeightKg.value * 3000);
});

const taxAmount = computed(() => Math.round(cartSubtotal.value * 0.11));
const adminFee = computed(() => Math.round(cartSubtotal.value * 0.02));
const grandTotal = computed(() => cartSubtotal.value + estimatedShipping.value + taxAmount.value + adminFee.value);

const loadCart = async () => {
  loadingCart.value = true;
  try {
    const res = await api.get('/cart', { destRegionId: destinationRegionId.value });
    if (res.groups && res.groups.length > 0) {
      cartItems.value = res.groups.flatMap((g) =>
        g.items.map((it) => ({
          ...it,
          sellerName: g.sellerName,
          originRegion: g.originRegion,
        }))
      );
    } else {
      cartItems.value = [];
    }
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memuat keranjang.';
  } finally {
    loadingCart.value = false;
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

const loadOrders = async () => {
  loadingOrders.value = true;
  try {
    const res = await api.get('/orders');
    orders.value = Array.isArray(res) ? res : (res.orders || []);
  } catch (err) {
    console.error('Failed to load orders:', err);
  } finally {
    loadingOrders.value = false;
  }
};

const loadMeta = async () => {
  try {
    const res = await api.get('/public/meta');
    regions.value = res.regions || [];
    if (regions.value.length > 0 && !destinationRegionId.value) {
      destinationRegionId.value = regions.value[0].id;
    }
  } catch (err) {
    console.error('Failed to load meta:', err);
  }
};

const updateCartQty = async (item) => {
  try {
    await api.put('/cart/items', { variantId: item.variantId, qty: item.qty });
    await loadCart();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal mengubah kuantitas.';
  }
};

const removeCartItem = async (variantId) => {
  try {
    await api.delete(`/cart/items/${variantId}`);
    cartItems.value = cartItems.value.filter((i) => i.variantId !== variantId);
    await loadCart();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal menghapus produk.';
  }
};

const recalculatePricing = () => {
  loadCart();
};

const handleCheckout = async () => {
  checkingOut.value = true;
  noticeMessage.value = '';
  errorMessage.value = '';
  try {
    const res = await api.post('/checkout', {
      destRegionId: destinationRegionId.value,
      shipAddress: deliveryAddress.value || 'Gudang Pembeli',
      method: selectedPaymentMethod.value,
    });

    // Populate and trigger interactive payment modal
    paymentModal.orderId = res.orders?.[0]?.id || null;
    paymentModal.gatewayRef = res.gatewayRef;
    paymentModal.method = res.method;
    paymentModal.payCode = res.payCode;
    paymentModal.qrUrl = res.qrUrl;
    paymentModal.amount = res.amount;
    paymentModal.show = true;

    noticeMessage.value = `Checkout Berhasil! Silakan selesaikan pembayaran sebesar ${idr(res.amount)}. Kunci stok transaksi atomik aktif.`;
    cartItems.value = [];
    activeTab.value = 'orders';
    await loadOrders();
  } catch (err) {
    errorMessage.value = err.message || 'Checkout gagal karena kuota stok habis atau terjadi persaingan transaksi.';
  } finally {
    checkingOut.value = false;
  }
};

const openPaymentModalForOrder = (ord) => {
  paymentModal.orderId = ord.id;
  paymentModal.gatewayRef = ord.payment?.gateway_ref || ord.code;
  paymentModal.method = ord.payment?.method || 'tripay_qris';
  paymentModal.payCode = ord.payment?.pay_code || `PAY-${ord.id}`;
  paymentModal.qrUrl = ord.payment?.qr_url || '';
  paymentModal.amount = ord.total;
  paymentModal.show = true;
};

const executeModalPayment = async () => {
  if (!paymentModal.orderId && !paymentModal.gatewayRef) return;
  settlingSandbox.value = true;
  try {
    await api.post('/webhooks/mock-settle', {
      orderId: paymentModal.orderId,
      gatewayRef: paymentModal.gatewayRef,
    });
    noticeMessage.value = '✓ Pembayaran LUNAS via Tripay Sandbox! Stok telah didekrementasi & pengemasan armada dijadwalkan.';
    paymentModal.show = false;
    await loadOrders();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memproses pembayaran sandbox.';
  } finally {
    settlingSandbox.value = false;
  }
};

const simulatePayment = async (orderId) => {
  try {
    await api.post('/webhooks/mock-settle', { orderId });
    noticeMessage.value = 'Pembayaran berhasil diselesaikan via Tripay Sandbox! Stok telah didekrementasi & armada dijadwalkan.';
    await loadOrders();
  } catch (err) {
    errorMessage.value = err.message || 'Simulasi pembayaran gagal.';
  }
};

const checkoutNegotiation = async (nego) => {
  try {
    await api.put('/cart/items', {
      variantId: nego.variant_id,
      qty: nego.requested_volume,
    });
    activeTab.value = 'cart';
    await loadCart();
    noticeMessage.value = 'Hasil negosiasi harga berhasil dimasukkan ke keranjang!';
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memproses negosiasi ke keranjang.';
  }
};

onMounted(() => {
  loadCart();
  loadNegotiations();
  loadOrders();
  loadMeta();
});
</script>
