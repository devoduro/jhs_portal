<x-admission-layout title="Confirming Payment">
    <div class="bg-white shadow-sm rounded-lg p-8 text-center">
        <div id="waiting-state">
            <svg class="animate-spin h-10 w-10 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <h1 class="text-lg font-bold text-gray-900 mb-2">Approve the payment on your phone</h1>
            <p class="text-sm text-gray-500">A Mobile Money prompt has been sent to your phone. Enter your PIN to approve it - this page will update automatically once it's confirmed.</p>
        </div>
        <div id="failed-state" class="hidden">
            <h1 class="text-lg font-bold text-red-700 mb-2">Payment was not completed</h1>
            <p class="text-sm text-gray-500 mb-4">The payment failed or was declined. You can try again.</p>
            <a href="{{ route('admission.pay', $application->index_number) }}" class="inline-block bg-blue-600 text-white rounded-md px-4 py-2 text-sm hover:bg-blue-700">Try Again</a>
        </div>
    </div>

    <script>
        const statusUrl = @json(route('admission.pay.status', $application->index_number));

        async function pollStatus() {
            try {
                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();

                if (data.status === 'completed' && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }

                if (data.status === 'failed') {
                    document.getElementById('waiting-state').classList.add('hidden');
                    document.getElementById('failed-state').classList.remove('hidden');
                    return;
                }
            } catch (e) {
                // Network hiccup - just try again on the next tick.
            }

            setTimeout(pollStatus, 5000);
        }

        setTimeout(pollStatus, 5000);
    </script>
</x-admission-layout>
