<div class="w-full bg-black rounded-lg border-2 border-gray-700 shadow-2xl overflow-hidden font-mono text-sm">
    <div class="bg-gray-800 px-4 py-2 flex items-center justify-between border-b border-gray-700">
        <div class="flex space-x-2">
            <div class="w-3 h-3 rounded-full bg-red-500"></div>
            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
            <div class="w-3 h-3 rounded-full bg-green-500"></div>
        </div>
        <div class="text-gray-400 text-xs uppercase tracking-widest">Acado Refinery Terminal v2.5</div>
        <div></div>
    </div>

    <div class="p-4 h-96 overflow-y-auto flex flex-col-reverse bg-opacity-95" id="terminal-scroll">
        @php
            $logs = is_array($getState()) ? array_reverse($getState()) : [];
        @endphp

        @if(empty($logs))
            <div class="text-gray-600 animate-pulse"> > IDLE: Awaiting pipeline trigger...</div>
        @else
            @foreach($logs as $log)
                <div class="mb-1 leading-relaxed">
                    <span class="text-blue-500">[{{ $log['t'] ?? '' }}]</span>
                    <span class="font-bold @if(($log['type'] ?? '') == 'error') text-red-500 @elseif(($log['type'] ?? '') == 'done') text-green-400 @else text-yellow-500 @endif">
                        {{ ($log['type'] ?? 'info') == 'done' ? '>>[OK]' : ( ($log['type'] ?? 'info') == 'error' ? '##[ERR]' : ' [SYS]' ) }}
                    </span>
                    <span class="text-gray-300 ml-2 italic">{{ $log['m'] ?? '' }}</span>
                </div>
            @endforeach
        @endif
    </div>
</div>

<style>
    #terminal-scroll::-webkit-scrollbar { width: 8px; }
    #terminal-scroll::-webkit-scrollbar-track { background: #1a1a1a; }
    #terminal-scroll::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }
</style>
