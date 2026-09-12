<!DOCTYPE html>
<html>

<head>
    <title>Study Diagrams</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-900">
    <div class="max-w-5xl mx-auto p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Study Diagrams</h1>
            <p class="text-slate-600">Visual explanations generated as images using an OpenAI image model.</p>
        </div>

        @if (!empty($result['image_url']))
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <h2 class="text-xl font-bold mb-4">{{ $result['title'] ?? 'Study Diagram' }}</h2>
                <img src="{{ $result['image_url'] }}" alt="{{ $result['title'] ?? 'Study Diagram' }}" class="w-full rounded-lg border border-slate-200">
            </div>
        @else
        @forelse ($result['diagrams'] ?? [] as $diagram)
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <h2 class="text-xl font-bold mb-2">{{ $diagram['title'] ?? 'Diagram' }}</h2>

                @if (!empty($diagram['description']))
                    <p class="mb-4 text-slate-700">{{ $diagram['description'] }}</p>
                @endif

                <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white p-4">
                    <pre class="mermaid">{{ $diagram['mermaid'] ?? '' }}</pre>
                </div>
            </div>
        @empty
            <div class="bg-white p-6 rounded-xl shadow-sm text-slate-600">
                No diagrams were generated.
            </div>
        @endforelse
        @endif

        <a href="{{ route('cloud.exam.index') }}"
            class="inline-block mt-4 bg-slate-900 text-white px-5 py-3 rounded-lg">
            Back
        </a>
    </div>
</body>

</html>
