<!DOCTYPE html>
<html>

<head>
    <title>Study Diagrams</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="module">
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';

        mermaid.initialize({
            startOnLoad: true,
            theme: 'default',
            securityLevel: 'strict'
        });
    </script>
</head>

<body class="bg-slate-50 text-slate-900">
    <div class="max-w-5xl mx-auto p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Study Diagrams</h1>
            <p class="text-slate-600">Visual explanations generated separately using OpenAI.</p>
        </div>

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

        <a href="{{ route('cloud.exam.index') }}"
            class="inline-block mt-4 bg-slate-900 text-white px-5 py-3 rounded-lg">
            Back
        </a>
    </div>
</body>

</html>
