<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Agent;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds for the US-optimized ACADO board.
     */
    public function run(): void
{
    $agents = [
        // --- C-SUITE ---
        ['name' => 'Jackson', 'role_code' => 'CEO', 'acado_coins' => 2500, 'system_prompt' => 'Strategy & Exit Lead.'],
        ['name' => 'Marcus',  'role_code' => 'CFO', 'acado_coins' => 1500, 'system_prompt' => 'Finance & EBITDA Validation.'],
        ['name' => 'Elena',   'role_code' => 'CMO', 'acado_coins' => 1500, 'system_prompt' => 'Marketing Triggers & Templates.'],
        ['name' => 'Sarah',   'role_code' => 'COO', 'acado_coins' => 1500, 'system_prompt' => 'Factory & Operations Management.'],
        ['name' => 'Leo',     'role_code' => 'CTO', 'acado_coins' => 1500, 'system_prompt' => 'Tech Stack & Blueprint Authority.'],
        
        // --- RISK & BOARD ---
        ['name' => 'Dexter',  'role_code' => 'LEGAL', 'acado_coins' => 1000, 'system_prompt' => 'Veto Power & Final Risk Check.'],
        ['name' => 'Miller',  'role_code' => 'COMP-US', 'acado_coins' => 1000, 'system_prompt' => 'US Market Compliance Specialist.'],
        ['name' => 'Schmidt', 'role_code' => 'COMP-EU', 'acado_coins' => 1000, 'system_prompt' => 'EU Market Compliance Specialist.'],
        ['name' => 'Vance',   'role_code' => 'INVESTOR', 'acado_coins' => 5000, 'system_prompt' => 'ROI Guardian & Exit Architect.'],
        ['name' => 'Silas',   'role_code' => 'ANALYST', 'acado_coins' => 1000, 'system_prompt' => 'Market Data & Signal Validation.'],
    ];

    foreach ($agents as $agent) {
        \App\Models\Agent::updateOrCreate(['name' => $agent['name']], $agent);
    }
  }
}
