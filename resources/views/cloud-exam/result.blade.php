<!DOCTYPE html>
<html>
<head>
    <title>Exam Result</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
    <div class="max-w-5xl mx-auto p-6">
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h1 class="text-2xl font-bold mb-2">{{ session('cloud_exam.subject') }} Exam Result</h1>

            <div class="text-4xl font-bold">
                {{ $result['total_score'] ?? 0 }} / {{ $result['total_marks'] ?? 0 }}
            </div>

            <p class="text-slate-600 mt-2">
                Percentage: {{ $result['percentage'] ?? 0 }}%
            </p>

            @if (!empty($result['grade_comment']))
                <p class="mt-4 rounded-lg bg-slate-100 p-4">
                    {{ $result['grade_comment'] }}
                </p>
            @endif
        </div>

        <div class="space-y-6">
            @foreach ($result['questions'] ?? [] as $item)
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex justify-between gap-4 mb-3">
                        <h2 class="font-semibold">
                            Q{{ $item['number'] ?? $loop->iteration }}. {{ $item['question'] ?? '' }}
                        </h2>

                        <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold">
                            {{ $item['student_score'] ?? 0 }} / {{ $item['marks'] ?? 0 }}
                        </span>
                    </div>

                    <div class="mb-4">
                        <h3 class="font-semibold mb-1">Feedback</h3>
                        <p class="text-slate-700">{{ $item['feedback'] ?? '' }}</p>
                    </div>

                    @if (!empty($item['missing_points']))
                        <div class="mb-4">
                            <h3 class="font-semibold mb-1">Missing Points</h3>
                            <ul class="list-disc pl-6 text-slate-700">
                                @foreach ($item['missing_points'] as $point)
                                    <li>{{ $point }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (!empty($item['model_answer']))
                        <div>
                            <h3 class="font-semibold mb-1">Model Answer</h3>
                            <p class="text-slate-700 whitespace-pre-line">{{ $item['model_answer'] }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex gap-3">
            <a href="{{ route('cloud.exam.index') }}" class="rounded-lg bg-slate-900 px-5 py-3 font-semibold text-white">
                Generate New Exam
            </a>

            <a href="{{ route('cloud.exam.attempt') }}" class="rounded-lg bg-white px-5 py-3 font-semibold border border-slate-300">
                Retry Same Exam
            </a>
        </div>
    </div>
</body>
</html>
