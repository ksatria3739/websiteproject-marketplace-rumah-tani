<template>
  <div class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
    <!-- Welcome Header -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); color: #ffffff;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span style="font-size: 0.8rem; background: rgba(255,255,255,0.2); padding: 0.2rem 0.6rem; border-radius: 9999px; text-transform: uppercase; font-weight: 700;">
            Portal Operasional Tengkulak Wilayah
          </span>
          <h1 style="font-size: 1.6rem; font-weight: 800; margin-top: 0.35rem;">
            Pusat Distribusi & Kurasi Komoditas
          </h1>
          <p style="font-size: 0.85rem; opacity: 0.9;">
            Wilayah Operasional: <strong>{{ auth.user?.region?.name || 'Jawa Barat' }}</strong> • Penanggung Jawab: {{ auth.user?.name }}
          </p>
        </div>

        <div>
          <span class="badge badge-green" style="font-size: 0.85rem;">
            🟢 Hub Distribusi Aktif
          </span>
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
        :class="activeTab === 'curation' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'curation'"
      >
        🔍 Kurasi Hasil Panen ({{ pendingCuration.length }})
      </button>
      <button
        class="btn btn-sm"
        :class="activeTab === 'shipments' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'shipments'"
      >
        📦 Manajemen Pengiriman ({{ shipments.length }})
      </button>
      <button
        class="btn btn-sm"
        :class="activeTab === 'armada' ? 'btn-primary' : 'btn-secondary'"
        @click="activeTab = 'armada'"
      >
        🚛 Armada Kendaraan ({{ vehicles.length }})
      </button>
    </div>

    <!-- TAB 1: CURATION -->
    <div v-if="activeTab === 'curation'">
      <div v-if="loadingCuration" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat komoditas yang membutuhkan kurasi...</p>
      </div>

      <div v-else-if="pendingCuration.length === 0" class="card" style="text-align: center; padding: 3rem 1rem;">
        <p style="font-size: 1.1rem; color: var(--text-muted);">
          Tidak ada komoditas baru yang menunggu verifikasi saat ini.
        </p>
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div v-for="item in pendingCuration" :key="item.id" class="card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
            <div>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700;">{{ item.name }}</h3>
                <span class="badge badge-amber">Menunggu Kurasi</span>
              </div>
              <p style="font-size: 0.85rem; color: var(--text-muted);">
                Petani: <strong>{{ item.farmer?.name }}</strong> • Kategori: {{ item.category?.name }} • Asal: {{ item.region?.name }}
              </p>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">
              Diajukan: {{ formatDate(item.created_at) }}
            </div>
          </div>

          <p v-if="item.description" style="font-size: 0.875rem; color: #475569; margin-bottom: 1rem; background: #f8fafc; padding: 0.75rem; border-radius: var(--radius);">
            <strong>Catatan Petani:</strong> {{ item.description }}
          </p>

          <!-- Variants preview -->
          <div class="table-responsive" style="margin-bottom: 1rem;">
            <table class="table">
              <thead>
                <tr>
                  <th>Grade Mutu</th>
                  <th>Harga Satuan</th>
                  <th>Stok Panen</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in item.variants" :key="v.id">
                  <td>
                    <span class="badge" :class="getGradeBadge(v.grade)">{{ v.grade }}</span>
                  </td>
                  <td>{{ idr(v.price) }} / {{ v.unit }}</td>
                  <td>{{ v.stock_qty }} {{ v.unit }}</td>
                  <td><span class="badge badge-blue">Valid</span></td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Curation Decision Buttons -->
          <div style="display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 0.75rem;">
            <button class="btn btn-danger btn-sm" @click="decideCuration(item.id, 'rejected')">
              ✕ Tolak Komoditas
            </button>
            <button class="btn btn-primary btn-sm" @click="decideCuration(item.id, 'approved')">
              ✓ Setujui & Rilis ke Katalog Publik
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: SHIPMENTS DISPATCH & TRACKING STATUS -->
    <div v-if="activeTab === 'shipments'">
      <div v-if="loadingShipments" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Memuat data logistik pengiriman...</p>
      </div>

      <div v-else-if="shipments.length === 0" class="card" style="text-align: center; padding: 3rem 0;">
        <p class="text-muted">Belum ada pengiriman dalam wilayah Anda.</p>
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 1rem;">
        <div v-for="s in shipments" :key="s.id" class="card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div>
              <span class="badge" :class="getShipmentBadge(s.status)">{{ s.status }}</span>
              <h3 style="font-size: 1.15rem; font-weight: 700; margin-top: 0.25rem;">
                Resi: {{ s.tracking_code }}
              </h3>
              <p style="font-size: 0.85rem; color: var(--text-muted);">
                Rute: <strong>{{ s.origin_region?.name }} → {{ s.destination_region?.name }}</strong> • Muatan: {{ s.actual_weight_kg || 0 }} kg
              </p>
            </div>

            <div style="text-align: right;">
              <div v-if="s.vehicle" style="font-size: 0.85rem; font-weight: 600;">
                🚛 {{ s.vehicle.model }} ({{ s.vehicle.plate_number }})
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  Supir: {{ s.vehicle.driver_name }} ({{ s.vehicle.driver_phone }})
                </div>
              </div>
              <div v-else style="font-size: 0.85rem; color: #dc2626; font-weight: 600;">
                ⚠️ Belum Ditugaskan Armada
              </div>
            </div>
          </div>

          <!-- Controls: Assign vehicle or update status -->
          <div style="display: flex; gap: 0.5rem; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 0.75rem;">
            <button
              v-if="!s.vehicle_id"
              class="btn btn-secondary btn-sm"
              @click="openAssignModal(s)"
            >
              Tugaskan Armada
            </button>
            <button
              v-if="s.status !== 'delivered'"
              class="btn btn-primary btn-sm"
              @click="openStatusModal(s)"
            >
              Perbarui Status Perjalanan
            </button>
            <router-link :to="`/track/${s.tracking_code}`" class="btn btn-secondary btn-sm">
              Lihat di Peta Publik →
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: ARMADA FLEET MANAGEMENT -->
    <div v-if="activeTab === 'armada'">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 style="font-size: 1.25rem; font-weight: 700;">Daftar Armada Angkut Wilayah</h2>
        <button class="btn btn-primary btn-sm" @click="showAddVehicleModal = true">
          + Tambah Armada Baru
        </button>
      </div>

      <div class="grid grid-3">
        <div v-for="v in vehicles" :key="v.id" class="card">
          <div style="font-size: 2rem; margin-bottom: 0.5rem;">🚛</div>
          <h3 style="font-size: 1.1rem; font-weight: 700;">{{ v.model }}</h3>
          <p style="font-size: 0.85rem; color: var(--text-muted); font-family: monospace;">
            Plat: {{ v.plate_number }}
          </p>
          <div style="margin-top: 0.5rem; font-size: 0.85rem;">
            Kapasitas Muat: <strong>{{ v.max_capacity_kg }} kg</strong>
          </div>
          <div style="font-size: 0.85rem; color: var(--text-muted);">
            Supir: {{ v.driver_name }} ({{ v.driver_phone }})
          </div>
          <div style="margin-top: 0.75rem;">
            <span class="badge" :class="v.status === 'available' ? 'badge-green' : 'badge-amber'">
              {{ v.status === 'available' ? 'Siap Angkut' : 'Sedang Bertugas' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: Assign Vehicle -->
    <div v-if="modalAssign.show" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 999; padding: 1rem;">
      <div class="card" style="width: 100%; max-width: 450px;">
        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.5rem;">Tugaskan Armada Angkut</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
          Resi: {{ modalAssign.shipment?.tracking_code }} • Berat: {{ modalAssign.shipment?.actual_weight_kg || 0 }} kg
        </p>

        <div class="form-group">
          <label class="label">Pilih Kendaraan:</label>
          <select v-model="modalAssign.vehicle_id" class="select" required>
            <option value="" disabled>-- Pilih Armada --</option>
            <option v-for="v in vehicles" :key="v.id" :value="v.id">
              {{ v.model }} ({{ v.plate_number }}) - Maks: {{ v.max_capacity_kg }} kg
            </option>
          </select>
        </div>

        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
          <button class="btn btn-secondary" @click="modalAssign.show = false">Batal</button>
          <button class="btn btn-primary" @click="executeAssignVehicle">Tugaskan Sekarang</button>
        </div>
      </div>
    </div>

    <!-- MODAL: Update Status -->
    <div v-if="modalStatus.show" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 999; padding: 1rem;">
      <div class="card" style="width: 100%; max-width: 450px;">
        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.5rem;">Perbarui Status Distribusi</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
          Resi: {{ modalStatus.shipment?.tracking_code }}
        </p>

        <div class="form-group">
          <label class="label">Status Tahapan Baru:</label>
          <select v-model="modalStatus.status" class="select">
            <option value="assigned">Armada Siap (Assigned)</option>
            <option value="loading">Pemuatan Komoditas (Loading)</option>
            <option value="in_transit">Dalam Perjalanan (In Transit)</option>
            <option value="arrived">Tiba di Gudang Tujuan (Arrived)</option>
            <option value="delivered">Selesai Diterima Pembeli (Delivered)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="label">Lokasi Saat Ini:</label>
          <input v-model="modalStatus.location" type="text" class="input" placeholder="Gudang Distribusi Malang / Tol Cipali KM 120" />
        </div>

        <div class="form-group">
          <label class="label">Keterangan / Catatan Perjalanan:</label>
          <textarea v-model="modalStatus.description" rows="2" class="textarea" placeholder="Muatan telah dicek, temperatur terjaga."></textarea>
        </div>

        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
          <button class="btn btn-secondary" @click="modalStatus.show = false">Batal</button>
          <button class="btn btn-primary" @click="executeStatusUpdate">Simpan Status</button>
        </div>
      </div>
    </div>

    <!-- MODAL: Add Vehicle -->
    <div v-if="showAddVehicleModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 999; padding: 1rem;">
      <div class="card" style="width: 100%; max-width: 480px;">
        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">Registrasi Armada Baru</h3>
        <form @submit.prevent="submitNewVehicle">
          <div class="form-group">
            <label class="label">Tipe / Model Kendaraan</label>
            <input v-model="newVehicle.model" type="text" class="input" required placeholder="Truk Engkel Pendingin / Isuzu Elf" />
          </div>
          <div class="grid grid-2" style="gap: 0.5rem;">
            <div class="form-group">
              <label class="label">Nomor Polisi</label>
              <input v-model="newVehicle.plate_number" type="text" class="input" required placeholder="B 1234 TNY" />
            </div>
            <div class="form-group">
              <label class="label">Kapasitas Muat (kg)</label>
              <input v-model.number="newVehicle.max_capacity_kg" type="number" class="input" required min="100" />
            </div>
          </div>
          <div class="grid grid-2" style="gap: 0.5rem;">
            <div class="form-group">
              <label class="label">Nama Pengemudi</label>
              <input v-model="newVehicle.driver_name" type="text" class="input" required placeholder="Supriyadi" />
            </div>
            <div class="form-group">
              <label class="label">No. Telepon Pengemudi</label>
              <input v-model="newVehicle.driver_phone" type="tel" class="input" required placeholder="08198765432" />
            </div>
          </div>
          <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
            <button type="button" class="btn btn-secondary" @click="showAddVehicleModal = false">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan Armada</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { api, idr } from '../../services/api';

const auth = useAuthStore();

const activeTab = ref('curation');
const pendingCuration = ref([]);
const shipments = ref([]);
const vehicles = ref([]);

const loadingCuration = ref(true);
const loadingShipments = ref(false);
const noticeMessage = ref('');
const errorMessage = ref('');

// Modals
const showAddVehicleModal = ref(false);
const newVehicle = reactive({
  model: '',
  plate_number: '',
  max_capacity_kg: 2500,
  driver_name: '',
  driver_phone: '',
});

const modalAssign = reactive({
  show: false,
  shipment: null,
  vehicle_id: '',
});

const modalStatus = reactive({
  show: false,
  shipment: null,
  status: 'in_transit',
  location: '',
  description: '',
});

const getGradeBadge = (grade) => {
  if (grade === 'Grade A') return 'badge-green';
  if (grade === 'Grade B') return 'badge-blue';
  return 'badge-amber';
};

const getShipmentBadge = (status) => {
  switch (status) {
    case 'delivered': return 'badge-green';
    case 'in_transit': return 'badge-blue';
    case 'loading': return 'badge-amber';
    default: return 'badge-gray';
  }
};

const formatDate = (iso) => {
  if (!iso) return '-';
  return new Date(iso).toLocaleDateString('id-ID');
};

const loadCuration = async () => {
  loadingCuration.value = true;
  try {
    const res = await api.get('/curation/queue');
    pendingCuration.value = Array.isArray(res) ? res : (res.commodities || []);
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memuat kurasi komoditas.';
  } finally {
    loadingCuration.value = false;
  }
};

const loadShipments = async () => {
  loadingShipments.value = true;
  try {
    const res = await api.get('/logistics/shipments');
    shipments.value = Array.isArray(res) ? res : (res.shipments || []);
  } catch (err) {
    console.error('Failed to load shipments:', err);
  } finally {
    loadingShipments.value = false;
  }
};

const loadVehicles = async () => {
  try {
    const res = await api.get('/logistics/vehicles');
    vehicles.value = Array.isArray(res) ? res : (res.vehicles || []);
  } catch (err) {
    console.error('Failed to load vehicles:', err);
  }
};

const decideCuration = async (id, decision) => {
  try {
    await api.post(`/curation/${id}/decision`, {
      approve: decision === 'approved',
      note: decision === 'approved' ? 'Telah memenuhi standar mutu wilayah.' : 'Mutu belum memenuhi standar distribusi.',
    });
    noticeMessage.value = `Komoditas berhasil di-${decision === 'approved' ? 'setujui dan tayang di katalog' : 'tolak'}.`;
    await loadCuration();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memproses kurasi.';
  }
};

const openAssignModal = (shipment) => {
  modalAssign.shipment = shipment;
  modalAssign.vehicle_id = vehicles.value[0]?.id || '';
  modalAssign.show = true;
};

const executeAssignVehicle = async () => {
  if (!modalAssign.shipment || !modalAssign.vehicle_id) return;
  try {
    await api.post('/logistics/shipments', {
      orderId: modalAssign.shipment.order_id || modalAssign.shipment.id,
      shipmentId: modalAssign.shipment.id,
      vehicleId: modalAssign.vehicle_id,
    });
    modalAssign.show = false;
    noticeMessage.value = 'Armada berhasil ditugaskan untuk pengiriman ini.';
    await loadShipments();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal menugaskan armada.';
  }
};

const openStatusModal = (shipment) => {
  modalStatus.shipment = shipment;
  modalStatus.status = 'in_transit';
  modalStatus.location = '';
  modalStatus.description = '';
  modalStatus.show = true;
};

const executeStatusUpdate = async () => {
  if (!modalStatus.shipment) return;
  try {
    await api.patch(`/logistics/shipments/${modalStatus.shipment.id}/status`, {
      status: modalStatus.status,
      note: modalStatus.description || `Status diperbarui ke ${modalStatus.status}`,
    });
    modalStatus.show = false;
    noticeMessage.value = 'Status pengiriman dan event perjalanan berhasil diperbarui.';
    await loadShipments();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal memperbarui status pengiriman.';
  }
};

const submitNewVehicle = async () => {
  try {
    await api.post('/logistics/vehicles', {
      plate: newVehicle.plate_number,
      type: newVehicle.model,
      capacityKg: newVehicle.max_capacity_kg,
      driverName: newVehicle.driver_name,
    });
    showAddVehicleModal.value = false;
    noticeMessage.value = 'Armada kendaraan baru berhasil didaftarkan!';
    await loadVehicles();
  } catch (err) {
    errorMessage.value = err.message || 'Gagal mendaftarkan armada.';
  }
};

onMounted(() => {
  loadCuration();
  loadShipments();
  loadVehicles();
});
</script>
