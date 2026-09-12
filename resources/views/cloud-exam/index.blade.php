<!DOCTYPE html>
<html>

<head>
    <title>MCQ Study Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-900">
    <div class="max-w-4xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-2">MCQ Study Generator</h1>
        <p class="text-slate-600 mb-6">Select one of your three subjects, choose the lecture notes, and generate an MCQ practice exam or study summary.
        </p>

        <form method="GET" action="{{ route('cloud.exam.index') }}" class="mb-4 bg-white rounded-xl shadow-sm p-4">
            <label class="block font-semibold mb-2">Subject</label>
            <select name="subject" class="w-full rounded-lg border border-slate-300 p-3" onchange="this.form.submit()">
                @foreach ($subjects as $code => $name)
                    <option value="{{ $code }}" @selected($selectedSubject === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </form>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 p-4 text-red-700">
                {{ $errors->first() }}
            </div>
        @endif
        @if (session('success'))<div class="mb-4 rounded-lg bg-emerald-50 p-4 text-emerald-700">{{ session('success') }}</div>@endif

        @if ($diagrams->isNotEmpty())
            <div class="mb-6 bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-semibold mb-3">Saved {{ $selectedSubject }} diagrams</h2>
                <div class="space-y-2">
                    @foreach ($diagrams as $diagram)
                        <div class="flex justify-between items-center border-b border-slate-100 py-2">
                            <span>{{ $diagram->title }}</span>
                            @if ($diagram->status === 'ready')<a class="text-indigo-600 font-semibold" href="{{ asset('storage/'.$diagram->path) }}" target="_blank">View image</a>
                            @else<span class="text-sm text-slate-500">{{ ucfirst($diagram->status) }}</span>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('cloud.exam.generate') }}"
            class="bg-white rounded-xl shadow-sm p-6 space-y-6">
            @csrf
            <input type="hidden" name="subject" value="{{ $selectedSubject }}">

            <div>
                <label class="block font-semibold mb-3">Select Source Notes</label>

                <div class="space-y-3">
                    @forelse ($files as $file)
                        <label
                            class="flex items-center justify-between rounded-lg border border-slate-200 p-4 hover:bg-slate-50 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" name="sources[]" value="{{ $file['path'] }}" class="rounded">
                                <div>
                                    <div class="font-medium">{{ $file['name'] }}</div>
                                    <div class="text-sm text-slate-500">{{ $file['size'] }} MB</div>
                                </div>
                            </div>
                        </label>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-300 p-4 text-slate-600">
                            No notes found for {{ $subjects[$selectedSubject] }} yet. Add files to
                            <span class="font-mono text-sm">public/subjects/{{ $selectedSubject }}</span>.
                        </div>
                    @endforelse
                </div>
            </div>

            <div>
                <label class="block font-semibold mb-2">Number of Questions</label>
                <select name="question_count" class="w-full rounded-lg border border-slate-300 p-3">
                    <option value="5">5 Questions</option>
                    <option value="10">10 Questions</option>
                    <option value="15">15 Questions</option>
                    <option value="20">20 Questions</option>
                </select>
            </div>

            <div>
                <label class="block font-semibold mb-2">Quick Diagram Passage <span class="font-normal text-slate-500">(optional)</span></label>
                <textarea name="passage" rows="5" maxlength="12000"
                    class="w-full rounded-lg border border-slate-300 p-3"
                    placeholder="Paste a short passage here to generate diagrams only from this text. Leave empty to use the selected notes."></textarea>
                <p class="mt-1 text-sm text-slate-500">This applies to Generate Diagrams and avoids sending the whole document.</p>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <button formaction="{{ route('cloud.exam.generate') }}"
                    class="w-full rounded-lg bg-slate-900 px-5 py-3 font-semibold text-white">
                    Generate Exam
                </button>

                <button formaction="{{ route('cloud.exam.summarize') }}"
                    class="w-full rounded-lg bg-white border border-slate-300 px-5 py-3 font-semibold">
                    Summarize Notes
                </button>

                <button formaction="{{ route('cloud.exam.diagrams') }}"
                    class="w-full rounded-lg bg-indigo-600 px-5 py-3 font-semibold text-white">
                    Generate Diagrams
                </button>
            </div>
        </form>
    </div>
</body>

</html>
