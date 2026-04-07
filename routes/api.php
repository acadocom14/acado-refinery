Route::post('/assets/{assetId}/webhook', [App\Http\Controllers\Api\AssetWebhookController::class, 'handle']);
