import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import './style.css';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Fetch current authenticated user if token exists
import { useAuthStore } from './stores/auth';
const auth = useAuthStore(pinia);
if (auth.token) {
    auth.fetchMe();
}

app.mount('#app');
