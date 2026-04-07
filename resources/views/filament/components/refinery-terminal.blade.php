<div 
    x-data="{ 
        scrollToBottom() { 
            $el.querySelector('#terminal-content').scrollIntoView({ behavior: 'smooth', block: 'end' }); 
        } 
    }" 
    x-init="scrollToBottom()"
    x-effect="$nextTick(() => scrollToBottom())"
    class="w-full bg-black rounded-lg border-2 border-gray-700 shadow-2xl overflow-hidden font-mono text-sm"
>
    <div class="bg-gray-800 px-4 py-2 flex items-center justify-between border-b border-gray-700">
        <div class="flex space-x-2">
            <div class="w-3 h-3 rounded-full bg-red-500"></div>
            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
            <div class="w-3 h-3 rounded-full bg-green-500"></div>
        </div>
        <div class="text-gray-400 text-[10px] uppercase tracking-widest font-bold">Acado Refinery Terminal v3.0</div>
        <div class="flex items-center space-x-2">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
            </span>
            <span class="text-[10px] text-green-500 uppercase">Live</span>
        </div>
    </div>

    <div class="p-4 h-96 overflow-y-auto bg-opacity-95" id="terminal-scroll">
        <div class="flex flex-col space-y-1" id="terminal-content">
            @php
            $record = $getRecord();
    
            // DER ENTSCHEIDENDE FIX:
            // Wir zwingen das Model, sich frisch aus der DB zu laden,
            // damit der Worker-Fortschritt sichtbar wird.
            if ($record) {
                $record->refresh();
            }

            $logs = is_array($record?->processing_logs) ? $record->processing_logs : [];
        @endphp

        {{-- Der Rest des HTML-Codes bleibt exakt so, wie wir ihn vorhin gebaut haben --}}

            @if(empty($logs))
                <div class="text-gray-600 animate-pulse italic"> > IDLE: Awaiting pipeline trigger...</div>
            @else
                @foreach($logs as $log)
                    <div class="leading-relaxed border-l-2 border-gray-800 pl-2">
                        <span class="text-blue-500 opacity-75">[{{ $log['t'] ?? '' }}]</span>
                        <span class="font-bold @if(($log['type'] ?? '') == 'error') text-red-500 @elseif(($log['type'] ?? '') == 'done') text-green-400 @else text-yellow-500 @endif">
                            {{ match($log['type'] ?? 'info') {
                                'done' => '>>[OK]',
                                'error' => '##[ERR]',
                                default => ' [SYS]'
                            } }}
                        </span>
                        <span class="text-gray-200 ml-2">{!! $log['m'] ?? '' !!}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
<style>
    #terminal-scroll {
        scroll-behavior: smooth;
    }
    #terminal-scroll::-webkit-scrollbar { width: 6px; }
    #terminal-scroll::-webkit-scrollbar-track { background: #000; }
    #terminal-scroll::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }
    #terminal-scroll::-webkit-scrollbar-thumb:hover { background: #444; }
</style>
