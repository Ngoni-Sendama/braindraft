<!DOCTYPE html>
<html>
<head>
    <title>Attempt Exam</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
    <div class="max-w-5xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-2">Attempt {{ session('cloud_exam.subject') }} MCQ Exam</h1>
        <p class="text-slate-600 mb-6">{{ session('cloud_exam.subject') === 'CHE 110' ? 'Choose one answer for each question.' : 'Write a clear answer for each subjective question.' }}</p>

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
                    </div>

                    @if (!empty($question['topic']))
                        <p class="text-sm text-slate-500 mb-3">Topic: {{ $question['topic'] }}</p>
                    @endif

                    @if (session('cloud_exam.subject') !== 'CHE 110')
                    <textarea name="answers[{{ $index }}]" rows="8" class="w-full rounded-lg border border-slate-300 p-4" placeholder="Write your answer here..." required></textarea>
                    @else
                    <div class="space-y-2">
                        @foreach ($question['options'] ?? [] as $letter => $option)
                            <label class="flex gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 cursor-pointer">
                                <input type="radio" name="answers[{{ $index }}]" value="{{ $letter }}" required>
                                <span><strong>{{ $letter }}.</strong> {{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                    @endif
                </div>
            @endforeach

            <button class="w-full rounded-lg bg-slate-900 px-5 py-3 font-semibold text-white hover:bg-slate-800">
                Submit for AI Marking
            </button>
        </form>
    </div>
</body>
</html>
