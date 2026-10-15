<template>
  <div class="container" style="max-width: 800px; padding-top: 2rem; padding-bottom: 3rem;">
    <!-- Header -->
    <div style="text-align: center; margin-bottom: 2rem;">
      <h1 style="font-size: 2rem; font-weight: 800; color: var(--primary);">
        🚚 Pelacakan Distribusi Armada
      </h1>
      <p class="text-muted">
        Pantau pergerakan logistik hasil bumi dari gudang tengkulak wilayah hingga lokasi penerima secara real-time.
      </p>
    </div>

    <!-- Tracking Input Bar -->
    <div class="card" style="margin-bottom: 2rem;">
      <form @submit.prevent="searchTracking" style="display: flex; gap: 0.75rem;">
        <input
          v-model="trackingInput"
          type="text"
          class="input"
          placeholder="Masukkan Kode Resi Pelacakan (contoh: RT-TRK-...)"
          required
        />
        <button type="submit" class="btn btn-primary" :disabled="loading">
          <span v-if="loading">Melacak...</span>
          <span v-else>Lacak</span>
        </button>
      </form>
    </div>

    <!-- Error state -->
    <div v-if="error" class="alert alert-error">
      {{ error }}
    </div>

    <!-- Tracking Result -->
    <div v-if="shipment" class="card">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 1.25rem; margin-bottom: 1.5rem;">
        <div>
          <span class="badge badge-blue" style="margin-bottom: 0.35rem;">Nomor Resi</span>
          <h2 style="font-size: 1.35rem; font-weight: 800; font-family: monospace;">
            {{ shipment.tracking_code }}
          </h2>
          <div style="font-size: 0.85rem; color: var(--text-muted);">
            Invoice Terkait: <strong>{{ shipment.order?.order_number || '-' }}</strong>
          </div>
        </div>

        <div style="text-align: right;">
          <span class="badge" :class="getStatusBadge(shipment.status)" style="font-size: 0.85rem; padding: 0.35rem 0.85rem;">
            {{ getStatusLabel(shipment.status) }}
          </span>
          <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            Muatan: {{ shipment.actual_weight_kg || 0 }} kg
          </div>
        </div>
      </div>

      <!-- Visual Stepper Progress Bar -->
      <div style="margin: 2rem 0;">
        <div style="display: flex; justify-content: space-between; position: relative;">
          <!-- Connecting Line -->
          <div style="position: absolute; top: 18px; left: 5%; right: 5%; height: 4px; background: #e2e8f0; z-index: 1;"></div>
          <div
            :style="{
              position: 'absolute',
              top: '18px',
              left: '5%',
              width: `${stepperProgressPercent}%`,
              height: '4px',
              background: 'var(--primary)',
              zIndex: 2,
              transition: 'width 0.4s ease'
            }"
          ></div>

          <!-- Steps -->
          <div
            v-for="(st, idx) in steps"
            :key="st.key"
            style="position: relative; z-index: 3; text-align: center; flex: 1;"
          >
            <div
              :style="{
                width: '36px',
                height: '36px',
                borderRadius: '50%',
                margin: '0 auto',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontWeight: '700',
                fontSize: '0.85rem',
                background: isStepActive(st.key) ? 'var(--primary)' : '#ffffff',
                color: isStepActive(st.key) ? '#ffffff' : 'var(--text-muted)',
                border: isStepActive(st.key) ? '2px solid var(--primary)' : '2px solid #cbd5e1'
              }"
            >
              {{ isStepPassed(st.key) ? '✓' : idx + 1 }}
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; margin-top: 0.5rem; color: var(--text-main);">
              {{ st.label }}
            </div>
          </div>
        </div>
      </div>

      <!-- Route & Armada Details -->
      <div class="grid grid-2" style="gap: 1.25rem; margin-top: 2rem; background: #f8fafc; padding: 1.25rem; border-radius: var(--radius);">
        <div>
          <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
            📍 Rute Distribusi
          </div>
          <div style="font-size: 0.95rem; font-weight: 600; margin-top: 0.35rem;">
            {{ shipment.origin || shipment.origin_region?.name || 'Sentra Pertanian' }} → {{ shipment.destination || shipment.destination_region?.name || 'Gudang Pembeli' }}
          </div>
          <div style="font-size: 0.8rem; color: var(--text-muted);">
            Estimasi Tiba: {{ formatDate(shipment.estimated_arrival || shipment.created_at) }}
          </div>
        </div>

        <div>
          <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
            🚛 Informasi Armada & Supir
          </div>
          <div v-if="shipment.carrier || shipment.vehicle" style="font-size: 0.95rem; font-weight: 600; margin-top: 0.35rem;">
            {{ shipment.carrier?.model || shipment.vehicle?.model }} ({{ shipment.carrier?.plate || shipment.vehicle?.plate_number }})
            <div style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">
              Supir: {{ shipment.carrier?.driver || shipment.vehicle?.driver_name }} ({{ shipment.carrier?.phone || shipment.vehicle?.driver_phone }})
            </div>
          </div>
          <div v-else style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.35rem;">
            Armada sedang dialokasikan oleh koordinator logistik.
          </div>
        </div>
      </div>

      <!-- Timeline Event Logs -->
      <div style="margin-top: 2rem;">
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">
          Riwayat Perjalanan (Log Event)
        </h3>

        <div v-if="shipment.events && shipment.events.length > 0" style="border-left: 2px solid #e2e8f0; margin-left: 0.5rem; padding-left: 1.25rem;">
          <div
            v-for="ev in shipment.events"
            :key="ev.id || ev.timestamp"
            style="position: relative; margin-bottom: 1.25rem;"
          >
            <!-- Dot -->
            <div style="position: absolute; left: -1.65rem; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: var(--primary);"></div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">
              {{ formatDate(ev.timestamp || ev.created_at) }} • {{ ev.location || 'Pos Logistik' }}
            </div>
            <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-main); margin-top: 0.15rem;">
              {{ ev.note || ev.description }}
            </div>
          </div>
        </div>
        <div v-else style="font-size: 0.85rem; color: var(--text-muted);">
          Belum ada riwayat pergerakan tercatat.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../services/api';

const route = useRoute();

const trackingInput = ref(route.params.code || '');
const shipment = ref(null);
const loading = ref(false);
const error = ref('');

const steps = [
  { key: 'draft', label: 'Diproses' },
  { key: 'assigned', label: 'Armada Siap' },
  { key: 'loading', label: 'Pemuatan' },
  { key: 'in_transit', label: 'Dalam Perjalanan' },
  { key: 'arrived', label: 'Tiba di Tujuan' },
  { key: 'delivered', label: 'Selesai Diterima' },
];

const currentStepIndex = computed(() => {
  if (!shipment.value) return 0;
  const idx = steps.findIndex((s) => s.key === shipment.value.status);
  return idx >= 0 ? idx : 0;
});

const stepperProgressPercent = computed(() => {
  return (currentStepIndex.value / (steps.length - 1)) * 90;
});

const isStepActive = (key) => {
  const idx = steps.findIndex((s) => s.key === key);
  return idx <= currentStepIndex.value;
};

const isStepPassed = (key) => {
  const idx = steps.findIndex((s) => s.key === key);
  return idx < currentStepIndex.value;
};

const getStatusBadge = (status) => {
  switch (status) {
    case 'delivered': return 'badge-green';
    case 'in_transit': return 'badge-blue';
    case 'loading': return 'badge-amber';
    default: return 'badge-gray';
  }
};

const getStatusLabel = (status) => {
  const found = steps.find((s) => s.key === status);
  return found ? found.label : status;
};

const formatDate = (isoString) => {
  if (!isoString) return '-';
  return new Date(isoString).toLocaleString('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
};

const searchTracking = async () => {
  if (!trackingInput.value.trim()) return;
  loading.value = true;
  error.value = '';
  shipment.value = null;

  try {
    const res = await api.get(`/logistics/track/${trackingInput.value.trim()}`);
    shipment.value = res.shipment || res;
  } catch (err) {
    error.value = err.message || 'Resi pengiriman tidak ditemukan.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  if (route.params.code) {
    searchTracking();
  }
});
</script>
