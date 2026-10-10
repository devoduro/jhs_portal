<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Send SMS') }}</h2>
            <div class="flex flex-wrap gap-2">
                <x-button href="{{ route('sms.history') }}" variant="secondary" icon="fas fa-clock-rotate-left">Sent Messages</x-button>
                <x-button href="{{ route('sms.templates.index') }}" variant="secondary" icon="fas fa-file-lines">Templates</x-button>
            </div>
        </div>
    </x-slot>

    @php
        $isStaff = $filters['audience'] === 'staff';
        $sel = 'w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-200';
        $recipients = $isStaff ? $counts['with_phone'] : $counts['families'];
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <!-- Audience tabs -->
            <div class="flex gap-2">
                @foreach(['parents' => ['fa-people-roof', 'Parents & guardians'], 'staff' => ['fa-chalkboard-user', 'Staff']] as $key => [$icon, $label])
                    <a href="{{ route('sms.index', ['audience' => $key]) }}"
                       class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold {{ $filters['audience'] === $key ? 'bg-primary-600 text-white shadow' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                        <i class="fas {{ $icon }}"></i> {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <!-- Step 1: who -->
                <form method="GET" action="{{ route('sms.index') }}" class="space-y-4 rounded-lg bg-white p-5 shadow-sm lg:col-span-2">
                    <input type="hidden" name="audience" value="{{ $filters['audience'] }}">
                    <h3 class="font-semibold text-gray-900"><span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 text-xs text-primary-700">1</span>Who should get it?</h3>

                    @if($isStaff)
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Role</label>
                            <select name="staff_role" class="{{ $sel }}">
                                <option value="">All staff</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" @selected(($filters['staff_role'] ?? '') === $role)>{{ $role }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Section</label>
                            <select name="section" class="{{ $sel }}">
                                <option value="">Whole school</option>
                                @foreach(\App\Support\FormLabel::SECTIONS as $key => $label)
                                    <option value="{{ $key }}" @selected(($filters['section'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Classes <span class="text-gray-400">(tick none for all)</span></label>
                            <div class="grid max-h-40 grid-cols-2 gap-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
                                @foreach($classGroups as $c)
                                    <label class="flex items-center gap-2 rounded px-1 py-0.5 text-sm hover:bg-gray-50">
                                        <input type="checkbox" name="class_group_ids[]" value="{{ $c->id }}" class="rounded border-gray-300" @checked(in_array($c->id, $filters['class_group_ids']))>
                                        {{ $c->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Gender</label>
                                <select name="gender" class="{{ $sel }}">
                                    <option value="">Boys & girls</option>
                                    <option value="Male" @selected(($filters['gender'] ?? '') === 'Male')>Boys</option>
                                    <option value="Female" @selected(($filters['gender'] ?? '') === 'Female')>Girls</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">School fees</label>
                                <select name="fees" class="{{ $sel }}" onchange="document.getElementById('min-bal').classList.toggle('hidden', this.value !== 'owing')">
                                    <option value="">Everyone</option>
                                    <option value="owing" @selected(($filters['fees'] ?? '') === 'owing')>Owing only</option>
                                    <option value="paid" @selected(($filters['fees'] ?? '') === 'paid')>Fully paid only</option>
                                </select>
                            </div>
                        </div>
                        <div id="min-bal" class="{{ ($filters['fees'] ?? '') === 'owing' ? '' : 'hidden' }}">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Owing at least (GH₵)</label>
                            <input type="number" min="0" step="1" name="min_balance" value="{{ $filters['min_balance'] ?? '' }}" placeholder="0" class="{{ $sel }}">
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Search</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $isStaff ? 'Staff name' : 'Pupil, admission no., parent or phone' }}" class="{{ $sel }}">
                    </div>

                    <div class="flex gap-2">
                        <x-button type="submit" icon="fas fa-filter">Show recipients</x-button>
                        <x-button href="{{ route('sms.index', ['audience' => $filters['audience']]) }}" variant="secondary">Reset</x-button>
                    </div>
                </form>

                <!-- Step 2: what -->
                <form method="POST" action="{{ route('sms.send') }}" id="smsForm" class="space-y-4 rounded-lg bg-white p-5 shadow-sm lg:col-span-3">
                    @csrf
                    @foreach($filters as $key => $value)
                        @if(is_array($value))
                            @foreach($value as $v)<input type="hidden" name="{{ $key }}[]" value="{{ $v }}">@endforeach
                        @elseif($value !== null && $value !== '')
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-gray-900"><span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 text-xs text-primary-700">2</span>Write the message</h3>
                        @if($templates->isNotEmpty())
                            <select id="template" class="rounded-lg border-gray-300 text-sm">
                                <option value="">Use a template…</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->message }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div>
                        <textarea name="message" id="message" rows="6" maxlength="612" required class="{{ $sel }}"
                                  placeholder="Dear {parent}, ...">{{ old('message') }}</textarea>
                        <div class="mt-1 flex flex-wrap justify-between text-xs text-gray-500">
                            <span><span id="chars">0</span> characters &bull; <span id="parts">1</span> SMS per person</span>
                            <span>About <strong id="credits">0</strong> SMS credits in total</span>
                        </div>
                    </div>

                    <div>
                        <p class="mb-1 text-xs font-medium text-gray-600">Personalise — click to insert:</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach(\App\Services\BulkSmsService::PLACEHOLDERS as $tag => $help)
                                @continue($isStaff && !in_array($tag, ['{name}', '{school}']))
                                @continue(!$isStaff && $tag === '{name}')
                                <button type="button" class="placeholder rounded-full bg-primary-50 px-2.5 py-1 font-mono text-xs text-primary-700 hover:bg-primary-100" data-tag="{{ $tag }}" title="{{ $help }}">{{ $tag }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="mb-2 text-xs font-medium uppercase text-gray-500">Preview{{ !$isStaff && isset($sampleValues['{pupil}']) ? ' — as ' . $sampleValues['{parent}'] . ' will see it' : '' }}</p>
                        <div class="max-w-sm rounded-2xl rounded-tl-sm bg-white p-3 text-sm text-gray-800 shadow-sm ring-1 ring-gray-200 whitespace-pre-wrap" id="preview"><span class="text-gray-400">Your message will appear here.</span></div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary-100 bg-primary-50 p-4">
                        <p class="text-sm text-primary-900">
                            @if($isStaff)
                                <strong>{{ $counts['with_phone'] }}</strong> staff member(s) will receive this.
                            @else
                                <strong id="recipient-count">{{ $counts['families'] }}</strong> parent phone(s) for <strong>{{ $counts['with_phone'] }}</strong> pupil(s).
                                <span id="sibling-note" class="block text-xs text-primary-700">Parents with more than one child here get it once. Using a pupil placeholder sends one per child.</span>
                            @endif
                            @if($counts['no_phone'])
                                <span class="block text-xs text-orange-700">{{ $counts['no_phone'] }} {{ $isStaff ? 'staff member(s)' : 'pupil(s)' }} skipped — no phone number on file.</span>
                            @endif
                        </p>
                        <x-button type="submit" icon="fas fa-paper-plane" :disabled="$recipients === 0">Send SMS</x-button>
                    </div>
                </form>
            </div>

            <!-- Recipients -->
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b px-5 py-3 text-sm font-semibold text-gray-700">{{ $counts['matched'] }} {{ $isStaff ? 'staff member(s)' : 'pupil(s)' }} match</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                @if($isStaff)
                                    <th class="px-5 py-2 text-left font-medium">Name</th>
                                    <th class="px-5 py-2 text-left font-medium">Role</th>
                                    <th class="px-5 py-2 text-left font-medium">Phone</th>
                                @else
                                    <th class="px-5 py-2 text-left font-medium">Pupil</th>
                                    <th class="px-5 py-2 text-left font-medium">Class</th>
                                    <th class="px-5 py-2 text-left font-medium">Parent / guardian</th>
                                    <th class="px-5 py-2 text-left font-medium">Phone</th>
                                    <th class="px-5 py-2 text-right font-medium">Fees owing</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($people as $person)
                                @if($isStaff)
                                    <tr>
                                        <td class="px-5 py-2 font-medium text-gray-800">{{ $person->name }}</td>
                                        <td class="px-5 py-2 text-gray-600">{{ $person->getRoleNames()->implode(', ') }}</td>
                                        <td class="px-5 py-2 {{ $person->resetPhone() ? 'text-gray-600' : 'text-orange-600' }}">{{ $person->resetPhone() ?: 'No phone' }}</td>
                                    </tr>
                                @else
                                    @php $phone = \App\Services\BulkSmsService::parentPhone($person); @endphp
                                    <tr>
                                        <td class="px-5 py-2"><span class="font-medium text-gray-800">{{ $person->full_name }}</span> <span class="text-xs text-gray-400">{{ $person->index_number }}</span></td>
                                        <td class="px-5 py-2 text-gray-600">{{ $person->classGroup->name ?? \App\Support\FormLabel::label($person->level) }}</td>
                                        <td class="px-5 py-2 text-gray-600">{{ $person->emergency_contact_name ?: '—' }}</td>
                                        <td class="px-5 py-2 {{ $phone ? 'text-gray-600' : 'text-orange-600' }}">{{ $phone ?: 'No phone' }}</td>
                                        <td class="px-5 py-2 text-right {{ $person->balance > 0.009 ? 'text-red-600' : 'text-green-600' }}">{{ number_format(max(0, (float) $person->balance), 2) }}</td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">Nobody matches these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3">{{ $people->links() }}</div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var box = document.getElementById('message');
            var sample = @json($sampleValues);
            var pupilTags = ['{pupil}', '{first_name}', '{class}', '{balance}', '{admission_no}'];
            var counts = { families: {{ (int) ($counts['families'] ?? $counts['with_phone']) }}, pupils: {{ (int) $counts['with_phone'] }} };
            var isStaff = {{ $isStaff ? 'true' : 'false' }};

            function esc(s) { return s.replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); }

            function refresh() {
                var text = box.value, filled = text;
                Object.keys(sample).forEach(function (tag) { filled = filled.split(tag).join(sample[tag]); });
                var len = filled.length, parts = len <= 160 ? 1 : Math.ceil(len / 153);
                var personal = pupilTags.some(function (t) { return text.indexOf(t) !== -1; });
                var recipients = isStaff ? counts.pupils : (personal ? counts.pupils : counts.families);

                document.getElementById('chars').textContent = len;
                document.getElementById('parts').textContent = parts;
                document.getElementById('credits').textContent = (parts * recipients).toLocaleString();
                document.getElementById('preview').innerHTML = text ? esc(filled) : '<span class="text-gray-400">Your message will appear here.</span>';
                var rc = document.getElementById('recipient-count');
                if (rc) rc.textContent = recipients;
                box.dataset.recipients = recipients;
            }

            box.addEventListener('input', refresh);
            document.querySelectorAll('.placeholder').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var start = box.selectionStart, end = box.selectionEnd, tag = btn.dataset.tag;
                    box.value = box.value.slice(0, start) + tag + box.value.slice(end);
                    box.focus(); box.selectionStart = box.selectionEnd = start + tag.length;
                    refresh();
                });
            });
            var template = document.getElementById('template');
            if (template) template.addEventListener('change', function () {
                if (!template.value) return;
                if (!box.value || confirm('Replace the current message with this template?')) { box.value = template.value; refresh(); }
                template.value = '';
            });
            document.getElementById('smsForm').addEventListener('submit', function (e) {
                if (!confirm('Send this SMS to ' + box.dataset.recipients + ' recipient(s)? This cannot be undone.')) e.preventDefault();
            });
            refresh();
        })();
    </script>
</x-app-layout>
