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
                <p class="mb-3 whitespace-pre-line">{{ $topic['how_to_answer'] ?? '' }}</p>

                <h3 class="font-semibold">Examples</h3>
                <ul class="list-disc pl-6 mb-3">
                    @foreach ($topic['real_world_examples'] ?? [] as $ex)
                        <li>{{ $ex }}</li>
                    @endforeach
                </ul>

                <h3 class="font-semibold">Sample Answer</h3>
                <p class="whitespace-pre-line">{{ $topic['sample_answer'] ?? '' }}</p>
            </div>
        @endforeach

         

        <a href="{{ route('cloud.exam.index') }}"
            class="inline-block mt-4 bg-slate-900 text-white px-5 py-3 rounded-lg">
            Back
        </a>

    </div>

</body>

</html>
