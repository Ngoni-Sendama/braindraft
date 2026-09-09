<!DOCTYPE html>
<html>

<head>
    <title>Study Summary</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-900">

    <div class="max-w-4xl mx-auto p-6 space-y-6">

        <h1 class="text-2xl font-bold">Study Summary</h1>

        @foreach ($result['topics'] ?? [] as $topic)
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <h2 class="text-xl font-bold mb-2">{{ $topic['title'] }}</h2>

                <p class="mb-3 text-slate-700">{{ $topic['summary'] }}</p>

                <h3 class="font-semibold">Key Points</h3>
                <ul class="list-disc pl-6 mb-3">
                    @foreach ($topic['key_points'] ?? [] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>

                <h3 class="font-semibold">How to Answer</h3>
                @if (is_array($topic['how_to_answer'] ?? null))
                    <p class="mb-2"><strong>5-mark structure:</strong> {{ $topic['how_to_answer']['5_mark_structure'] ?? '' }}</p>
                    <p class="mb-3"><strong>10-mark structure:</strong> {{ $topic['how_to_answer']['10_mark_structure'] ?? '' }}</p>
                @else
                    <p class="mb-3 whitespace-pre-line">{{ $topic['how_to_answer'] ?? '' }}</p>
                @endif

                <h3 class="font-semibold">Examples</h3>
                <ul class="list-disc pl-6 mb-3">
                    @foreach ($topic['real_world_examples'] ?? [] as $ex)
                        <li>{{ $ex }}</li>
                    @endforeach
                </ul>

                <h3 class="font-semibold">5-Mark Answer</h3>
                <p class="mb-3 whitespace-pre-line">{{ $topic['sample_answers']['5_mark'] ?? $topic['sample_answer'] ?? '' }}</p>

                <h3 class="font-semibold">10-Mark Answer</h3>
                <p class="whitespace-pre-line">{{ $topic['sample_answers']['10_mark'] ?? '' }}</p>

                @if (!empty($topic['assumptions']))
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800"><strong>Assumption:</strong> {{ $topic['assumptions'] }}</p>
                @endif
            </div>
        @endforeach

         

        <a href="{{ route('cloud.exam.index') }}"
            class="inline-block mt-4 bg-slate-900 text-white px-5 py-3 rounded-lg">
            Back
        </a>

    </div>

</body>

</html>
