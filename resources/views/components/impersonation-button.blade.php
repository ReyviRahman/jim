@props(['user'])

@can('impersonate', $user)
    <form method="POST" action="{{ route('impersonation.start', $user) }}">
        @csrf
        <button type="submit" class="rounded-md p-2 text-sm font-medium text-heading hover:bg-neutral-tertiary-medium focus-visible:outline-2 focus-visible:outline-brand">
            Masuk sebagai pengguna
        </button>
    </form>
@endcan
