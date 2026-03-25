<div wire:poll.2s class="font-mono text-sm bg-black p-4 rounded-lg h-64 overflow-y-auto">
    @if($record && is_array($record->processing_logs) && count($record->processing_logs) > 0)
        @foreach($record->processing_logs as $log)
            <div class="mb-1">
                <span class="text-gray-500">[{{ $log['t'] ?? '' }}]</span>
                
                @if(($log['type'] ?? '') === 'error')
                    <span class="text-red-500 font-bold">{{ $log['m'] ?? '' }}</span>
                @elseif(($log['type'] ?? '') === 'warning')
                    <span class="text-yellow-500">{{ $log['m'] ?? '' }}</span>
                @elseif(($log['type'] ?? '') === 'done')
                    <span class="text-blue-400 font-bold">{{ $log['m'] ?? '' }}</span>
                @else
                    <span class="text-green-400">{{ $log['m'] ?? '' }}</span>
                @endif
            </div>
        @endforeach
    @else
        <div class="text-gray-500">... standby. Warte auf Signal ...</div>
    @endif
</div>
