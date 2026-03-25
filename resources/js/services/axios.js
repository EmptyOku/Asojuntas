import axios from 'axios';

const instance = axios.create({
    baseURL: 'http://127.0.0.1:8000/api', // La URL de laravel para el desarrollo, para la finalidad del proyecto debe tener la ip o domino del vpn
    withCredentials: true,                // Permite enviar cookies/tokens
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
});

export default instance;