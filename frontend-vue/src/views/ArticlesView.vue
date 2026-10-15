<template>
  <div class="container" style="padding-top: 2rem; padding-bottom: 3rem;">
    <!-- Header -->
    <div style="text-align: center; max-width: 650px; margin: 0 auto 2.5rem auto;">
      <span class="badge badge-green" style="margin-bottom: 0.5rem;">Kabar & Edukasi Pertanian</span>
      <h1 style="font-size: 2rem; font-weight: 800; color: var(--primary);">
        Wawasan Agrikultur Indonesia
      </h1>
      <p class="text-muted">
        Informasi pasar komoditas terkini, standarisasi mutu panen, dan tips rantai pasok modern dari para pakar.
      </p>
    </div>

    <div v-if="loading" style="text-align: center; padding: 4rem 0;">
      <p class="text-muted">Memuat artikel...</p>
    </div>

    <div v-else-if="articles.length === 0" style="text-align: center; padding: 3rem 0;">
      <p class="text-muted">Belum ada artikel yang dipublikasikan.</p>
    </div>

    <div v-else class="grid grid-3">
      <div
        v-for="art in articles"
        :key="art.id"
        class="card"
        style="display: flex; flex-direction: column; justify-content: space-between;"
      >
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span class="badge badge-blue">{{ art.category || 'Edukasi' }}</span>
            <span style="font-size: 0.75rem; color: var(--text-muted);">
              👁 {{ art.view_count || 0 }} views
            </span>
          </div>

          <h2 style="font-size: 1.15rem; font-weight: 700; line-height: 1.4; margin-bottom: 0.5rem;">
            <router-link :to="`/articles/${art.slug}`" style="color: var(--text-main); text-decoration: none;">
              {{ art.title }}
            </router-link>
          </h2>

          <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1rem;">
            {{ art.excerpt || 'Baca selengkapnya mengenai perkembangan agribisnis dan teknologi hasil tani terkini...' }}
          </p>
        </div>

        <div style="border-top: 1px solid var(--border); padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 0.75rem; color: var(--text-muted);">
            Oleh: {{ art.author?.name || 'Redaksi Rumah Tani' }}
          </span>
          <router-link :to="`/articles/${art.slug}`" class="btn btn-secondary btn-sm">
            Baca →
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { api } from '../services/api';

const articles = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const res = await api.get('/public/articles');
    articles.value = Array.isArray(res) ? res : (res.articles || []);
  } catch (err) {
    console.error('Failed to load articles:', err);
  } finally {
    loading.value = false;
  }
});
</script>
