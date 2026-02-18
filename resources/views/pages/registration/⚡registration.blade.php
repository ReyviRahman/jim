<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app')] #[Title('Frans GYM | Pendaftaran Member')] class extends Component 
{};
?>

<div class="flex items-center justify-center p-4">
    <div class="w-full max-w-5xl bg-neutral-primary-soft p-6 border border-default rounded-base shadow-xs">
        <div>
            <h5 class="text-xl font-semibold text-heading mb-6">Pendaftaran Akun</h5>
            <div class="w-full bg-neutral-primary border border-default rounded-base shadow-xs">
                <ul class="flex flex-wrap text-sm font-medium text-center text-body bg-neutral-secondary-soft border-b border-default rounded-t-base"
                    id="defaultTab" data-tabs-toggle="#defaultTabContent" role="tablist">
                    <li class="me-2">
                        <button id="about-tab" data-tabs-target="#about" type="button" role="tab"
                            aria-controls="about" aria-selected="true"
                            class="inline-block p-4 text-fg-brand rounded-ss-base hover:bg-neutral-tertiary">Member</button>
                    </li>
                    <li class="me-2">
                        <button id="services-tab" data-tabs-target="#services" type="button" role="tab"
                            aria-controls="services" aria-selected="false"
                            class="inline-block p-4 hover:text-heading hover:bg-neutral-tertiary">Personal
                            Trainer</button>
                    </li>
                </ul>
                <div id="defaultTabContent">
                    <div class="hidden p-4 rounded-b-base md:p-8" id="about" role="tabpanel"
                        aria-labelledby="about-tab">
                        <livewire:registration.member />
                    </div>
                    <div class="hidden p-4 rounded-b-base md:p-8" id="services" role="tabpanel"
                        aria-labelledby="services-tab">
                        <livewire:registration.pt />
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
