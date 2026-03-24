<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
public function up(): void
{
    // Wir nutzen $this->migrator statt $this->common
    $this->migrator->add('llm.global_default_model', 'gemini-2.5-flash');
    $this->migrator->add('llm.use_intelligent_fallback', true);

    // Platzhalter für die einzelnen Phasen (null = nutzt den Standard)
    $this->migrator->add('llm.model_extraction', null);
    $this->migrator->add('llm.model_analysis', null);
    $this->migrator->add('llm.model_association', null);
}
};
