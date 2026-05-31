import './bootstrap';
import flatpickr from "flatpickr";
import Chart from 'chart.js/auto';

// Jadikan global agar Alpine.js di file Blade bisa memanggil fungsi flatpickr() dan Chart
window.Chart = Chart;
window.flatpickr = flatpickr;

// Persist sidebar scroll position across wire:navigate
document.addEventListener('livewire:navigating', () => {
    const sidebar = document.querySelector('#top-bar-sidebar div.overflow-y-auto');
    if (sidebar) {
        sessionStorage.setItem('sidebar_scroll', sidebar.scrollTop);
    }
});

document.addEventListener('livewire:navigated', () => {
    const sidebar = document.querySelector('#top-bar-sidebar div.overflow-y-auto');
    if (sidebar) {
        const saved = sessionStorage.getItem('sidebar_scroll');
        if (saved !== null) {
            sidebar.scrollTop = parseInt(saved, 10);
        }
    }
});