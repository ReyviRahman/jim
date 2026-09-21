<fieldset class="bg-white p-6 shadow-xs rounded-md border border-default">
    <legend class="text-sm font-semibold text-heading px-2">Apakah ingin mencoba program personal trainer atau trial? <span class="text-red-500">*</span></legend>
    <div class="flex flex-wrap gap-6">
        @foreach(\App\Models\Membership::PT_TRIAL_INTEREST_OPTIONS as $value => $label)
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="pt_trial_interest" wire:model="pt_trial_interest" value="{{ $value }}" required class="text-brand focus:ring-brand w-4 h-4">
                <span class="text-sm font-medium text-heading">{{ $label }}</span>
            </label>
        @endforeach
    </div>
    @error('pt_trial_interest') <p role="alert" class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
</fieldset>
