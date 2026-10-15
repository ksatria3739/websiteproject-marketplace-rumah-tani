<template>
  <div class="app-layout">
    <!-- Navigation Bar -->
    <header class="navbar">
      <div class="container nav-container">
        <router-link to="/" class="nav-brand">
          <span>🌾</span>
          <span>Rumah Tani</span>
        </router-link>

        <nav class="nav-links">
          <router-link to="/catalog" class="nav-link">Katalog</router-link>
          <router-link to="/track" class="nav-link">Lacak Armada</router-link>
          <router-link to="/articles" class="nav-link">Berita</router-link>

          <template v-if="auth.isAuthenticated">
            <router-link :to="auth.roleDashboardRoute" class="btn btn-secondary btn-sm">
              <span class="badge" :class="roleBadgeClass">{{ roleLabel }}</span>
              Dashboard
            </router-link>
            <button @click="handleLogout" class="btn btn-secondary btn-sm">Keluar</button>
          </template>
          <template v-else>
            <router-link to="/login" class="nav-link">Masuk</router-link>
            <router-link to="/register" class="btn btn-primary btn-sm">Daftar</router-link>
          </template>
        </nav>
      </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
      <router-view />
    </main>

    <!-- Footer -->
    <footer class="footer">
      <div class="container footer-content">
        <p>© 2026 Marketplace Rumah Tani — Solusi Rantai Pasok Pertanian Terintegrasi Hulu ke Hilir.</p>
        <p class="text-muted">Didukung oleh Laravel 12 RESTful API, MySQL Relational Database & Vue.js 3</p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const router = useRouter();

const roleLabel = computed(() => {
  switch (auth.role) {
    case 'super_admin': return 'Super Admin';
    case 'admin': return 'Admin Support L3';
    case 'admin_tengkulak': return 'Tengkulak';
    case 'petani': return 'Petani';
    case 'pembeli': return auth.user?.buyer_type === 'b2b' ? 'Pembeli B2B' : 'Pembeli B2C';
    default: return 'Tamu';
  }
});

const roleBadgeClass = computed(() => {
  switch (auth.role) {
    case 'super_admin': return 'badge-red';
    case 'admin': return 'badge-blue';
    case 'admin_tengkulak': return 'badge-blue';
    case 'petani': return 'badge-green';
    case 'pembeli': return 'badge-amber';
    default: return 'badge-gray';
  }
});

const handleLogout = () => {
  auth.logout();
  router.push('/login');
};
</script>

<style scoped>
.app-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.main-content {
  flex: 1;
  padding: 2rem 0;
}

.footer {
  background: #ffffff;
  border-top: 1px solid var(--border);
  padding: 2rem 0;
  text-align: center;
  font-size: 0.85rem;
}

.footer-content {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.text-muted {
  color: var(--text-muted);
}
</style>
