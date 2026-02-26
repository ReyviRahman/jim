import './bootstrap';
import flatpickr from "flatpickr";

// Jadikan global agar Alpine.js di file Blade bisa memanggil fungsi flatpickr()
window.flatpickr = flatpickr;