import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import App from './App.vue';
import router from './router'; 

// Configuración de Axios para Monolito
axios.defaults.withCredentials = true;
// Eliminamos la baseURL fija. Se usará la del navegador automáticamente.
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router); 

app.mount('#app');