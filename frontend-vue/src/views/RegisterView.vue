<template>
  <div class="container" style="max-width: 600px; margin: 2rem auto;">
    <div class="card">
      <div style="text-align: center; margin-bottom: 1.5rem;">
        <span style="font-size: 2.5rem;">🌱</span>
        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-top: 0.5rem;">
          Daftar Akun Rumah Tani
        </h2>
        <p class="text-muted" style="font-size: 0.875rem;">
          Bergabunglah dalam ekosistem digitalisasi rantai pasok pertanian Indonesia
        </p>
      </div>

      <div v-if="errorMessage" class="alert alert-error">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleRegister">
        <!-- Role Selection -->
        <div class="form-group">
          <label class="label">Pilih Peran Akun Anda</label>
          <div class="grid grid-3" style="gap: 0.5rem;">
            <button
              type="button"
              class="btn"
              :class="form.role === 'petani' ? 'btn-primary' : 'btn-secondary'"
              @click="form.role = 'petani'"
            >
              🧑‍🌾 Petani
            </button>
            <button
              type="button"
              class="btn"
              :class="form.role === 'admin_tengkulak' ? 'btn-primary' : 'btn-secondary'"
              @click="form.role = 'admin_tengkulak'"
            >
              🏢 Tengkulak
            </button>
            <button
              type="button"
              class="btn"
              :class="form.role === 'pembeli' ? 'btn-primary' : 'btn-secondary'"
              @click="form.role = 'pembeli'"
            >
              🛒 Pembeli
            </button>
          </div>
        </div>

        <!-- Buyer Type Selection -->
        <div v-if="form.role === 'pembeli'" class="form-group" style="background: #f8fafc; padding: 1rem; border-radius: var(--radius);">
          <label class="label">Tipe Pembeli</label>
          <div style="display: flex; gap: 1.5rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
              <input type="radio" value="b2c" v-model="form.buyer_type" />
              <span>B2C (Konsumen Perorangan)</span>
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
              <input type="radio" value="b2b" v-model="form.buyer_type" />
              <span>B2B (Restoran / Hotel / Pabrik)</span>
            </label>
          </div>
        </div>

        <div v-if="(form.buyer_type === 'b2b' && form.role === 'pembeli') || form.role === 'admin_tengkulak'" class="form-group">
          <label class="label">
            {{ form.role === 'admin_tengkulak' ? 'Nama Usaha / Gudang Distribusi Tengkulak' : 'Nama Perusahaan / Institusi' }}
          </label>
          <input
            v-model="form.company_name"
            type="text"
            class="input"
            :required="form.buyer_type === 'b2b' && form.role === 'pembeli'"
            :placeholder="form.role === 'admin_tengkulak' ? 'Contoh: CV Mandiri Logistik Tani / UD Sumber Rejeki' : 'PT Berkah Boga Mandiri / Grand Mercure Hotel'"
          />
        </div>

        <div class="form-group">
          <label class="label">Nama Lengkap</label>
          <input
            v-model="form.name"
            type="text"
            class="input"
            required
            placeholder="Ahmad Subarjo"
          />
        </div>

        <div class="grid grid-2" style="gap: 1rem;">
          <div class="form-group">
            <label class="label">Alamat Email</label>
            <input
              v-model="form.email"
              type="email"
              class="input"
              required
              placeholder="ahmad@gmail.com"
            />
          </div>

          <div class="form-group">
            <label class="label">Nomor WhatsApp / Telepon</label>
            <input
              v-model="form.phone"
              type="tel"
              class="input"
              required
              placeholder="08123456789"
            />
          </div>
        </div>

        <div class="grid grid-2" style="gap: 1rem;">
          <div class="form-group">
            <label class="label">Kata Sandi</label>
            <input
              v-model="form.password"
              type="password"
              class="input"
              required
              placeholder="Minimal 8 karakter"
            />
          </div>

          <div class="form-group">
            <label class="label">Konfirmasi Sandi</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              class="input"
              required
              placeholder="Ketik ulang kata sandi"
            />
          </div>
        </div>

        <div class="form-group">
          <label class="label">Wilayah Domisili / Operasional</label>
          <select v-model="form.region_id" class="select" required>
            <option value="" disabled>-- Pilih Wilayah --</option>
            <option v-for="r in regions" :key="r.id" :value="r.id">
              {{ r.name }} ({{ r.province }})
            </option>
          </select>
        </div>

        <div class="form-group">
          <label class="label">Alamat Lengkap</label>
          <textarea
            v-model="form.address"
            rows="2"
            class="textarea"
            placeholder="Nama jalan, nomor gudang / kebun, RT/RW, kecamatan"
          ></textarea>
        </div>

        <button
          type="submit"
          class="btn btn-primary"
          style="width: 100%; margin-top: 0.5rem;"
          :disabled="auth.loading"
        >
          <span v-if="auth.loading">Mendaftarkan Akun...</span>
          <span v-else>Daftar Sekarang</span>
        </button>
      </form>

      <div style="margin-top: 1.25rem; text-align: center; font-size: 0.875rem;">
        Sudah memiliki akun?
        <router-link to="/login" style="color: var(--primary); font-weight: 600;">
          Masuk ke sini
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { api } from '../services/api';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({
  role: 'petani',
  buyer_type: 'b2c',
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  company_name: '',
  region_id: '',
  address: '',
});

const regions = ref([]);
const errorMessage = ref('');

onMounted(async () => {
  try {
    const meta = await api.get('/public/meta');
    regions.value = meta.regions || [];
    if (regions.value.length > 0) {
      form.region_id = regions.value[0].id;
    }
  } catch (err) {
    console.error('Failed to load regions:', err);
  }
});

const handleRegister = async () => {
  errorMessage.value = '';
  if (form.password !== form.password_confirmation) {
    errorMessage.value = 'Konfirmasi kata sandi tidak cocok.';
    return;
  }

  try {
    await auth.register(form);
    router.push(auth.roleDashboardRoute);
  } catch (err) {
    errorMessage.value = err.message || 'Registrasi gagal. Cek data Anda.';
  }
};
</script>
