import { createApp } from 'vue'
import { createPinia } from 'pinia';
import axios from 'axios';
import './style.css'
import App from './App.vue'
import router from './router';

// Configuración Global de Axios
axios.defaults.withCredentials = true; // OBLIGATORIO para Sanctum
axios.defaults.baseURL = 'http://localhost:8000'; // La URL del backend de tu compañero
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

createApp(App).mount('#app')
