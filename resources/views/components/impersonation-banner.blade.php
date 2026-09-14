@if (session()->has('impersonator_id'))
    <div role="status" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-brand bg-brand p-4 text-[#34342F]">
        <p>Anda masuk sebagai <strong>{{ auth()->user()->name }}</strong></p>
        <form method="POST" action="{{ route('impersonation.stop') }}">
            @csrf
            <button type="submit" class="rounded-md bg-[#34342F] px-4 py-2 text-sm font-medium text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#34342F]">
                Kembali ke admin
            </button>
        </form>
    </div>
@endif
