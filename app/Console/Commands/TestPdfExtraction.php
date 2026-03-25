<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IngestSignal;
use Smalot\PdfParser\Parser;

class TestPdfExtraction extends Command
{
    // Der Aufruf in der Konsole, z.B.: php artisan scout:test-pdf 1
    protected $signature = 'scout:test-pdf {signal_id} {--memory=512M : Setzt das temporäre Speicherlimit}';

    protected $description = 'Sandbox: Isoliert den Smalot PDF-Parser, um Abstürze (wie den foreach-Error) forensisch zu debuggen.';

    public function handle()
    {
        $signalId = $this->argument('signal_id');
        ini_set('memory_limit', $this->option('memory'));

        $this->info("🚀 Starte PDF-Forensik für Signal ID: {$signalId}");
        $signal = IngestSignal::find($signalId);

        if (!$signal) {
            $this->error("❌ IngestSignal nicht gefunden.");
            return Command::FAILURE;
        }

        $mediaList = $signal->getMedia('scouts');

        foreach ($mediaList as $media) {
            $path = $media->getPath();
            $this->newLine();
            $this->warn("==================================================");
            $this->warn("📄 TESTE DATEI: {$media->file_name}");
            $this->warn("==================================================");

            // --- NEU: TEST 1 - System Level (pdftotext) ---
            $this->info("🔍 TEST 1: System-Level Extraktion (pdftotext)");
            
            $versionOutput = [];
            $versionReturn = -1;
            exec("pdftotext -v 2>&1", $versionOutput, $versionReturn);

            if ($versionReturn !== 0 && $versionReturn !== 99) { // 99 ist bei manchen Windows-Ports der Exit-Code für -v
                $this->error("❌ 'pdftotext' ist nicht installiert oder nicht in den Windows-Umgebungsvariablen (PATH)!");
                $this->line("👉 Lösung für Windows: Lade die Xpdf-Tools herunter und füge den bin-Ordner zum PATH hinzu.");
            } else {
                $this->info("✅ pdftotext gefunden! Starte Extraktion...");
                $output = [];
                $returnVar = -1;
                
                // Windows-kompatibler Aufruf
                $cmd = "pdftotext -layout " . escapeshellarg($path) . " -";
                exec($cmd, $output, $returnVar);

                if ($returnVar === 0 && count($output) > 0) {
                    $text = implode("\n", $output);
                    $preview = substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 200);
                    $this->info("✅ ERFOLG: " . strlen($text) . " Zeichen extrahiert.");
                    $this->info("📝 PREVIEW: {$preview}...");
                } else {
                    $this->error("❌ pdftotext konnte die Datei nicht lesen. Exit Code: {$returnVar}");
                }
            }
            
            $this->newLine();
            // --- TEST 2 - Smalot (zum Vergleich) ---
            $this->info("🔍 TEST 2: PHP-Level Extraktion (Smalot)");
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                $pages = $pdf->getPages();
                $this->info("✅ ERFOLG: Smalot konnte die Datei lesen.");
            } catch (\Throwable $e) {
                $this->error("❌ Smalot Absturz: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
