<?php
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts::empty')] #[Title('Frans GYM | Pusat Kebugaran Terbaik di Jambi')] class extends Component 
{
    public function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirect('/', navigate: true);
    }
    
    public function with(): array
    {
        // Ambil semua PT aktif
        $trainers = User::where('role', 'pt')->where('is_active', true)->get();
        
        return [
            // Membagi pelatih menjadi kelompok berisi maksimal 3 orang per "Halaman Slide"
            'trainerPages' => $trainers->chunk(3) 
        ];
    }
};
?>

<div class="bg-neutral-primary-soft min-h-screen">
    <nav x-data="{
            activeSection: 'beranda',
            initObserver() {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.activeSection = entry.target.id;
                        }
                    });
                }, { rootMargin: '-40% 0px -60% 0px' }); // Mendeteksi saat bagian tersebut menyentuh tengah layar
                
                // Pantau semua elemen yang punya ID
                document.querySelectorAll('div[id], footer[id]').forEach((el) => {
                    observer.observe(el);
                });
            }
        }"
        x-init="initObserver()"
        class="bg-[#34342F] fixed w-full z-20 top-0 start-0 border-b border-gray-800 transition-all duration-300">
        
        <div class="max-w-7xl w-full flex flex-wrap items-center mx-auto p-4">
            
            <a href="/" class="flex items-center space-x-3 rtl:space-x-reverse">
                <img src="{{ asset('icon.png') }}" class="h-7" alt="Frans GYM Logo" />
                <span class="self-center text-xl text-brand font-semibold whitespace-nowrap">Frans GYM</span>
            </a>
            
            <div class="flex items-center md:order-2 space-x-3 md:space-x-0 rtl:space-x-reverse ms-auto md:ms-6">
                @auth
                    <button type="button" class="flex text-sm bg-[#34342F] rounded-full md:me-3 focus:ring-4 focus:ring-brand" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown" data-dropdown-placement="bottom">
                        <span class="sr-only">Open user menu</span>
                        <img class="w-8 h-8 rounded-full object-cover" src="{{ asset('storage/' . Auth::user()->photo) }}" alt="{{ Auth::user()->name }}">
                    </button>
                    
                    <div class="z-50 hidden bg-[#34342F] border border-default-medium rounded-base shadow-lg w-44" id="user-dropdown">
                        <div class="px-4 py-3 text-sm border-b border-default">
                            <span class="block text-brand font-medium">{{ Auth::user()->name }}</span>
                            <span class="block text-white truncate">{{ Auth::user()->email }}</span>
                        </div>
                        <ul class="p-2 text-sm text-white font-medium" aria-labelledby="user-menu-button">
                            <li>
                                <a href="{{ match(Auth::user()->role) { 'admin' => route('admin.packages.index'), 'pt' => route('pt.absensi'), default => route('member.dashboard') } }}" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-brand rounded transition-colors">Dashboard</a>
                            </li>
                            <li>
                                <a href="#" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-brand rounded transition-colors">Settings</a>
                            </li>
                            <li>
                                <button type="button" wire:click="logout" wire:loading.attr="disabled" wire:target="logout" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-brand rounded disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    <span wire:loading.remove wire:target="logout">Sign out</span>
                                    <span wire:loading wire:target="logout" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Signing out...
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>
                @endauth

                <button data-collapse-toggle="navbar-user" type="button" class="inline-flex items-center p-2 w-10 h-10 justify-center text-sm text-brand rounded-base md:hidden hover:bg-neutral-secondary-soft hover:text-heading focus:outline-none focus:ring-2 focus:ring-neutral-tertiary" aria-controls="navbar-user" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M5 7h14M5 12h14M5 17h14" /></svg>
                </button>
            </div>
            
            <div class="items-center justify-between hidden w-full md:flex md:w-auto md:order-1 md:ms-auto" id="navbar-user">
                <ul class="font-medium flex flex-col p-4 md:p-0 mt-4 border border-default rounded-base bg-white md:flex-row md:space-x-8 rtl:space-x-reverse md:mt-0 md:border-0 md:bg-[#34342F]">
                    <li>
                        <a href="#beranda"
                            :class="activeSection === 'beranda' ? 'text-brand md:text-brand font-bold' : 'text-[#34342F] md:text-white'"
                            class="block py-2 px-3 rounded md:p-0 hover:bg-neutral-tertiary md:hover:bg-transparent md:hover:text-brand transition-colors">
                            Beranda
                        </a>
                    </li>
                    <li>
                        <a href="#tentang"
                            :class="activeSection === 'tentang' ? 'text-brand md:text-brand font-bold' : 'text-[#34342F] md:text-white'"
                            class="block py-2 px-3 rounded md:p-0 hover:bg-neutral-tertiary md:hover:bg-transparent md:hover:text-brand transition-colors">
                            Tentang Kami
                        </a>
                    </li>
                    <li>
                        <a href="#pelatih"
                            :class="activeSection === 'pelatih' ? 'text-brand md:text-brand font-bold' : 'text-[#34342F] md:text-white'"
                            class="block py-2 px-3 rounded md:p-0 hover:bg-neutral-tertiary md:hover:bg-transparent md:hover:text-brand transition-colors">
                            Tim Pelatih
                        </a>
                    </li>
                    <li>
                        <a href="#pencapaian"
                            :class="activeSection === 'pencapaian' ? 'text-brand md:text-brand font-bold' : 'text-[#34342F] md:text-white'"
                            class="block py-2 px-3 rounded md:p-0 hover:bg-neutral-tertiary md:hover:bg-transparent md:hover:text-brand transition-colors">
                            Pencapaian
                        </a>
                    </li>
                    
                    @guest
                        <li>
                            <a href="/pendaftaran/member" wire:navigate
                                class="block py-2 px-3 rounded md:p-0 md:border-0 text-[#34342F] md:text-white md:hover:text-brand hover:bg-neutral-tertiary md:hover:bg-transparent transition-colors">
                                Daftar
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('login') }}" wire:navigate
                                class="block py-2 px-3 rounded md:p-0 md:border-0 text-[#34342F] md:text-white hover:bg-neutral-tertiary md:hover:bg-transparent md:hover:text-brand transition-colors">
                                Login
                            </a>
                        </li>
                    @endguest
                </ul>
            </div>
            
        </div>
    </nav>
    
    <div id="beranda" class="relative min-h-[600px] flex items-center pt-16">
        <div class="absolute inset-0 overflow-hidden">
            <img class="w-full h-full object-cover object-[center_70%]" 
                 src="{{ asset('bg-home.png') }}" 
                 alt="Suasana Frans Gym">
            <div class="absolute inset-0 bg-neutral-900/60 mix-blend-multiply"></div>
        </div>
        
        <div class="relative max-w-7xl mx-auto py-24 px-4 sm:py-32 sm:px-6 lg:px-8 flex flex-col items-start text-left">
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl md:w-3/4 lg:w-2/3">
                Bentuk Tubuh Idealmu Bersama <span class="text-brand">Frans GYM</span>
            </h1>
            <p class="mt-6 max-w-2xl text-xl text-gray-300">
                Pusat kebugaran terlengkap di Jambi dengan peralatan modern, lingkungan yang suportif, dan Personal Trainer profesional yang siap membantu mencapai target fitnesmu.
            </p>
            <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-start">
                <a href="/pendaftaran/member" wire:navigate class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-black bg-brand hover:bg-brand-strong transition-colors shadow-lg w-full sm:w-auto">
                    Daftar Member Sekarang
                </a>
            </div>
        </div>
    </div>

    <div id="tentang" class="py-16 sm:py-24 bg-white scroll-mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                
                <div class="mb-10 lg:mb-0 relative">
                    <div class="relative rounded-2xl overflow-hidden shadow-xl aspect-w-4 aspect-h-3 sm:aspect-w-16 sm:aspect-h-9 lg:aspect-none lg:h-full">
                        <img class="w-full h-full object-cover rounded-2xl lg:h-[500px]" 
                             src="{{ asset('ruangan.png') }}" 
                             alt="Fasilitas dan Komunitas Frans Gym">
                    </div>
                    <div class="hidden lg:block absolute -bottom-6 -right-6 w-32 h-32 bg-brand rounded-full opacity-20 -z-10"></div>
                    <div class="hidden lg:block absolute -top-6 -left-6 w-24 h-24 bg-brand-strong rounded-xl opacity-20 -z-10"></div>
                </div>
                
                <div class="flex flex-col justify-center">
                    <h2 class="text-base font-semibold text-brand tracking-wide uppercase">Tentang Kami</h2>
                    <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        Membangun Gaya Hidup Sehat & Aktif
                    </p>
                    <div class="mt-6 text-lg text-gray-600 space-y-6 leading-relaxed">
                        <p>
                            <strong>Frans Gym</strong> adalah pusat kebugaran di Jambi yang siap membantu kamu mencapai kesehatan dan kebugaran optimal. Dengan fasilitas lengkap, suasana nyaman, dan program latihan untuk semua tingkat, hadir untuk mendukung perjalanan kebugaran kamu.
                        </p>
                        <p>
                            Tim pelatih profesional kami siap membimbing kamu dengan metode latihan yang efektif dan aman, sesuai dengan kebutuhan dan tujuan kamu. Di Frans Gym, kami tidak hanya menyediakan ruang untuk berolahraga, tetapi juga membangun komunitas yang mendukung gaya hidup sehat dan aktif.
                        </p>
                    </div>
                    
                    <div class="mt-10 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-gray-100 pt-8">
                        <div>
                            <span class="block text-2xl font-bold text-brand">Fasilitas Lengkap</span>
                            <span class="block text-sm text-gray-500 mt-1">Alat modern & terawat</span>
                        </div>
                        <div>
                            <span class="block text-2xl font-bold text-brand">Coach Profesional</span>
                            <span class="block text-sm text-gray-500 mt-1">Siap membimbing targetmu</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="pelatih" class="bg-neutral-primary py-16 sm:py-24 border-t border-default-medium overflow-hidden scroll-mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-base font-semibold text-brand tracking-wide uppercase">Tim Pelatih Kami</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-heading sm:text-4xl">
                    Kenalan dengan Personal Trainer Profesional
                </p>
                <p class="mt-4 text-lg text-body">
                    Capai target fitnesmu lebih cepat dan aman dengan bimbingan langsung dari ahli yang berpengalaman.
                </p>
            </div>

            @if($trainerPages->count() > 0)
                <div x-data="{
                        activePage: 0,
                        totalPages: {{ $trainerPages->count() }},
                        next() { this.activePage = (this.activePage + 1) % this.totalPages },
                        prev() { this.activePage = (this.activePage === 0) ? this.totalPages - 1 : this.activePage - 1 }
                    }" 
                    class="relative w-full"
                >
                    <button @click="prev()" class="absolute left-0 top-1/2 -translate-y-1/2 z-10 -ml-4 md:-ml-6 bg-[#34342F] border border-default text-brand w-12 h-12 rounded-full flex items-center justify-center shadow-md hover:bg-brand hover:text-white transition-colors focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    </button>

                    <div class="overflow-hidden px-2 py-4">
                        <div class="flex transition-transform duration-700 ease-in-out" :style="`transform: translateX(-${activePage * 100}%)`">
                            
                            @foreach($trainerPages as $pageIndex => $chunk)
                                <div class="w-full flex-shrink-0 px-2 md:px-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                                        @foreach($chunk as $pt)
                                            <div class="bg-white rounded-2xl shadow-sm border border-default overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col">
                                                <div class="h-64 bg-gray-200 relative overflow-hidden group">
                                                    @if($pt->photo)
                                                        <img src="{{ asset('storage/' . $pt->photo) }}" alt="{{ $pt->name }}" class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-500">
                                                    @else
                                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($pt->name) }}&background=random&size=400" alt="{{ $pt->name }}" class="w-full h-full object-cover">
                                                    @endif
                                                </div>
                                                <div class="p-6 text-center flex-1 flex flex-col justify-center">
                                                    <h3 class="text-xl font-bold text-heading">{{ $pt->name }}</h3>
                                                    
                                                    <div class="w-12 h-1 bg-brand mx-auto my-4 rounded-full opacity-50"></div>
                                                    <p class="text-sm text-body line-clamp-3">Siap membantu merancang program latihan yang disesuaikan dengan tujuan kebugaran kamu.</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                        </div>
                    </div>

                    <button @click="next()" class="absolute right-0 top-1/2 -translate-y-1/2 z-10 -mr-4 md:-mr-6 bg-[#34342F] border border-default text-brand w-12 h-12 rounded-full flex items-center justify-center shadow-md hover:bg-brand hover:text-white transition-colors focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>

                    <div class="flex justify-center items-center space-x-3 mt-10">
                        <template x-for="i in totalPages" :key="i">
                            <button @click="activePage = i - 1" 
                                    class="h-2 rounded-full transition-all duration-300 focus:outline-none"
                                    :class="activePage === i - 1 ? 'w-8 bg-brand' : 'w-2 bg-gray-300 hover:bg-gray-400'">
                            </button>
                        </template>
                    </div>

                </div>
            @else
                <div class="text-center py-10 bg-white rounded-xl border border-default">
                    <p class="text-body font-medium">Data Personal Trainer sedang diperbarui.</p>
                </div>
            @endif
            
        </div>
    </div>

    <div id="pencapaian" class="bg-[#222222] py-16 sm:py-24 scroll-mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-12">
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white tracking-wide leading-snug">
                    Pencapaian Yang Bisa Kamu Raih <br class="hidden sm:block" />
                    Bersama <span class="text-[#FFD700]">FRANS GYM</span> </h2>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3">
                
                <div class="p-8 text-center border-b border-[#FFD700] lg:border-r flex flex-col items-center">
                    <img src="{{ asset('loss-weight.svg') }}" class="w-12 h-12 text-white mb-6"/>
                    <h3 class="text-lg font-bold text-white tracking-widest uppercase mb-3">Loss Weight</h3>
                    <p class="text-sm text-white leading-relaxed">
                        Turunkan berat badan secara efektif dengan latihan dan pola makan terarah.
                    </p>
                </div>

                <div class="p-8 text-center border-b border-[#FFD700] lg:border-r flex flex-col items-center">
                    <img src="{{ asset('body-shaping.svg') }}" class="w-12 h-12 text-white mb-6"/>
                    <h3 class="text-lg font-bold text-white tracking-widest uppercase mb-3">Body Shaping</h3>
                    <p class="text-sm text-white leading-relaxed">
                        Bentuk tubuh ideal yang proporsional dan estetis.
                    </p>
                </div>

                <div class="p-8 text-center border-b border-[#FFD700] flex flex-col items-center">
                    <img src="{{ asset('muscle-tone.svg') }}" class="w-12 h-12 text-white mb-6"/>
                    <h3 class="text-lg font-bold text-white tracking-widest uppercase mb-3">Muscle Tone</h3>
                    <p class="text-sm text-white leading-relaxed">
                        Perkuat dan perindah definisi otot tubuh Anda.
                    </p>
                </div>

                <div class="p-8 text-center border-b lg:border-b-0 border-[#FFD700] lg:border-r flex flex-col items-center">
                    <img src="{{ asset('mass-gain.svg') }}" class="w-12 h-12 text-white mb-6"/>
                    <h3 class="text-lg font-bold text-white tracking-widest uppercase mb-3">Mass Gain</h3>
                    <p class="text-sm text-white leading-relaxed">
                        Tingkatkan massa otot untuk tubuh yang lebih kekar.
                    </p>
                </div>

                <div class="p-8 text-center border-b lg:border-b-0 border-[#FFD700] lg:border-r flex flex-col items-center">
                    <img src="{{ asset('fitness.svg') }}" class="w-12 h-12 text-white mb-6"/>
                    <h3 class="text-lg font-bold text-white tracking-widest uppercase mb-3">Strength & Performance</h3>
                    <p class="text-sm text-white leading-relaxed">
                        Maksimalkan kekuatan dan kinerja fisik Anda.
                    </p>
                </div>

                <div class="p-8 text-center flex flex-col items-center">
                    <img src="{{ asset('glute-building.svg') }}" class="w-12 h-12 text-white mb-6"/>
                    <h3 class="text-lg font-bold text-white tracking-widest uppercase mb-3">Glute Building</h3>
                    <p class="text-sm text-white leading-relaxed">
                        Bangun core yang kuat untuk postur dan stabilitas optimal.
                    </p>
                </div>

            </div>
        </div>
    </div>

    <footer id="lokasi" class="bg-neutral-900 border-t border-gray-800 scroll-mt-16">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:py-16 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 lg:gap-8">
                
                <div class="md:col-span-1">
                    <div class="flex items-center space-x-3 mb-6">
                        <img src="{{ asset('icon.png') }}" class="h-8" alt="Frans GYM Logo" />
                        <span class="text-2xl font-bold text-white tracking-wider">PT FransGym</span>
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed mb-6">
                        Wujudkan Tubuh Impian Anda bersama FRANS GYM, Mitra Terpercaya dalam Kebugaran. Dengan pengalaman lebih dari 6 tahun, kami menyediakan pelatihan profesional dan program latihan yang dirancang khusus untuk kebutuhan Anda. Bergabunglah dengan komunitas kami dan mulailah perjalanan menuju tubuh yang lebih sehat dan kuat. Siap memulai perubahan?
                    </p>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-white tracking-wider uppercase mb-6">Jam Operasional</h3>
                    <ul class="space-y-4 text-sm text-gray-400">
                        <li class="flex justify-between border-b border-gray-800 pb-3">
                            <span>Senin - Jum’at</span>
                            <span class="text-white font-medium">07.00 - 22.00</span>
                        </li>
                        <li class="flex justify-between border-b border-gray-800 pb-3">
                            <span>Sabtu</span>
                            <span class="text-white font-medium">07.00 - 20.00</span>
                        </li>
                        <li class="flex justify-between border-b border-gray-800 pb-3">
                            <span>Minggu</span>
                            <span class="text-white font-medium">07.00 - 19.00</span>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-white tracking-wider uppercase mb-6">Lokasi Kami</h3>
                    <div class="flex items-start space-x-3 text-gray-400 text-sm leading-relaxed">
                        <svg class="flex-shrink-0 w-6 h-6 text-brand mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span>Jl. Intan Sari No.02, Simpang III Sipin, Kec. Kota Baru, Kota Jambi, Jambi.</span>
                    </div>
                </div>

            </div>

            <div class="mt-12 border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-500 text-sm text-center md:text-left">
                    &copy; {{ date('Y') }} PT FransGym. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

</div>