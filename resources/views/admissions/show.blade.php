<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $application->full_name }}
            </h2>
            <x-button href="{{ route('admissions.index') }}" variant="secondary" icon="fas fa-arrow-left">
                {{ __('Back to Admissions') }}
            </x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-md px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-md px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-md px-4 py-3 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200 flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        @if($application->photo_path)
                            <img src="{{ asset('storage/'.$application->photo_path) }}" class="w-16 h-16 rounded-full object-cover border border-gray-200">
                        @else
                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center text-gray-400">
                                <i class="fas fa-user text-2xl"></i>
                            </div>
                        @endif
                        <div>
                            <div class="text-lg font-medium text-gray-900">{{ $application->full_name }}</div>
                            <div class="text-sm text-gray-500">{{ $application->index_number }} &bull; {{ $application->programme->name ?? 'No programme' }}</div>
                        </div>
                    </div>
                    @php
                        $statusColors = [
                            'placed' => 'bg-gray-100 text-gray-800',
                            'payment_pending' => 'bg-orange-100 text-orange-800',
                            'paid' => 'bg-yellow-100 text-yellow-800',
                            'submitted' => 'bg-blue-100 text-blue-800',
                            'admitted' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800',
                        ];
                    @endphp
                    <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $statusColors[$application->status] ?? 'bg-gray-100 text-gray-800' }}">
                        {{ \App\Models\AdmissionApplication::STATUS_LABELS[$application->status] ?? $application->status }}
                    </span>
                </div>

                @if($application->status === 'submitted')
                    <div class="p-6 bg-blue-50 border-b border-gray-200 flex gap-3">
                        <form method="POST" action="{{ route('admissions.admit', $application) }}" onsubmit="return confirm('Admit {{ $application->full_name }} into the main system? This creates their Student record and login account.')">
                            @csrf
                            <x-button type="submit" icon="fas fa-check">{{ __('Admit Student') }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('admissions.reject', $application) }}" onsubmit="return confirm('Reject this application?')">
                            @csrf
                            <x-button type="submit" variant="danger" icon="fas fa-times">{{ __('Reject') }}</x-button>
                        </form>
                    </div>
                @elseif($application->status === 'admitted' && $application->student)
                    <div class="p-6 bg-green-50 border-b border-gray-200">
                        <a href="{{ route('students.show', $application->student) }}" class="text-green-700 font-medium hover:underline">
                            <i class="fas fa-user-graduate"></i> View Student Record &rarr;
                        </a>
                    </div>
                @elseif($application->status === 'rejected' && $application->review_notes)
                    <div class="p-6 bg-red-50 border-b border-gray-200 text-sm text-red-700">
                        <strong>Rejection notes:</strong> {{ $application->review_notes }}
                    </div>
                @endif

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Personal Details</h4>
                        <dl class="text-sm space-y-2">
                            <div class="flex justify-between"><dt class="text-gray-500">Gender</dt><dd>{{ $application->gender ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Date of Birth</dt><dd>{{ optional($application->date_of_birth)->format('M j, Y') ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Boarding Status</dt><dd>{{ ucfirst($application->boarding_status ?? '-') }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Nationality</dt><dd>{{ $application->nationality ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Religion</dt><dd>{{ $application->religion ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Ghana Card</dt><dd>{{ $application->ghana_card_number ?? '-' }}</dd></div>
                        </dl>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Contact</h4>
                        <dl class="text-sm space-y-2">
                            <div class="flex justify-between"><dt class="text-gray-500">Phone</dt><dd>{{ $application->phone ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Alternate</dt><dd>{{ $application->alternate_phone ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">WhatsApp</dt><dd>{{ $application->whatsapp_number ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Email</dt><dd>{{ $application->email ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Address</dt><dd class="text-right">{{ $application->address ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Town / Region</dt><dd>{{ trim(($application->town ?? '').' / '.($application->region ?? ''), ' /') ?: '-' }}</dd></div>
                        </dl>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Parent / Guardian</h4>
                        <dl class="text-sm space-y-2">
                            <div class="flex justify-between"><dt class="text-gray-500">Father</dt><dd>{{ $application->father_name ?? '-' }} @if($application->father_occupation)({{ $application->father_occupation }})@endif</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Mother</dt><dd>{{ $application->mother_name ?? '-' }} @if($application->mother_occupation)({{ $application->mother_occupation }})@endif</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Guardian</dt><dd>{{ $application->guardian_name ?? '-' }}</dd></div>
                        </dl>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">JHS / Academic</h4>
                        <dl class="text-sm space-y-2">
                            <div class="flex justify-between"><dt class="text-gray-500">JHS Attended</dt><dd>{{ $application->jhs_attended ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">JHS Type</dt><dd>{{ $application->jhs_type ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">BECE Aggregate</dt><dd>{{ $application->aggregate_score ?? '-' }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Payment History</h3>
                    @if($application->payments->isEmpty())
                        <p class="text-sm text-gray-500">No payment attempts yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Network</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($application->payments as $payment)
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900">{{ $payment->order_reference }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-500">{{ $payment->network ?? '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-500">GHS {{ number_format($payment->amount, 2) }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-500">{{ ucfirst($payment->status) }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-500">{{ $payment->created_at->format('M j, Y g:i A') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
