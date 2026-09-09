<!DOCTYPE html>
<html>
<head>
    <title>Attempt Exam</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
    <div class="max-w-5xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-2">Attempt {{ session('cloud_exam.subject') }} Written Exam</h1>
        <p class="text-slate-600 mb-6">Write detailed answers. The AI will mark deeply and give instant feedback.</p>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 p-4 text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('cloud.exam.mark') }}" class="space-y-6">
            @csrf

            @foreach ($questions['questions'] ?? $questions as $index => $question)
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex justify-between gap-4 mb-3">
                        <h2 class="font-semibold">
                            Q{{ $index + 1 }}. {{ $question['question'] ?? '' }}
                        </h2>
                        <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold">
                            {{ $question['marks'] ?? 2 }} marks
                        </span>
                    </div>

                    @if (!empty($question['topic']))
                        <p class="text-sm text-slate-500 mb-3">Topic: {{ $question['topic'] }}</p>
                    @endif

                    <textarea
                        name="answers[{{ $index }}]"
                        rows="8"
                        class="w-full rounded-lg border border-slate-300 p-4 focus:border-slate-900 focus:ring-slate-900"
                        placeholder="Write your answer here..."
                    ></textarea>
                </div>
            @endforeach

            <button class="w-full rounded-lg bg-slate-900 px-5 py-3 font-semibold text-white hover:bg-slate-800">
                Submit for AI Marking
            </button>
        </form>
    </div>
</body>
</html>
