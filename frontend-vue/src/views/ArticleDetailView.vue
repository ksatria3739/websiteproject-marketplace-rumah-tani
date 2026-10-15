<template>
  <div class="container" style="max-width: 780px; padding-top: 1.5rem; padding-bottom: 3rem;">
    <div style="margin-bottom: 1.5rem;">
      <router-link to="/articles" class="btn btn-secondary btn-sm">
        ← Kembali ke Daftar Artikel
      </router-link>
    </div>

    <div v-if="loading" style="text-align: center; padding: 4rem 0;">
      <p class="text-muted">Memuat artikel...</p>
    </div>

    <div v-else-if="error" class="alert alert-error">
      {{ error }}
    </div>

    <article v-else-if="article" class="card">
      <div style="margin-bottom: 1rem;">
        <span class="badge badge-green">{{ article.category || 'Wawasan' }}</span>
        <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: 0.75rem;">
          Dipublikasikan: {{ formatDate(article.published_at || article.created_at) }}
        </span>
      </div>

      <h1 style="font-size: 2rem; font-weight: 800; line-height: 1.3; margin-bottom: 1rem; color: var(--text-main);">
        {{ article.title }}
      </h1>

      <div style="display: flex; align-items: center; gap: 0.75rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border); margin-bottom: 1.5rem;">
        <div style="width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
          ✍️
        </div>
        <div>
          <div style="font-weight: 700; font-size: 0.9rem;">{{ article.author?.name || 'Redaksi Rumah Tani' }}</div>
          <div style="font-size: 0.75rem; color: var(--text-muted);">Divisi Edukasi Rantai Pasok Pertanian</div>
        </div>
      </div>

      <div style="font-size: 1.05rem; line-height: 1.8; color: #334155; white-space: pre-line;">
        {{ article.content }}
      </div>
    </article>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../services/api';

const route = useRoute();
const article = ref(null);
const loading = ref(true);
const error = ref('');

const formatDate = (iso) => {
  if (!iso) return '-';
  return new Date(iso).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  });
};

onMounted(async () => {
  try {
    const res = await api.get(`/public/articles/${route.params.slug}`);
    article.value = res.article || res;
  } catch (err) {
    error.value = err.message || 'Artikel tidak ditemukan.';
  } finally {
    loading.value = false;
  }
});
</script>
