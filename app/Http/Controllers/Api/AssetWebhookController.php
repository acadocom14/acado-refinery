<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Jobs\ProcessAssetPipelineJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssetWebhookController extends Controller
{
    public function handle(Request $request, $assetId)
    {
        $asset = Asset::findOrFail($assetId);

        // Wir nehmen den Content aus dem JSON-Body 'content'
        $triggerData = $request->input('content');

        if (empty($triggerData)) {
            return response()->json(['error' => 'No content found in request'], 400);
        }

        // 1. Trigger im Asset ablegen
        $asset->update([
            'trigger_content' => $triggerData,
            'status' => 'processing',
            'processing_logs' => [], // Reset für den neuen Live-Ticker
        ]);

        // 2. Refinery-Maschine sofort anwerfen
        ProcessAssetPipelineJob::dispatch($asset);

        return response()->json([
            'status' => 'success',
            'asset' => $asset->name,
            'message' => 'Refinery started via Webhook.'
        ]);
    }
}
