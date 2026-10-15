<template>
  <div class="container" style="max-width: 520px; margin: 2rem auto;">
    <div class="card">
      <div style="text-align: center; margin-bottom: 1.5rem;">
        <span style="font-size: 2.5rem;">🌾</span>
        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-top: 0.5rem;">
          Masuk ke Rumah Tani
        </h2>
        <p class="text-muted" style="font-size: 0.875rem;">
          Platform Rantai Pasok Pertanian Terintegrasi B2B & B2C
        </p>
      </div>

      <div v-if="errorMessage" class="alert alert-error">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label class="label">Alamat Email</label>
          <input
            v-model="form.email"
            type="email"
            class="input"
            required
            placeholder="nama@domain.com"
          />
        </div>

        <div class="form-group">
          <label class="label">Kata Sandi</label>
          <input
            v-model="form.password"
            type="password"
            class="input"
            required
            placeholder="••••••••"
          />
        </div>

        <button
          type="submit"
          class="btn btn-primary"
          style="width: 100%; margin-top: 0.5rem;"
          :disabled="auth.loading"
        >
          <span v-if="auth.loading">Memproses...</span>
          <span v-else>Masuk Sekarang</span>
        </button>
      </form>

      <div style="margin-top: 1.25rem; text-align: center; font-size: 0.875rem;">
        Belum punya akun?
        <router-link to="/register" style="color: var(--primary); font-weight: 600;">
          Daftar di sini
        </router-link>
      </div>

      <!-- Quick Role Switcher for Live Demo & Verification -->
      <div style="margin-top: 2rem; border-top: 1px dashed var(--border); padding-top: 1.25rem;">
        <p style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem; text-align: center;">
          ⚡ Quick Demo Login (5-Level RBAC)
        </p>
        <div class="grid grid-2" style="gap: 0.5rem;">
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            @click="quickLogin('admin@rumahtani.id', 'Password123')"
          >
            🔴 Super Admin
          </button>
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            @click="quickLogin('support@rumahtani.id', 'Password123')"
          >
            🛡️ Admin Support L3
          </button>
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            @click="quickLogin('tengkulak@rumahtani.id', 'Password123')"
          >
            🔵 Tengkulak / Distribusi
          </button>
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            @click="quickLogin('petani@rumahtani.id', 'Password123')"
          >
            🟢 Petani Produsen
          </button>
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            @click="quickLogin('hotel@rumahtani.id', 'Password123')"
          >
            🟡 Pembeli B2B (Hotel)
          </button>
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            @click="quickLogin('umum@rumahtani.id', 'Password123')"
          >
            ⚪ Pembeli B2C (Umum)
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = reactive({
  email: '',
  password: '',
});

const errorMessage = ref('');

const handleLogin = async () => {
  errorMessage.value = '';
  try {
    await auth.login(form.email, form.password);
    const redirect = route.query.redirect;
    if (redirect) {
      router.push(redirect);
    } else {
      router.push(auth.roleDashboardRoute);
    }
  } catch (err) {
    errorMessage.value = err.message || 'Login gagal. Periksa email dan password.';
  }
};

const quickLogin = async (email, password) => {
  form.email = email;
  form.password = password;
  await handleLogin();
};
</script>
